<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Administração da fundação multi-competição.
 */
class Mengao360_Bolao_Admin_Pools {

    const MENU_SLUG = 'mega-bolao-360-boloes';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 20);
        add_action('admin_post_m360_bolao_pool_action', [__CLASS__, 'handle_action']);
    }

    public static function register_menu() {
        add_submenu_page(
            Mengao360_Bolao_Admin::MENU_SLUG,
            'Gerenciar Bolões',
            'Gerenciar Bolões',
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render']
        );
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão para acessar esta página.', 'mengao360-bolao'));
        }

        $pdo = Mengao360_Bolao_DB::conectar();
        echo '<div class="wrap"><h1>' . esc_html__('Gerenciar Bolões — Mega Bolão 360', 'mengao360-bolao') . '</h1>';
        self::render_notice();

        if (!$pdo) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Conexão com o DW Esportivo indisponível.', 'mengao360-bolao') . '</p></div></div>';
            return;
        }

        $preflight = Mengao360_Bolao_Schema::preflight($pdo);
        self::render_preflight($preflight);

        if (!$preflight['ready']) {
            echo '</div>';
            return;
        }

        $pools = self::get_pools($pdo);
        $competitions = self::get_eligible_competitions($pdo);
        $scoring_rules = self::get_lookup($pdo, 'bolao_regras_pontuacao', 'regra_pontuacao_id');
        $credit_rules = self::get_lookup($pdo, 'bolao_regras_creditos', 'regra_credito_id');
        $statuses = self::get_lookup($pdo, 'bolao_status_competicao', 'status_competicao_id');

        self::render_pool_list($pools);
        self::render_create_form($competitions, $scoring_rules, $credit_rules, $statuses);
        echo '</div>';
    }

    public static function handle_action() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão para executar esta ação.', 'mengao360-bolao'));
        }

        check_admin_referer('m360_bolao_pool_action');

        $action = isset($_POST['acao_bolao']) ? sanitize_key(wp_unslash($_POST['acao_bolao'])) : '';
        $pdo = Mengao360_Bolao_DB::conectar();

        if (!$pdo) {
            self::redirect('error', 'Conexão com o DW Esportivo indisponível.');
        }

        try {
            if ($action === 'migrate') {
                Mengao360_Bolao_Schema::migrate($pdo, get_current_user_id());
                self::redirect('success', 'Migração da fundação multi-competição aplicada.');
            }

            $preflight = Mengao360_Bolao_Schema::preflight($pdo);
            if (!$preflight['ready']) {
                throw new RuntimeException('A fundação multi-competição ainda não foi migrada.');
            }

            if ($action === 'create') {
                self::create_pool($pdo);
                self::redirect('success', 'Bolão criado como rascunho.');
            }

            if ($action === 'transition') {
                self::transition_pool($pdo);
                self::redirect('success', 'Estado do bolão atualizado.');
            }

            if ($action === 'sync') {
                $pool_id = isset($_POST['bolao_competicao_id']) ? absint($_POST['bolao_competicao_id']) : 0;
                $key = 'admin-' . gmdate('YmdHi') . '-' . get_current_user_id();
                $result = Mengao360_Bolao_Sync::run($pdo, $pool_id, $key, get_current_user_id());
                self::redirect(
                    'success',
                    !empty($result['skipped'])
                        ? 'Sincronização já processada para o mesmo estado do DW.'
                        : 'Sincronização pós-ETL concluída.'
                );
            }

            throw new RuntimeException('Ação administrativa não reconhecida.');
        } catch (Throwable $e) {
            error_log('Mega Bolão 360 — administração de bolões: ' . $e->getMessage());
            self::redirect('error', $e->getMessage());
        }
    }

    private static function create_pool($pdo) {
        $competition_id = isset($_POST['competicao_id']) ? absint($_POST['competicao_id']) : 0;
        $season = isset($_POST['temporada']) ? sanitize_text_field(wp_unslash($_POST['temporada'])) : '';
        $title = isset($_POST['titulo']) ? sanitize_text_field(wp_unslash($_POST['titulo'])) : '';
        $slug = isset($_POST['slug_bolao']) ? sanitize_title(wp_unslash($_POST['slug_bolao'])) : '';
        $description = isset($_POST['descricao']) ? sanitize_textarea_field(wp_unslash($_POST['descricao'])) : '';
        $scoring_rule = isset($_POST['regra_pontuacao_id']) ? absint($_POST['regra_pontuacao_id']) : 0;
        $credit_rule = isset($_POST['regra_credito_id']) ? absint($_POST['regra_credito_id']) : 0;
        $status_id = isset($_POST['status_competicao_id']) ? absint($_POST['status_competicao_id']) : 0;
        $closing_minutes = isset($_POST['janela_fechamento_minutos'])
            ? min(180, max(0, absint($_POST['janela_fechamento_minutos'])))
            : 10;

        if (!$competition_id || $season === '' || $title === '' || $slug === '') {
            throw new InvalidArgumentException('Competição, temporada, título e slug são obrigatórios.');
        }

        if (!$scoring_rule || !$credit_rule || !$status_id) {
            throw new InvalidArgumentException('Selecione as regras e o status inicial.');
        }

        $eligible = self::find_eligible_competition($pdo, $competition_id, $season);
        if (!$eligible) {
            throw new RuntimeException('A competição/temporada não está elegível no DW.');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO bolao_competicoes (
                competicao_id,
                temporada,
                regra_pontuacao_id,
                regra_credito_id,
                status_competicao_id,
                estado_operacional,
                visibilidade,
                janela_fechamento_minutos,
                slug_bolao,
                titulo,
                descricao,
                ind_publico,
                ind_ativo,
                criado_por_wp_user_id
            ) VALUES (?, ?, ?, ?, ?, 'RASCUNHO', 'PUBLICO', ?, ?, ?, ?, 1, 1, ?)"
        );
        $stmt->execute([
            $competition_id,
            $season,
            $scoring_rule,
            $credit_rule,
            $status_id,
            $closing_minutes,
            $slug,
            $title,
            $description,
            get_current_user_id(),
        ]);

        $pool_id = (int) $pdo->lastInsertId();
        self::audit($pdo, 'BOLAO_CRIADO', 'bolao_competicoes', $pool_id, $pool_id, null, [
            'competicao_id' => $competition_id,
            'temporada' => $season,
            'slug_bolao' => $slug,
            'estado_operacional' => 'RASCUNHO',
        ]);
    }

    private static function transition_pool($pdo) {
        $pool_id = isset($_POST['bolao_competicao_id']) ? absint($_POST['bolao_competicao_id']) : 0;
        $target = isset($_POST['estado_operacional'])
            ? strtoupper(sanitize_key(wp_unslash($_POST['estado_operacional'])))
            : '';

        $transitions = [
            'RASCUNHO' => ['ABERTO', 'ARQUIVADO'],
            'ABERTO' => ['BLOQUEADO', 'ARQUIVADO'],
            'BLOQUEADO' => ['ABERTO', 'EM_APURACAO', 'ARQUIVADO'],
            'EM_APURACAO' => ['ENCERRADO', 'BLOQUEADO'],
            'ENCERRADO' => ['ARQUIVADO'],
            'ARQUIVADO' => [],
        ];

        $stmt = $pdo->prepare(
            'SELECT bolao_competicao_id, estado_operacional
             FROM bolao_competicoes
             WHERE bolao_competicao_id = ?
             LIMIT 1'
        );
        $stmt->execute([$pool_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current || !isset($transitions[$current['estado_operacional']])) {
            throw new RuntimeException('Bolão ou estado atual inválido.');
        }

        if (!in_array($target, $transitions[$current['estado_operacional']], true)) {
            throw new RuntimeException('Transição de estado não permitida.');
        }

        $published_sql = $target === 'ABERTO'
            ? ', dth_publicacao = COALESCE(dth_publicacao, NOW())'
            : '';
        $archived_sql = $target === 'ARQUIVADO'
            ? ', dth_arquivamento = NOW(), ind_ativo = 0'
            : '';

        $stmt = $pdo->prepare(
            "UPDATE bolao_competicoes
             SET estado_operacional = ?,
                 atualizado_por_wp_user_id = ?,
                 dth_atualizacao = NOW()
                 {$published_sql}
                 {$archived_sql}
             WHERE bolao_competicao_id = ?"
        );
        $stmt->execute([$target, get_current_user_id(), $pool_id]);

        self::audit(
            $pdo,
            'BOLAO_ESTADO_ALTERADO',
            'bolao_competicoes',
            $pool_id,
            $pool_id,
            ['estado_operacional' => $current['estado_operacional']],
            ['estado_operacional' => $target]
        );
    }

    private static function get_pools($pdo) {
        $stmt = $pdo->query(
            'SELECT
                bc.bolao_competicao_id,
                bc.titulo,
                bc.slug_bolao,
                bc.temporada,
                bc.estado_operacional,
                bc.ind_ativo,
                dc.nome AS competicao_nome,
                dcm.nome_modelo
             FROM bolao_competicoes bc
             INNER JOIN dim_competicoes dc ON dc.id = bc.competicao_id
             LEFT JOIN dim_competicao_modelo dcm ON dcm.id = dc.modelo_id
             ORDER BY bc.bolao_competicao_id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function get_eligible_competitions($pdo) {
        $stmt = $pdo->query(
            "SELECT
                dc.id AS competicao_id,
                dc.nome,
                dc.slug,
                dc.temporada,
                dcm.nome_modelo,
                dcm.slug_modelo,
                dcm.tipo_mata_mata,
                COUNT(fj.id) AS total_jogos,
                SUM(CASE
                    WHEN fj.data_jogo IS NOT NULL
                     AND fj.status_jogo IS NOT NULL
                    THEN 1 ELSE 0 END) AS jogos_com_calendario
             FROM dim_competicoes dc
             INNER JOIN dim_competicao_modelo dcm
                ON dcm.id = dc.modelo_id
               AND dcm.ativo = 1
             INNER JOIN fato_jogos fj
                ON fj.competicao_id = dc.id
             WHERE dc.ativo = 1
               AND dc.temporada IS NOT NULL
               AND dc.temporada <> ''
             GROUP BY
                dc.id, dc.nome, dc.slug, dc.temporada,
                dcm.nome_modelo, dcm.slug_modelo, dcm.tipo_mata_mata
             HAVING COUNT(fj.id) > 0
                AND jogos_com_calendario > 0
             ORDER BY dc.temporada DESC, dc.nome"
        );

        $competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($competitions, function ($competition) {
            return Mengao360_Bolao_Competition_Model::is_supported(
                $competition['slug_modelo'],
                $competition['nome_modelo'],
                $competition['tipo_mata_mata']
            );
        }));
    }

    private static function find_eligible_competition($pdo, $competition_id, $season) {
        foreach (self::get_eligible_competitions($pdo) as $competition) {
            if ((int) $competition['competicao_id'] === (int) $competition_id
                && (string) $competition['temporada'] === (string) $season) {
                return $competition;
            }
        }

        return null;
    }

    private static function get_lookup($pdo, $table, $id_column) {
        $allowed = [
            'bolao_regras_pontuacao' => 'regra_pontuacao_id',
            'bolao_regras_creditos' => 'regra_credito_id',
            'bolao_status_competicao' => 'status_competicao_id',
        ];

        if (!isset($allowed[$table]) || $allowed[$table] !== $id_column) {
            return [];
        }

        $stmt = $pdo->query(
            "SELECT {$id_column} AS id, codigo, nome
             FROM {$table}
             WHERE ind_ativo = 1
             ORDER BY nome"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function render_preflight($preflight) {
        $class = $preflight['ready'] ? 'notice-success' : 'notice-warning';
        echo '<div class="notice ' . esc_attr($class) . '"><p>';
        echo $preflight['ready']
            ? esc_html__('Schema multi-competição pronto.', 'mengao360-bolao')
            : esc_html__('Schema multi-competição pendente.', 'mengao360-bolao');
        echo '</p>';

        if (!empty($preflight['missing_legacy_tables'])) {
            echo '<p>' . esc_html__('Baseline incompleto: ', 'mengao360-bolao')
                . esc_html(implode(', ', $preflight['missing_legacy_tables'])) . '</p>';
        }

        if (!empty($preflight['compatibility_issues'])) {
            echo '<p><strong>' . esc_html__('Migração bloqueada por incompatibilidade de dados:', 'mengao360-bolao') . '</strong></p><ul>';
            foreach ($preflight['compatibility_issues'] as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
        }

        if (!empty($preflight['missing_foundation_columns'])) {
            echo '<p>' . esc_html__('Colunas C.1 pendentes: ', 'mengao360-bolao')
                . esc_html(implode(', ', $preflight['missing_foundation_columns'])) . '</p>';
        }

        if (!empty($preflight['missing_foundation_indexes'])) {
            echo '<p>' . esc_html__('Índices C.1 pendentes: ', 'mengao360-bolao')
                . esc_html(implode(', ', $preflight['missing_foundation_indexes'])) . '</p>';
        }

        if (!$preflight['ready'] && empty($preflight['missing_legacy_tables'])) {
            if ($preflight['migrations_allowed'] && empty($preflight['compatibility_issues'])) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('m360_bolao_pool_action');
                echo '<input type="hidden" name="action" value="m360_bolao_pool_action">';
                echo '<input type="hidden" name="acao_bolao" value="migrate">';
                submit_button(__('Aplicar migração C.1', 'mengao360-bolao'), 'secondary', 'submit', false);
                echo '</form>';
            } elseif (!empty($preflight['compatibility_issues'])) {
                echo '<p>' . esc_html__(
                    'O botão de migração permanecerá indisponível até a correção das incompatibilidades.',
                    'mengao360-bolao'
                ) . '</p>';
            } else {
                echo '<p><code>define(\'MENGAO360_BOLAO_ALLOW_SCHEMA_MIGRATIONS\', true);</code></p>';
                echo '<p>' . esc_html__('Habilite a constante somente durante uma janela controlada com backup validado.', 'mengao360-bolao') . '</p>';
            }
        }

        echo '</div>';
    }

    private static function render_pool_list($pools) {
        echo '<h2>' . esc_html__('Bolões cadastrados', 'mengao360-bolao') . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['ID', 'Bolão', 'Competição', 'Temporada', 'Modelo', 'Estado', 'Ação'] as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if (!$pools) {
            echo '<tr><td colspan="7">' . esc_html__('Nenhum bolão cadastrado.', 'mengao360-bolao') . '</td></tr>';
        }

        foreach ($pools as $pool) {
            echo '<tr>';
            echo '<td>' . (int) $pool['bolao_competicao_id'] . '</td>';
            echo '<td><strong>' . esc_html($pool['titulo']) . '</strong><br><code>' . esc_html($pool['slug_bolao']) . '</code></td>';
            echo '<td>' . esc_html($pool['competicao_nome']) . '</td>';
            echo '<td>' . esc_html($pool['temporada']) . '</td>';
            echo '<td>' . esc_html($pool['nome_modelo'] ?: '—') . '</td>';
            echo '<td>' . esc_html($pool['estado_operacional']) . '</td>';
            echo '<td>' . self::render_transition_form($pool) . self::render_sync_form($pool) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function render_transition_form($pool) {
        $targets = [
            'RASCUNHO' => ['ABERTO', 'ARQUIVADO'],
            'ABERTO' => ['BLOQUEADO', 'ARQUIVADO'],
            'BLOQUEADO' => ['ABERTO', 'EM_APURACAO', 'ARQUIVADO'],
            'EM_APURACAO' => ['ENCERRADO', 'BLOQUEADO'],
            'ENCERRADO' => ['ARQUIVADO'],
            'ARQUIVADO' => [],
        ];
        $available = $targets[$pool['estado_operacional']] ?? [];

        if (!$available) {
            return '—';
        }

        ob_start();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('m360_bolao_pool_action');
        echo '<input type="hidden" name="action" value="m360_bolao_pool_action">';
        echo '<input type="hidden" name="acao_bolao" value="transition">';
        echo '<input type="hidden" name="bolao_competicao_id" value="' . (int) $pool['bolao_competicao_id'] . '">';
        echo '<select name="estado_operacional">';
        foreach ($available as $target) {
            echo '<option value="' . esc_attr($target) . '">' . esc_html($target) . '</option>';
        }
        echo '</select> ';
        submit_button(__('Aplicar', 'mengao360-bolao'), 'small', 'submit', false);
        echo '</form>';

        return ob_get_clean();
    }

    private static function render_sync_form($pool) {
        if ((int) $pool['ind_ativo'] !== 1 || $pool['estado_operacional'] === 'RASCUNHO') {
            return '';
        }

        ob_start();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('m360_bolao_pool_action');
        echo '<input type="hidden" name="action" value="m360_bolao_pool_action">';
        echo '<input type="hidden" name="acao_bolao" value="sync">';
        echo '<input type="hidden" name="bolao_competicao_id" value="' . (int) $pool['bolao_competicao_id'] . '">';
        submit_button(__('Sincronizar pós-ETL', 'mengao360-bolao'), 'small secondary', 'submit', false);
        echo '</form>';

        return ob_get_clean();
    }

    private static function render_create_form($competitions, $scoring_rules, $credit_rules, $statuses) {
        echo '<h2>' . esc_html__('Criar bolão administrado', 'mengao360-bolao') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('m360_bolao_pool_action');
        echo '<input type="hidden" name="action" value="m360_bolao_pool_action">';
        echo '<input type="hidden" name="acao_bolao" value="create">';
        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="m360-competition">Competição/temporada</label></th><td>';
        echo '<select id="m360-competition" name="competicao_id" required>';
        echo '<option value="">' . esc_html__('Selecione no catálogo do DW', 'mengao360-bolao') . '</option>';
        foreach ($competitions as $competition) {
            echo '<option value="' . (int) $competition['competicao_id'] . '" data-season="' . esc_attr($competition['temporada']) . '">'
                . esc_html($competition['nome'] . ' — ' . $competition['temporada'] . ' — ' . $competition['nome_modelo'])
                . '</option>';
        }
        echo '</select>';
        echo '<input id="m360-season" type="text" name="temporada" required readonly class="small-text"> ';
        echo '<p class="description">A temporada é preenchida a partir do catálogo somente leitura do DW.</p></td></tr>';

        self::text_row('Título', 'titulo', true);
        self::text_row('Slug', 'slug_bolao', true);
        self::text_row('Descrição', 'descricao', false);
        self::lookup_row('Regra de pontuação', 'regra_pontuacao_id', $scoring_rules);
        self::lookup_row('Regra de créditos', 'regra_credito_id', $credit_rules);
        self::lookup_row('Status legado inicial', 'status_competicao_id', $statuses);

        echo '<tr><th><label for="m360-closing">Fechamento</label></th><td>';
        echo '<input id="m360-closing" type="number" min="0" max="180" name="janela_fechamento_minutos" value="10"> minutos antes do jogo';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button(__('Criar como rascunho', 'mengao360-bolao'));
        echo '</form>';
        echo "<script>
            document.addEventListener('DOMContentLoaded', function () {
                var competition = document.getElementById('m360-competition');
                var season = document.getElementById('m360-season');
                if (!competition || !season) { return; }
                competition.addEventListener('change', function () {
                    var option = competition.options[competition.selectedIndex];
                    season.value = option ? (option.getAttribute('data-season') || '') : '';
                });
            });
        </script>";
    }

    private static function text_row($label, $name, $required) {
        echo '<tr><th><label for="m360-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" id="m360-' . esc_attr($name) . '" name="' . esc_attr($name) . '" type="text"'
            . ($required ? ' required' : '') . '>';
        echo '</td></tr>';
    }

    private static function lookup_row($label, $name, $items) {
        echo '<tr><th><label for="m360-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<select id="m360-' . esc_attr($name) . '" name="' . esc_attr($name) . '" required>';
        foreach ($items as $item) {
            echo '<option value="' . (int) $item['id'] . '">' . esc_html($item['nome'] . ' (' . $item['codigo'] . ')') . '</option>';
        }
        echo '</select></td></tr>';
    }

    private static function audit($pdo, $event, $entity, $entity_id, $pool_id, $before, $after) {
        $request_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('m360-', true);
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $stmt = $pdo->prepare(
            'INSERT INTO bolao_auditoria
                (request_id, evento, entidade, entidade_id, bolao_competicao_id, wp_user_id, ip_hash, estado_anterior, estado_novo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $request_id,
            $event,
            $entity,
            (string) $entity_id,
            (int) $pool_id,
            get_current_user_id(),
            hash('sha256', $ip . wp_salt('auth')),
            $before === null ? null : wp_json_encode($before, JSON_UNESCAPED_UNICODE),
            $after === null ? null : wp_json_encode($after, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private static function render_notice() {
        if (empty($_GET['m360_notice'])) {
            return;
        }

        $type = isset($_GET['m360_notice_type']) ? sanitize_key(wp_unslash($_GET['m360_notice_type'])) : 'success';
        $message = sanitize_text_field(wp_unslash($_GET['m360_notice']));
        echo '<div class="notice ' . ($type === 'error' ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>'
            . esc_html($message) . '</p></div>';
    }

    private static function redirect($type, $message) {
        wp_safe_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'm360_notice_type' => $type,
            'm360_notice' => $message,
        ], admin_url('admin.php')));
        exit;
    }
}
