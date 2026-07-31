<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Diagnóstico somente leitura para pré-homologação controlada em produção.
 *
 * Esta tela não registra actions de escrita, não executa DDL e não altera
 * opções do WordPress. A migração continua bloqueada pela constante dedicada.
 */
class Mengao360_Bolao_Pre_Homologation {

    const MENU_SLUG = 'mega-bolao-360-pre-homologacao';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 30);
    }

    public static function register_menu() {
        add_submenu_page(
            Mengao360_Bolao_Admin::MENU_SLUG,
            'Pré-homologação',
            'Pré-homologação',
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render']
        );
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão para acessar esta página.', 'mengao360-bolao'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Pré-homologação — Mega Bolão 360', 'mengao360-bolao') . '</h1>';
        echo '<p>' . esc_html__(
            'Diagnóstico somente leitura. Esta página não executa migrações nem altera o DW.',
            'mengao360-bolao'
        ) . '</p>';

        $pdo = Mengao360_Bolao_DB::conectar();
        if (!$pdo) {
            self::render_gate('BLOQUEADO', 'Não foi possível conectar ao DW Esportivo.');
            echo '</div>';
            return;
        }

        try {
            $report = self::build_report($pdo);
            self::render_gate($report['gate'], $report['gate_message']);
            self::render_runtime($report);
            self::render_checks($report['checks']);
            self::render_counts($report['counts']);
            self::render_pools($report['pools']);
            self::render_languages($report['languages']);
            self::render_next_step($report);
        } catch (Throwable $e) {
            error_log('Mega Bolão 360 — pré-homologação: ' . $e->getMessage());
            self::render_gate(
                'BLOQUEADO',
                'O diagnóstico não pôde ser concluído. Consulte o log do WordPress.'
            );
        }

        echo '</div>';
    }

    private static function build_report($pdo) {
        $required_tables = [
            'bolao_competicoes',
            'bolao_usuarios',
            'bolao_palpites',
            'bolao_ligas',
            'bolao_liga_participantes',
            'bolao_ranking',
            'bolao_resultados_partidas',
            'bolao_status_competicao',
            'dim_competicoes',
            'dim_competicao_modelo',
            'fato_jogos',
        ];
        $foundation_tables = [
            'bolao_participantes',
            'bolao_resultados_overrides',
            'bolao_auditoria',
            'bolao_sincronizacoes',
            'bolao_schema_migrations',
        ];
        $checks = [];
        $critical = 0;
        $schema_preflight = Mengao360_Bolao_Schema::preflight($pdo);

        foreach ($required_tables as $table) {
            $exists = Mengao360_Bolao_Schema::table_exists($pdo, $table);
            $checks[] = [
                'group' => 'Baseline obrigatório',
                'item' => $table,
                'status' => $exists ? 'OK' : 'BLOQUEADO',
                'detail' => $exists ? 'Disponível.' : 'Tabela ausente.',
            ];
            if (!$exists) {
                $critical++;
            }
        }

        foreach ($foundation_tables as $table) {
            $exists = Mengao360_Bolao_Schema::table_exists($pdo, $table);
            $checks[] = [
                'group' => 'Fundação C.1',
                'item' => $table,
                'status' => $exists ? 'OK' : 'PENDENTE',
                'detail' => $exists
                    ? ($schema_preflight['ready']
                        ? 'Estrutura disponível e migração C.1 registrada.'
                        : 'Já existe; a migração deverá validar sua estrutura.')
                    : 'Será criada pela migração controlada.',
            ];
        }

        $migration_enabled = Mengao360_Bolao_Schema::migrations_allowed();
        $checks[] = [
            'group' => 'Segurança',
            'item' => 'MENGAO360_BOLAO_ALLOW_SCHEMA_MIGRATIONS',
            'status' => $migration_enabled ? 'BLOQUEADO' : 'OK',
            'detail' => $migration_enabled
                ? 'A constante está true fora da janela de migração.'
                : 'Migrações bloqueadas, como esperado na pré-homologação.',
        ];
        if ($migration_enabled) {
            $critical++;
        }

        $procedure_exists = self::routine_exists($pdo, 'sp_bolao_processar_jogos_finalizados_api');
        $checks[] = [
            'group' => 'Integração pós-ETL',
            'item' => 'sp_bolao_processar_jogos_finalizados_api',
            'status' => $procedure_exists ? 'OK' : 'BLOQUEADO',
            'detail' => $procedure_exists ? 'Procedure disponível.' : 'Procedure ausente.',
        ];
        if (!$procedure_exists) {
            $critical++;
        }

        $null_competitions = self::scalar(
            $pdo,
            'SELECT COUNT(*) FROM bolao_competicoes WHERE competicao_id IS NULL'
        );
        $invalid_seasons = self::scalar(
            $pdo,
            "SELECT COUNT(*)
             FROM bolao_competicoes
             WHERE temporada IS NULL
                OR CHAR_LENGTH(CAST(temporada AS CHAR)) > 20"
        );
        $checks[] = [
            'group' => 'Compatibilidade de dados',
            'item' => 'Vínculos de competição',
            'status' => $null_competitions === 0 ? 'OK' : 'BLOQUEADO',
            'detail' => $null_competitions === 0
                ? 'Nenhum competicao_id nulo.'
                : $null_competitions . ' registro(s) com competicao_id nulo.',
        ];
        $checks[] = [
            'group' => 'Compatibilidade de dados',
            'item' => 'Temporadas',
            'status' => $invalid_seasons === 0 ? 'OK' : 'BLOQUEADO',
            'detail' => $invalid_seasons === 0
                ? 'Valores compatíveis com VARCHAR(20).'
                : $invalid_seasons . ' registro(s) incompatível(is).',
        ];
        if ($null_competitions > 0 || $invalid_seasons > 0) {
            $critical++;
        }

        $legacy_state_decisions = Mengao360_Bolao_Schema::get_legacy_state_decisions($pdo);
        $checks[] = [
            'group' => 'Ciclo de vida legado',
            'item' => 'Estado inicial C.1',
            'status' => $legacy_state_decisions ? 'REVISÃO' : 'OK',
            'detail' => $legacy_state_decisions
                ? count($legacy_state_decisions) . ' bolão(ões) ativo(s) sem data de fechamento exigirá(ão) classificação explícita na migração.'
                : 'Nenhuma classificação manual pendente.',
        ];

        $counts = [];
        foreach ([
            'bolao_competicoes',
            'bolao_usuarios',
            'bolao_palpites',
            'bolao_ligas',
            'bolao_liga_participantes',
            'bolao_ranking',
            'bolao_resultados_partidas',
            'fato_jogos',
        ] as $table) {
            if (Mengao360_Bolao_Schema::table_exists($pdo, $table)) {
                $counts[$table] = self::safe_table_count($pdo, $table);
            }
        }

        $gate = 'BLOQUEADO';
        $gate_message = 'Existem verificações críticas que precisam ser resolvidas antes da instalação controlada.';
        if ($critical === 0 && $schema_preflight['ready']) {
            $gate = 'PRONTO PARA PÓS-HOMOLOGAÇÃO';
            $gate_message = 'A migração C.1 está registrada, o schema está pronto e novas migrações permanecem bloqueadas.';
        } elseif ($critical === 0) {
            $gate = 'PRONTO PARA PRÉ-HOMOLOGAÇÃO';
            $gate_message = 'O plugin pode ser instalado para observação. A migração permanece bloqueada.';
        }

        return [
            'gate' => $gate,
            'gate_message' => $gate_message,
            'schema_ready' => $schema_preflight['ready'],
            'checks' => $checks,
            'counts' => $counts,
            'pools' => self::get_pools($pdo),
            'languages' => self::get_languages($pdo),
            'migration_enabled' => $migration_enabled,
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_header_version' => defined('MENGAO360_BOLAO_PLUGIN_VERSION')
                ? MENGAO360_BOLAO_PLUGIN_VERSION
                : 'indisponível',
            'plugin_asset_version' => defined('MENGAO360_BOLAO_VERSION')
                ? MENGAO360_BOLAO_VERSION
                : 'indisponível',
            'plugin_build' => defined('MENGAO360_BOLAO_BUILD')
                ? MENGAO360_BOLAO_BUILD
                : 'indisponível',
            'asset_version' => defined('MENGAO360_BOLAO_ASSET_VERSION')
                ? MENGAO360_BOLAO_ASSET_VERSION
                : 'indisponível',
            'database_version' => self::database_version($pdo),
        ];
    }

    private static function get_pools($pdo) {
        if (!Mengao360_Bolao_Schema::table_exists($pdo, 'bolao_competicoes')) {
            return [];
        }

        $has_state = Mengao360_Bolao_Schema::column_exists(
            $pdo,
            'bolao_competicoes',
            'estado_operacional'
        );
        $state_sql = $has_state
            ? 'bc.estado_operacional'
            : "'LEGADO' AS estado_operacional";

        $stmt = $pdo->query(
            "SELECT bc.bolao_competicao_id,
                    bc.titulo,
                    bc.slug_bolao,
                    bc.temporada,
                    bc.ind_ativo,
                    bc.data_abertura,
                    bc.data_fechamento,
                    {$state_sql},
                    dc.nome AS competicao_nome,
                    bsc.codigo AS status_codigo,
                    bsc.nome AS status_nome
             FROM bolao_competicoes bc
             INNER JOIN dim_competicoes dc ON dc.id = bc.competicao_id
             LEFT JOIN bolao_status_competicao bsc
                ON bsc.status_competicao_id = bc.status_competicao_id
             ORDER BY bc.bolao_competicao_id"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function get_languages($pdo) {
        $result = [
            'resolved_admin' => function_exists('m360_bolao_get_lang')
                ? m360_bolao_get_lang()
                : 'pt-BR',
            'wordpress_locale' => function_exists('determine_locale')
                ? determine_locale()
                : get_locale(),
            'catalog' => [],
        ];

        if (!Mengao360_Bolao_Schema::table_exists($pdo, 'm360_i18n_publico')) {
            return $result;
        }

        $stmt = $pdo->query(
            "SELECT idioma, COUNT(*) AS total
             FROM m360_i18n_publico
             WHERE modulo IN ('BOLAO', 'GLOBAL')
               AND ind_ativo = 1
             GROUP BY idioma
             ORDER BY idioma"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result['catalog'][(string) $row['idioma']] = (int) $row['total'];
        }

        return $result;
    }

    private static function routine_exists($pdo, $routine) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.routines
             WHERE routine_schema = DATABASE()
               AND routine_name = ?
               AND routine_type = \'PROCEDURE\''
        );
        $stmt->execute([$routine]);

        return (int) $stmt->fetchColumn() === 1;
    }

    private static function scalar($pdo, $sql) {
        return (int) $pdo->query($sql)->fetchColumn();
    }

    private static function safe_table_count($pdo, $table) {
        $allowed = [
            'bolao_competicoes',
            'bolao_usuarios',
            'bolao_palpites',
            'bolao_ligas',
            'bolao_liga_participantes',
            'bolao_ranking',
            'bolao_resultados_partidas',
            'fato_jogos',
        ];
        if (!in_array($table, $allowed, true)) {
            return 0;
        }

        return (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private static function database_version($pdo) {
        try {
            return (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Throwable $e) {
            return 'indisponível';
        }
    }

    private static function render_gate($gate, $message) {
        $success = strpos($gate, 'PRONTO PARA ') === 0;
        $class = $success ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p><strong>'
            . esc_html($gate)
            . '</strong> — '
            . esc_html($message)
            . '</p></div>';
    }

    private static function render_runtime($report) {
        echo '<h2>' . esc_html__('Runtime observado', 'mengao360-bolao') . '</h2>';
        echo '<table class="widefat striped"><tbody>';
        self::runtime_row('WordPress', $report['wordpress_version']);
        self::runtime_row('PHP', $report['php_version']);
        self::runtime_row('MariaDB/MySQL', $report['database_version']);
        self::runtime_row('Cabeçalho do plugin', $report['plugin_header_version']);
        self::runtime_row('Versão do runtime/assets', $report['plugin_asset_version']);
        self::runtime_row('Build de pré-homologação', $report['plugin_build']);
        self::runtime_row('Cache-buster dos assets', $report['asset_version']);
        echo '</tbody></table>';
    }

    private static function runtime_row($label, $value) {
        echo '<tr><th style="width:240px">' . esc_html($label) . '</th><td><code>'
            . esc_html($value)
            . '</code></td></tr>';
    }

    private static function render_checks($checks) {
        echo '<h2>' . esc_html__('Verificações somente leitura', 'mengao360-bolao') . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['Grupo', 'Item', 'Estado', 'Detalhe'] as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($checks as $check) {
            echo '<tr><td>' . esc_html($check['group']) . '</td>';
            echo '<td><code>' . esc_html($check['item']) . '</code></td>';
            echo '<td><strong>' . esc_html($check['status']) . '</strong></td>';
            echo '<td>' . esc_html($check['detail']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_counts($counts) {
        echo '<h2>' . esc_html__('Snapshot de contagens', 'mengao360-bolao') . '</h2>';
        echo '<p>' . esc_html__(
            'Estas contagens serão comparadas antes e depois da migração.',
            'mengao360-bolao'
        ) . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>Tabela</th><th>Registros</th></tr></thead><tbody>';
        foreach ($counts as $table => $count) {
            echo '<tr><td><code>' . esc_html($table) . '</code></td><td>'
                . esc_html(number_format_i18n($count))
                . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_pools($pools) {
        echo '<h2>' . esc_html__('Bolões existentes', 'mengao360-bolao') . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['ID', 'Bolão', 'Competição', 'Temporada', 'Status legado', 'Estado C.1', 'Ativo', 'Abertura', 'Fechamento'] as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';
        if (!$pools) {
            echo '<tr><td colspan="9">Nenhum bolão encontrado.</td></tr>';
        }
        foreach ($pools as $pool) {
            $legacy_status = implode(
                ' — ',
                array_values(array_filter([
                    (string) $pool['status_codigo'],
                    (string) $pool['status_nome'],
                ], 'strlen'))
            );
            echo '<tr>';
            echo '<td>' . (int) $pool['bolao_competicao_id'] . '</td>';
            echo '<td><strong>' . esc_html($pool['titulo']) . '</strong><br><code>'
                . esc_html($pool['slug_bolao']) . '</code></td>';
            echo '<td>' . esc_html($pool['competicao_nome']) . '</td>';
            echo '<td>' . esc_html($pool['temporada']) . '</td>';
            echo '<td>' . esc_html($legacy_status ?: '—') . '</td>';
            echo '<td>' . esc_html($pool['estado_operacional']) . '</td>';
            echo '<td>' . ((int) $pool['ind_ativo'] === 1 ? 'Sim' : 'Não') . '</td>';
            echo '<td>' . esc_html($pool['data_abertura'] ?: '—') . '</td>';
            echo '<td>' . esc_html($pool['data_fechamento'] ?: '—') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_languages($languages) {
        echo '<h2>' . esc_html__('Contrato de idiomas do widget', 'mengao360-bolao') . '</h2>';
        echo '<p>O shortcode continua priorizando <code>idioma</code>, depois o idioma resolvido pela página/URL e, por fim, <code>pt-BR</code>.</p>';
        echo '<table class="widefat striped"><tbody>';
        self::runtime_row('Locale administrativo observado', $languages['wordpress_locale']);
        self::runtime_row('Idioma resolvido nesta requisição', $languages['resolved_admin']);
        self::runtime_row(
            'Catálogo BOLAO/GLOBAL pt-BR',
            isset($languages['catalog']['pt-BR']) ? (string) $languages['catalog']['pt-BR'] : '0'
        );
        self::runtime_row(
            'Catálogo BOLAO/GLOBAL en-US',
            isset($languages['catalog']['en-US']) ? (string) $languages['catalog']['en-US'] : '0'
        );
        echo '</tbody></table>';
        echo '<p><code>[bolao_mengao bolao="slug-do-bolao" idioma="pt-BR"]</code><br>';
        echo '<code>[bolao_mengao bolao="slug-do-bolao" idioma="en-US"]</code></p>';
    }

    private static function render_next_step($report) {
        echo '<h2>' . esc_html__('Próximo gate', 'mengao360-bolao') . '</h2>';
        if ($report['gate'] === 'BLOQUEADO') {
            echo '<p>' . esc_html__(
                'Não habilite a migração. Corrija os itens BLOQUEADO e execute novamente este diagnóstico.',
                'mengao360-bolao'
            ) . '</p>';
            return;
        }

        if (!empty($report['schema_ready'])) {
            echo '<ol>';
            echo '<li>Manter a constante de migração ausente ou definida como <code>false</code>.</li>';
            echo '<li>Confirmar que as contagens legadas permanecem iguais ao snapshot anterior.</li>';
            echo '<li>Validar o bolão encerrado nas páginas PT-BR e EN-US, inclusive com usuário desconectado.</li>';
            echo '<li>Não arquivar o protótipo até concluir a validação visual e funcional.</li>';
            echo '<li>Somente depois criar o primeiro novo bolão como <code>RASCUNHO</code>.</li>';
            echo '</ol>';
            return;
        }

        echo '<ol>';
        echo '<li>Registrar capturas desta página e exportar o backup integral.</li>';
        echo '<li>Validar as páginas PT-BR e EN-US do bolão encerrado.</li>';
        echo '<li>Na janela de migração, classificar explicitamente o bolão legado da Copa como <code>ENCERRADO</code>.</li>';
        echo '<li>Manter a constante de migração ausente ou definida como false.</li>';
        echo '<li>Somente depois preparar a janela controlada da migração C.1.</li>';
        echo '</ol>';
    }
}
