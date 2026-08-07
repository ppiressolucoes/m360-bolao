<?php
/**
 * Administração operacional do Mega Bolão 360.
 *
 * Sprint 6.2:
 * - Registrar resultado manual/parcial/final;
 * - Apurar jogo;
 * - Atualizar rankings;
 * - Gerar notificações;
 * - Monitorar fila de notificações.
 *
 * Sprint 6.4:
 * - Resumo de conciliação API/DW x Manual;
 * - Alertas operacionais no painel;
 * - Ação manual para executar conciliação;
 * - Badges de alerta por jogo.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Mengao360_Bolao_Admin')) {

    class Mengao360_Bolao_Admin {

        const MENU_SLUG = 'mega-bolao-360-operacao';

        public static function init() {
            add_action('admin_menu', [__CLASS__, 'registrar_menu']);
            add_action('admin_post_m360_bolao_admin_action', [__CLASS__, 'processar_acao']);
        }

        public static function registrar_menu() {
            add_menu_page(
                'Mega Bolão 360',
                'Mega Bolão 360',
                'manage_options',
                self::MENU_SLUG,
                [__CLASS__, 'render_operacao_jogos'],
                'dashicons-awards',
                26
            );

            add_submenu_page(
                self::MENU_SLUG,
                'Operação de Jogos',
                'Operação de Jogos',
                'manage_options',
                self::MENU_SLUG,
                [__CLASS__, 'render_operacao_jogos']
            );
        }

        private static function conectar() {
            if (!class_exists('Mengao360_Bolao_DB')) {
                error_log('Mega Bolão 360 Admin: classe Mengao360_Bolao_DB não carregada.');
                return null;
            }

            return Mengao360_Bolao_DB::conectar();
        }

        private static function agora_sql_brasilia() {
            return "DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR)";
        }

        private static function get_param($key, $default = '') {
            return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : $default;
        }

        private static function get_post($key, $default = '') {
            return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : $default;
        }

        private static function redirect_com_notice($type, $message, $args = []) {
            $url = add_query_arg(
                array_merge(
                    [
                        'page' => self::MENU_SLUG,
                        'm360_notice_type' => $type,
                        'm360_notice' => rawurlencode($message),
                    ],
                    $args
                ),
                admin_url('admin.php')
            );

            wp_safe_redirect($url);
            exit;
        }

        public static function render_operacao_jogos() {
            if (!current_user_can('manage_options')) {
                wp_die('Sem permissão para acessar esta página.');
            }

            $pdo = self::conectar();
            if (!$pdo) {
                echo '<div class="wrap"><h1>Mega Bolão 360</h1><div class="notice notice-error"><p>Não foi possível conectar ao DW Esportivo.</p></div></div>';
                return;
            }

            $competicoes = self::buscar_competicoes($pdo);
            $competicao_id = (int) self::get_param('bolao_competicao_id', !empty($competicoes) ? $competicoes[0]['bolao_competicao_id'] : 1);
            $data_jogo = self::get_param('data_jogo', self::buscar_data_padrao($pdo, $competicao_id));
            $status_resultados = self::buscar_status_resultado($pdo);
            $jogos = self::buscar_jogos_operacao($pdo, $competicao_id, $data_jogo);
            $resumo_conciliacao = self::buscar_resumo_conciliacao($pdo, $competicao_id);
            $alertas_conciliacao = self::buscar_alertas_conciliacao($pdo, $competicao_id);
            $alertas_por_jogo = self::buscar_alertas_por_jogo($pdo, $competicao_id, $data_jogo);

            self::render_css_inline();

            echo '<div class="wrap m360-admin-bolao">';
            echo '<h1>Operação de Jogos — Mega Bolão 360</h1>';

            self::render_notice();

            echo '<form method="get" class="m360-admin-filtros">';
            echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG) . '">';

            echo '<label><strong>Competição</strong><br>';
            echo '<select name="bolao_competicao_id">';
            foreach ($competicoes as $competicao) {
                printf(
                    '<option value="%d" %s>%s — %s</option>',
                    (int) $competicao['bolao_competicao_id'],
                    selected($competicao_id, (int) $competicao['bolao_competicao_id'], false),
                    esc_html($competicao['titulo']),
                    esc_html($competicao['temporada'])
                );
            }
            echo '</select></label>';

            echo '<label><strong>Data</strong><br>';
            echo '<input type="date" name="data_jogo" value="' . esc_attr($data_jogo) . '">';
            echo '</label>';

            echo '<button type="submit" class="button button-primary">Filtrar</button>';
            echo '</form>';

            echo '<div class="m360-admin-resumo">';
            echo '<span><strong>' . count($jogos) . '</strong> jogos encontrados</span>';
            echo '<span>Horário operacional: Brasília</span>';
            echo '<span>Datas em SQL: <code>DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR)</code></span>';
            echo '</div>';

            self::render_conciliacao($resumo_conciliacao, $alertas_conciliacao, $competicao_id, $data_jogo);

            if (empty($jogos)) {
                echo '<div class="notice notice-warning inline"><p>Nenhum jogo encontrado para os filtros selecionados.</p></div>';
                echo '</div>';
                return;
            }

            echo '<div class="m360-admin-jogos">';
            foreach ($jogos as $jogo) {
                $jogo_id_loop = (int) $jogo['jogo_id'];
                $alerta_jogo = isset($alertas_por_jogo[$jogo_id_loop]) ? $alertas_por_jogo[$jogo_id_loop] : null;
                self::render_card_jogo($jogo, $status_resultados, $competicao_id, $data_jogo, $alerta_jogo);
            }
            echo '</div>';

            echo '</div>';
        }

        private static function render_notice() {
            if (empty($_GET['m360_notice'])) {
                return;
            }

            $type = self::get_param('m360_notice_type', 'success');
            $message = rawurldecode(self::get_param('m360_notice', ''));

            $class = $type === 'error' ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }

        private static function render_card_jogo($jogo, $status_resultados, $competicao_id, $data_jogo_filtro, $alerta_jogo = null) {
            $jogo_id = (int) $jogo['jogo_id'];
            $status_bolao_id = !empty($jogo['status_resultado_id']) ? (int) $jogo['status_resultado_id'] : 1;
            $placar_mandante = $jogo['resultado_mandante'] !== null ? $jogo['resultado_mandante'] : $jogo['placar_mandante'];
            $placar_visitante = $jogo['resultado_visitante'] !== null ? $jogo['resultado_visitante'] : $jogo['placar_visitante'];

            echo '<div class="m360-admin-jogo-card">';
            echo '<div class="m360-admin-jogo-topo">';
            echo '<div>';
            echo '<h2>' . esc_html($jogo['mandante_nome']) . ' x ' . esc_html($jogo['visitante_nome']) . '</h2>';
            echo '<p><strong>' . esc_html($jogo['hora_jogo']) . '</strong> · Jogo ID: <code>' . esc_html($jogo_id) . '</code></p>';
            echo '</div>';
            echo '<div class="m360-admin-badges">';
            echo '<span class="badge">API: ' . esc_html($jogo['status_jogo']) . '</span>';
            echo '<span class="badge badge-bolao">Manual: ' . esc_html($jogo['status_resultado_codigo'] ?: 'PENDENTE') . '</span>';
            if (!empty($alerta_jogo)) {
                $classe_alerta = self::classe_severidade($alerta_jogo['severidade']);
                echo '<span class="badge badge-alerta ' . esc_attr($classe_alerta) . '">' . esc_html(self::rotulo_tipo_evento($alerta_jogo['tipo_evento'])) . '</span>';
            }
            echo '</div>';
            echo '</div>';

            echo '<div class="m360-admin-grid">';
            echo '<div class="m360-admin-box"><strong>Resultado API/DW</strong><br>' . esc_html(self::formatar_placar($jogo['placar_mandante'], $jogo['placar_visitante'])) . '</div>';
            echo '<div class="m360-admin-box"><strong>Resultado Manual</strong><br>' . esc_html(self::formatar_placar($jogo['resultado_mandante'], $jogo['resultado_visitante'])) . '</div>';
            echo '<div class="m360-admin-box"><strong>Apuração</strong><br>' . (int) $jogo['qtd_pontuacoes'] . ' pontuações</div>';
            echo '<div class="m360-admin-box"><strong>Notificações</strong><br>Pendentes: ' . (int) $jogo['notif_pendente'] . ' · Enviadas: ' . (int) $jogo['notif_enviado'] . ' · Erros: ' . (int) $jogo['notif_erro'] . '</div>';
            echo '</div>';

            if (!empty($alerta_jogo)) {
                $classe_alerta = self::classe_severidade($alerta_jogo['severidade']);
                echo '<div class="m360-admin-alerta-jogo ' . esc_attr($classe_alerta) . '">';
                echo '<strong>' . esc_html(self::rotulo_tipo_evento($alerta_jogo['tipo_evento'])) . '</strong> — ';
                echo esc_html($alerta_jogo['mensagem']);
                echo ' <span>Último registro: ' . esc_html($alerta_jogo['dth_evento']) . '</span>';
                echo '</div>';
            }

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="m360-admin-form">';
            wp_nonce_field('m360_bolao_admin_action_' . $jogo_id);
            echo '<input type="hidden" name="action" value="m360_bolao_admin_action">';
            echo '<input type="hidden" name="acao_bolao" value="salvar_resultado">';
            echo '<input type="hidden" name="jogo_id" value="' . esc_attr($jogo_id) . '">';
            echo '<input type="hidden" name="bolao_competicao_id" value="' . esc_attr($competicao_id) . '">';
            echo '<input type="hidden" name="data_jogo_filtro" value="' . esc_attr($data_jogo_filtro) . '">';

            echo '<div class="m360-admin-form-row">';
            echo '<label>Placar mandante<br><input type="number" min="0" max="99" name="placar_mandante" value="' . esc_attr($placar_mandante !== null ? $placar_mandante : '') . '"></label>';
            echo '<label>Placar visitante<br><input type="number" min="0" max="99" name="placar_visitante" value="' . esc_attr($placar_visitante !== null ? $placar_visitante : '') . '"></label>';
            echo '<label>Status<br><select name="status_resultado_id">';
            foreach ($status_resultados as $status) {
                printf(
                    '<option value="%d" %s>%s</option>',
                    (int) $status['status_resultado_id'],
                    selected($status_bolao_id, (int) $status['status_resultado_id'], false),
                    esc_html($status['nome'])
                );
            }
            echo '</select></label>';
            echo '<label class="m360-admin-obs">Observação<br><input type="text" name="observacao" value="' . esc_attr($jogo['observacao'] ?: '') . '" placeholder="Resultado manual, conciliação, correção..."></label>';
            echo '<label class="m360-admin-obs">Fonte oficial<br><input type="url" name="fonte_oficial" required placeholder="https://fonte-oficial.example/jogo"></label>';
            echo '<label>Validade (horas)<br><input type="number" min="1" max="72" name="validade_horas" value="6" required></label>';
            echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Confirma salvar este resultado manual? Se o status permitir apuração, o jogo poderá ser recalculado.\');">Salvar Resultado</button>';
            echo '</div>';
            echo '</form>';

            $permite_acoes_processamento = !empty($jogo['status_permite_apuracao']) && (int) $jogo['status_permite_apuracao'] === 1;

            echo '<div class="m360-admin-acoes">';
            self::render_botao_acao('apurar_jogo', 'Apurar Jogo', $jogo_id, $competicao_id, $data_jogo_filtro, $permite_acoes_processamento);
            self::render_botao_acao('atualizar_rankings', 'Atualizar Rankings', $jogo_id, $competicao_id, $data_jogo_filtro, $permite_acoes_processamento);
            self::render_botao_acao('gerar_notificacoes', 'Gerar Notificações', $jogo_id, $competicao_id, $data_jogo_filtro, $permite_acoes_processamento);
            if (!$permite_acoes_processamento) {
                echo '<span class="m360-admin-alerta-parcial">Apuração, rankings e notificações ficam bloqueados até status final ou corrigido.</span>';
            }
            echo '</div>';

            echo '</div>';
        }

        private static function render_botao_acao($acao, $label, $jogo_id, $competicao_id, $data_jogo_filtro, $habilitado = true) {
            $confirmacoes = [
                'apurar_jogo' => 'Tem certeza que deseja apurar este jogo? Essa ação pode recalcular pontuação dos usuários.',
                'atualizar_rankings' => 'Tem certeza que deseja atualizar os rankings desta competição?',
                'gerar_notificacoes' => 'Tem certeza que deseja gerar notificações? Os usuários poderão receber e-mails.',
            ];
            $confirm = isset($confirmacoes[$acao]) ? ' onclick="return confirm(\'' . esc_js($confirmacoes[$acao]) . '\');"' : '';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('m360_bolao_admin_action_' . $jogo_id);
            echo '<input type="hidden" name="action" value="m360_bolao_admin_action">';
            echo '<input type="hidden" name="acao_bolao" value="' . esc_attr($acao) . '">';
            echo '<input type="hidden" name="jogo_id" value="' . esc_attr($jogo_id) . '">';
            echo '<input type="hidden" name="bolao_competicao_id" value="' . esc_attr($competicao_id) . '">';
            echo '<input type="hidden" name="data_jogo_filtro" value="' . esc_attr($data_jogo_filtro) . '">';
            $disabled = $habilitado ? '' : ' disabled="disabled"';
            $title = $habilitado ? '' : ' title="Disponível apenas para resultado final, final confirmado ou corrigido."';
            echo '<button type="submit" class="button"' . $disabled . $title . $confirm . '>' . esc_html($label) . '</button>';
            echo '</form>';
        }

        private static function render_conciliacao($resumo, $alertas, $competicao_id, $data_jogo_filtro) {
            $qtd_alta = isset($resumo['ALTA']) ? (int) $resumo['ALTA']['qtd'] : 0;
            $qtd_media = isset($resumo['MEDIA']) ? (int) $resumo['MEDIA']['qtd'] : 0;
            $qtd_info = isset($resumo['INFO']) ? (int) $resumo['INFO']['qtd'] : 0;
            $ultima = '-';

            foreach ($resumo as $linha) {
                if (!empty($linha['ultima_ocorrencia']) && ($ultima === '-' || $linha['ultima_ocorrencia'] > $ultima)) {
                    $ultima = $linha['ultima_ocorrencia'];
                }
            }

            echo '<section class="m360-admin-conciliacao">';
            echo '<div class="m360-admin-section-head">';
            echo '<div><h2>Conciliação API/DW x Manual</h2><p>Diagnóstico operacional gerado a partir dos logs da procedure de conciliação.</p></div>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('m360_bolao_admin_action_0');
            echo '<input type="hidden" name="action" value="m360_bolao_admin_action">';
            echo '<input type="hidden" name="acao_bolao" value="executar_conciliacao">';
            echo '<input type="hidden" name="jogo_id" value="0">';
            echo '<input type="hidden" name="bolao_competicao_id" value="' . esc_attr($competicao_id) . '">';
            echo '<input type="hidden" name="data_jogo_filtro" value="' . esc_attr($data_jogo_filtro) . '">';
            echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'Executar conciliação agora? A rotina apenas diagnostica e registra logs, sem alterar placares ou rankings.\');">Executar Conciliação</button>';
            echo '</form>';
            echo '</div>';

            echo '<div class="m360-admin-conciliacao-cards">';
            echo '<div class="m360-admin-card-kpi alta"><strong>' . $qtd_alta . '</strong><span>Críticos</span></div>';
            echo '<div class="m360-admin-card-kpi media"><strong>' . $qtd_media . '</strong><span>Médios</span></div>';
            echo '<div class="m360-admin-card-kpi info"><strong>' . $qtd_info . '</strong><span>Informativos</span></div>';
            echo '<div class="m360-admin-card-kpi ultima"><strong>' . esc_html(self::formatar_data_hora($ultima)) . '</strong><span>Última ocorrência</span></div>';
            echo '</div>';

            self::render_tabela_alertas_conciliacao($alertas);
            echo '</section>';
        }

        private static function render_tabela_alertas_conciliacao($alertas) {
            echo '<div class="m360-admin-alertas-wrap">';
            echo '<div class="m360-admin-alertas-head">';
            echo '<h3>Alertas de Conciliação</h3>';
            echo '<p>Alertas críticos e médios ficam em evidência. Confirmações informativas permanecem recolhidas para consulta.</p>';
            echo '</div>';

            if (empty($alertas)) {
                echo '<p class="m360-admin-sem-alertas">Nenhum alerta de conciliação registrado até o momento.</p>';
                echo '</div>';
                return;
            }

            $alertas_acao = [];
            $alertas_info = [];

            foreach ($alertas as $alerta) {
                if ($alerta['severidade'] === 'INFO') {
                    $alertas_info[] = $alerta;
                } else {
                    $alertas_acao[] = $alerta;
                }
            }

            if (empty($alertas_acao)) {
                echo '<div class="m360-admin-estado-ok">';
                echo '<strong>Sem alertas críticos ou médios.</strong>';
                echo '<span>A operação está saudável. Existem apenas confirmações informativas da conciliação.</span>';
                echo '</div>';
            } else {
                echo '<div class="m360-admin-estado-atencao">';
                echo '<strong>Atenção operacional</strong>';
                echo '<span>Existem alertas que exigem revisão no painel.</span>';
                echo '</div>';
                self::render_tabela_alertas_linhas($alertas_acao, 'Alertas que exigem ação');
            }

            if (!empty($alertas_info)) {
                echo '<details class="m360-admin-info-details">';
                echo '<summary>Ver confirmações informativas (' . count($alertas_info) . ')</summary>';
                self::render_tabela_alertas_linhas($alertas_info, 'Confirmações informativas');
                echo '</details>';
            }

            echo '</div>';
        }

        private static function render_tabela_alertas_linhas($alertas, $titulo = '') {
            if (!empty($titulo)) {
                echo '<h4 class="m360-admin-alertas-subtitulo">' . esc_html($titulo) . '</h4>';
            }

            echo '<div class="m360-admin-table-scroll">';
            echo '<table class="widefat striped m360-admin-alertas-table">';
            echo '<thead><tr>';
            echo '<th>Severidade</th><th>Jogo</th><th>Tipo</th><th>API/DW</th><th>Manual</th><th>Pontuações</th><th>Mensagem</th><th>Data</th>';
            echo '</tr></thead><tbody>';

            foreach ($alertas as $alerta) {
                $classe = self::classe_severidade($alerta['severidade']);
                echo '<tr class="m360-alerta-row ' . esc_attr($classe) . '">';
                echo '<td><span class="m360-admin-severidade ' . esc_attr($classe) . '">' . esc_html($alerta['severidade']) . '</span></td>';
                echo '<td><code>' . esc_html($alerta['jogo_id']) . '</code></td>';
                echo '<td>' . esc_html(self::rotulo_tipo_evento($alerta['tipo_evento'])) . '</td>';
                echo '<td><strong>' . esc_html(self::formatar_placar($alerta['placar_novo_mandante'], $alerta['placar_novo_visitante'])) . '</strong><br><small>' . esc_html($alerta['status_novo']) . '</small></td>';
                echo '<td><strong>' . esc_html(self::formatar_placar($alerta['placar_anterior_mandante'], $alerta['placar_anterior_visitante'])) . '</strong><br><small>' . esc_html($alerta['status_anterior']) . '</small></td>';
                echo '<td>' . (int) $alerta['qtd_pontuacoes'] . '</td>';
                echo '<td class="m360-admin-msg-alerta">' . esc_html($alerta['mensagem']) . '</td>';
                echo '<td>' . esc_html(self::formatar_data_hora($alerta['dth_evento'])) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
        }

        private static function classe_severidade($severidade) {
            if ($severidade === 'ALTA') {
                return 'sev-alta';
            }
            if ($severidade === 'MEDIA') {
                return 'sev-media';
            }
            return 'sev-info';
        }

        private static function rotulo_tipo_evento($tipo_evento) {
            $mapa = [
                'DIVERGENCIA_API_SEM_PLACAR' => 'API sem placar',
                'DIVERGENCIA_API_MANUAL' => 'Divergência API x Manual',
                'PARCIAL_EM_JOGO_FINALIZADO' => 'Parcial em jogo finalizado',
                'PONTUACAO_STATUS_INCOERENTE' => 'Pontuação incoerente',
                'RESULTADO_API_DIVERGE_FATO' => 'API diverge da fato',
                'API_CONFIRMOU_MANUAL' => 'API confirmou manual',
            ];

            return isset($mapa[$tipo_evento]) ? $mapa[$tipo_evento] : $tipo_evento;
        }

        private static function formatar_placar($mandante, $visitante) {
            if ($mandante === null || $visitante === null || $mandante === '' || $visitante === '') {
                return '-';
            }

            return (int) $mandante . ' x ' . (int) $visitante;
        }

        private static function formatar_data_hora($valor) {
            if (empty($valor) || $valor === '-') {
                return '-';
            }

            try {
                $dt = new DateTime($valor);
                return $dt->format('d/m/Y H:i');
            } catch (Exception $e) {
                return $valor;
            }
        }

        private static function buscar_competicoes($pdo) {
            try {
                $sql = "
                    SELECT
                        bolao_competicao_id,
                        temporada,
                        slug_bolao,
                        titulo
                    FROM bolao_competicoes
                    WHERE ind_ativo = 1
                    ORDER BY temporada DESC, titulo
                ";

                return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('Mega Bolão 360 Admin - erro ao buscar competições: ' . $e->getMessage());
                return [];
            }
        }

        private static function buscar_data_padrao($pdo, $bolao_competicao_id) {
            try {
                $sql = "
                    SELECT DATE(MIN(fj.data_jogo)) AS data_jogo
                    FROM bolao_competicoes bc
                    INNER JOIN fato_jogos fj
                        ON fj.competicao_id = bc.competicao_id
                    WHERE bc.bolao_competicao_id = ?
                      AND DATE(fj.data_jogo) >= DATE(DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR))
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$bolao_competicao_id]);
                $data = $stmt->fetchColumn();

                return $data ?: date('Y-m-d');
            } catch (Exception $e) {
                return date('Y-m-d');
            }
        }

        private static function buscar_status_resultado($pdo) {
            try {
                $sql = "
                    SELECT
                        status_resultado_id,
                        codigo,
                        nome,
                        permite_apuracao
                    FROM bolao_status_resultado
                    WHERE ind_ativo = 1
                    ORDER BY status_resultado_id
                ";

                return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('Mega Bolão 360 Admin - erro ao buscar status resultado: ' . $e->getMessage());
                return [];
            }
        }

        private static function buscar_resumo_conciliacao($pdo, $bolao_competicao_id) {
            try {
                $sql = "
                    SELECT
                        CASE
                            WHEN tipo_evento IN (
                                'DIVERGENCIA_API_SEM_PLACAR',
                                'DIVERGENCIA_API_MANUAL',
                                'PONTUACAO_STATUS_INCOERENTE',
                                'RESULTADO_API_DIVERGE_FATO'
                            ) THEN 'ALTA'
                            WHEN tipo_evento = 'PARCIAL_EM_JOGO_FINALIZADO' THEN 'MEDIA'
                            ELSE 'INFO'
                        END AS severidade,
                        COUNT(*) AS qtd,
                        MAX(dth_evento) AS ultima_ocorrencia
                    FROM bolao_log_operacional
                    WHERE bolao_competicao_id = ?
                      AND origem = 'CONCILIACAO_SP'
                    GROUP BY severidade
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$bolao_competicao_id]);
                $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $resumo = [];

                foreach ($linhas as $linha) {
                    $resumo[$linha['severidade']] = $linha;
                }

                return $resumo;
            } catch (Exception $e) {
                error_log('Mega Bolão 360 Admin - erro ao buscar resumo de conciliação: ' . $e->getMessage());
                return [];
            }
        }

        private static function buscar_alertas_conciliacao($pdo, $bolao_competicao_id, $limite = 30) {
            try {
                $sql = "
                    SELECT
                        log_operacional_id,
                        jogo_id,
                        tipo_evento,
                        origem,
                        status_anterior,
                        status_novo,
                        placar_anterior_mandante,
                        placar_anterior_visitante,
                        placar_novo_mandante,
                        placar_novo_visitante,
                        fonte_resultado_anterior,
                        fonte_resultado_nova,
                        qtd_pontuacoes,
                        mensagem,
                        dth_evento,
                        CASE
                            WHEN tipo_evento IN (
                                'DIVERGENCIA_API_SEM_PLACAR',
                                'DIVERGENCIA_API_MANUAL',
                                'PONTUACAO_STATUS_INCOERENTE',
                                'RESULTADO_API_DIVERGE_FATO'
                            ) THEN 'ALTA'
                            WHEN tipo_evento = 'PARCIAL_EM_JOGO_FINALIZADO' THEN 'MEDIA'
                            ELSE 'INFO'
                        END AS severidade
                    FROM bolao_log_operacional
                    WHERE bolao_competicao_id = ?
                      AND origem = 'CONCILIACAO_SP'
                    ORDER BY
                        CASE
                            WHEN tipo_evento IN (
                                'DIVERGENCIA_API_SEM_PLACAR',
                                'DIVERGENCIA_API_MANUAL',
                                'PONTUACAO_STATUS_INCOERENTE',
                                'RESULTADO_API_DIVERGE_FATO'
                            ) THEN 1
                            WHEN tipo_evento = 'PARCIAL_EM_JOGO_FINALIZADO' THEN 2
                            ELSE 3
                        END,
                        dth_evento DESC
                    LIMIT " . (int) $limite;

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$bolao_competicao_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('Mega Bolão 360 Admin - erro ao buscar alertas de conciliação: ' . $e->getMessage());
                return [];
            }
        }

        private static function buscar_alertas_por_jogo($pdo, $bolao_competicao_id, $data_jogo) {
            try {
                $sql = "
                    SELECT
                        lo.jogo_id,
                        lo.tipo_evento,
                        lo.mensagem,
                        lo.dth_evento,
                        CASE
                            WHEN lo.tipo_evento IN (
                                'DIVERGENCIA_API_SEM_PLACAR',
                                'DIVERGENCIA_API_MANUAL',
                                'PONTUACAO_STATUS_INCOERENTE',
                                'RESULTADO_API_DIVERGE_FATO'
                            ) THEN 'ALTA'
                            WHEN lo.tipo_evento = 'PARCIAL_EM_JOGO_FINALIZADO' THEN 'MEDIA'
                            ELSE 'INFO'
                        END AS severidade
                    FROM bolao_log_operacional lo
                    INNER JOIN (
                        SELECT
                            lo2.jogo_id,
                            MAX(lo2.log_operacional_id) AS ultimo_log_id
                        FROM bolao_log_operacional lo2
                        INNER JOIN fato_jogos fj2
                            ON fj2.id = lo2.jogo_id
                        INNER JOIN bolao_competicoes bc2
                            ON bc2.competicao_id = fj2.competicao_id
                           AND bc2.bolao_competicao_id = lo2.bolao_competicao_id
                        WHERE lo2.bolao_competicao_id = ?
                          AND lo2.origem = 'CONCILIACAO_SP'
                          AND DATE(fj2.data_jogo) = ?
                        GROUP BY lo2.jogo_id
                    ) ult
                        ON ult.ultimo_log_id = lo.log_operacional_id
                    ORDER BY lo.dth_evento DESC
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$bolao_competicao_id, $data_jogo]);
                $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $mapa = [];

                foreach ($linhas as $linha) {
                    $mapa[(int) $linha['jogo_id']] = $linha;
                }

                return $mapa;
            } catch (Exception $e) {
                error_log('Mega Bolão 360 Admin - erro ao buscar alertas por jogo: ' . $e->getMessage());
                return [];
            }
        }

        private static function buscar_jogos_operacao($pdo, $bolao_competicao_id, $data_jogo) {
            try {
                $sql = "
                    SELECT
                        fj.id AS jogo_id,
                        fj.data_jogo,
                        TIME_FORMAT(fj.data_jogo, '%H:%i') AS hora_jogo,
                        fj.status_jogo,
                        fj.placar_mandante,
                        fj.placar_visitante,
                        mandante.nome AS mandante_nome,
                        visitante.nome AS visitante_nome,

                        brp.status_resultado_id,
                        sr.codigo AS status_resultado_codigo,
                        sr.nome AS status_resultado_nome,
                        sr.permite_apuracao AS status_permite_apuracao,
                        brp.placar_mandante AS resultado_mandante,
                        brp.placar_visitante AS resultado_visitante,
                        brp.observacao,

                        COUNT(DISTINCT bp.pontuacao_id) AS qtd_pontuacoes,
                        SUM(CASE WHEN bn.status = 'PENDENTE' THEN 1 ELSE 0 END) AS notif_pendente,
                        SUM(CASE WHEN bn.status = 'ENVIADO' THEN 1 ELSE 0 END) AS notif_enviado,
                        SUM(CASE WHEN bn.status = 'ERRO' THEN 1 ELSE 0 END) AS notif_erro

                    FROM bolao_competicoes bc
                    INNER JOIN fato_jogos fj
                        ON fj.competicao_id = bc.competicao_id
                    INNER JOIN dim_times mandante
                        ON mandante.id = fj.mandante_id
                    INNER JOIN dim_times visitante
                        ON visitante.id = fj.visitante_id
                    LEFT JOIN bolao_resultados_partidas brp
                        ON brp.bolao_competicao_id = bc.bolao_competicao_id
                       AND brp.jogo_id = fj.id
                    LEFT JOIN bolao_status_resultado sr
                        ON sr.status_resultado_id = brp.status_resultado_id
                    LEFT JOIN bolao_pontuacao bp
                        ON bp.bolao_competicao_id = bc.bolao_competicao_id
                       AND bp.jogo_id = fj.id
                    LEFT JOIN bolao_notificacoes bn
                        ON bn.bolao_competicao_id = bc.bolao_competicao_id
                       AND bn.jogo_id = fj.id
                       AND bn.canal = 'EMAIL'

                    WHERE bc.bolao_competicao_id = ?
                      AND DATE(fj.data_jogo) = ?

                    GROUP BY
                        fj.id,
                        fj.data_jogo,
                        fj.status_jogo,
                        fj.placar_mandante,
                        fj.placar_visitante,
                        mandante.nome,
                        visitante.nome,
                        brp.status_resultado_id,
                        sr.codigo,
                        sr.nome,
                        sr.permite_apuracao,
                        brp.placar_mandante,
                        brp.placar_visitante,
                        brp.observacao

                    ORDER BY fj.data_jogo ASC
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$bolao_competicao_id, $data_jogo]);

                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('Mega Bolão 360 Admin - erro ao buscar jogos: ' . $e->getMessage());
                return [];
            }
        }

        public static function processar_acao() {
            if (!current_user_can('manage_options')) {
                wp_die('Sem permissão para executar esta ação.');
            }

            $acao = self::get_post('acao_bolao');
            $jogo_id = (int) self::get_post('jogo_id');
            $competicao_id = (int) self::get_post('bolao_competicao_id');
            $data_jogo_filtro = self::get_post('data_jogo_filtro', date('Y-m-d'));

            if (!$competicao_id || ($acao !== 'executar_conciliacao' && !$jogo_id)) {
                self::redirect_com_notice('error', 'Parâmetros inválidos.');
            }

            check_admin_referer('m360_bolao_admin_action_' . $jogo_id);

            $pdo = self::conectar();
            if (!$pdo) {
                self::redirect_com_notice('error', 'Não foi possível conectar ao DW Esportivo.');
            }

            try {
                switch ($acao) {
                    case 'executar_conciliacao':
                        self::call_sp($pdo, 'sp_bolao_conciliar_resultados_api_manual', [$competicao_id]);
                        self::redirect_com_notice('success', 'Conciliação executada com sucesso.', self::args_retorno($competicao_id, $data_jogo_filtro));
                        break;

                    case 'salvar_resultado':
                        self::acao_salvar_resultado($pdo, $competicao_id, $jogo_id);
                        self::redirect_com_notice('success', 'Resultado salvo com sucesso.', self::args_retorno($competicao_id, $data_jogo_filtro));
                        break;

                    case 'apurar_jogo':
                        self::validar_resultado_permite_apuracao($pdo, $competicao_id, $jogo_id);
                        self::call_sp($pdo, 'sp_bolao_apurar_jogo', [$competicao_id, $jogo_id]);
                        self::redirect_com_notice('success', 'Jogo apurado com sucesso.', self::args_retorno($competicao_id, $data_jogo_filtro));
                        break;

                    case 'atualizar_rankings':
                        self::validar_resultado_permite_apuracao($pdo, $competicao_id, $jogo_id);
                        self::acao_atualizar_rankings($pdo, $competicao_id, $jogo_id);
                        self::redirect_com_notice('success', 'Rankings atualizados com sucesso.', self::args_retorno($competicao_id, $data_jogo_filtro));
                        break;

                    case 'gerar_notificacoes':
                        self::validar_resultado_permite_apuracao($pdo, $competicao_id, $jogo_id);
                        self::call_sp($pdo, 'sp_bolao_gerar_notificacoes_pos_jogo', [$competicao_id, $jogo_id]);
                        self::redirect_com_notice('success', 'Notificações geradas com sucesso.', self::args_retorno($competicao_id, $data_jogo_filtro));
                        break;

                    default:
                        self::redirect_com_notice('error', 'Ação não reconhecida.', self::args_retorno($competicao_id, $data_jogo_filtro));
                }
            } catch (Exception $e) {
                error_log('Mega Bolão 360 Admin - erro na ação ' . $acao . ': ' . $e->getMessage());
                self::redirect_com_notice('error', 'Erro: ' . $e->getMessage(), self::args_retorno($competicao_id, $data_jogo_filtro));
            }
        }

        private static function args_retorno($competicao_id, $data_jogo_filtro) {
            return [
                'bolao_competicao_id' => $competicao_id,
                'data_jogo' => $data_jogo_filtro,
            ];
        }

        private static function validar_resultado_permite_apuracao($pdo, $competicao_id, $jogo_id) {
            $sql = "
                SELECT sr.permite_apuracao
                FROM bolao_resultados_partidas brp
                INNER JOIN bolao_status_resultado sr
                    ON sr.status_resultado_id = brp.status_resultado_id
                WHERE brp.bolao_competicao_id = ?
                  AND brp.jogo_id = ?
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$competicao_id, $jogo_id]);
            $permite_apuracao = $stmt->fetchColumn();

            if ((int) $permite_apuracao !== 1) {
                throw new Exception('Ação bloqueada: o jogo ainda não possui resultado final permitido para apuração. Salve como Final confirmado ou Corrigido antes de apurar, atualizar rankings ou gerar notificações.');
            }
        }

        private static function acao_salvar_resultado($pdo, $competicao_id, $jogo_id) {
            $placar_mandante = self::get_post('placar_mandante');
            $placar_visitante = self::get_post('placar_visitante');
            $status_resultado_id = (int) self::get_post('status_resultado_id');
            $observacao = self::get_post('observacao', 'Resultado informado pelo painel operacional.');
            $fonte_oficial = esc_url_raw(self::get_post('fonte_oficial'));
            $validade_horas = min(72, max(1, (int) self::get_post('validade_horas', 6)));

            if (!preg_match('/^\d{1,2}$/', (string) $placar_mandante)
                || !preg_match('/^\d{1,2}$/', (string) $placar_visitante)) {
                throw new Exception('Informe placares válidos entre 0 e 99.');
            }

            if (!$fonte_oficial || mb_strlen($fonte_oficial) > 255
                || mb_strlen($observacao) < 10 || mb_strlen($observacao) > 500) {
                throw new Exception('Informe a fonte oficial e uma justificativa com pelo menos 10 caracteres.');
            }

            if (!class_exists('Mengao360_Bolao_Schema')
                || !Mengao360_Bolao_Schema::table_exists($pdo, 'bolao_resultados_overrides')) {
                throw new Exception('Migração C.1 pendente: overrides auditáveis ainda não estão disponíveis.');
            }

            $sql_status = "
                SELECT codigo, permite_apuracao
                FROM bolao_status_resultado
                WHERE status_resultado_id = ?
                LIMIT 1
            ";
            $stmt_status = $pdo->prepare($sql_status);
            $stmt_status->execute([$status_resultado_id]);
            $status = $stmt_status->fetch(PDO::FETCH_ASSOC);

            if (!$status) {
                throw new Exception('Status de resultado inválido.');
            }

            $permite_apuracao = (int) $status['permite_apuracao'] === 1;
            $fonte = 'ADMIN_MANUAL';
            $ind_recalcular = $permite_apuracao ? 1 : 0;

            $stmt_jogo = $pdo->prepare(
                'SELECT id, competicao_id, data_jogo, status_jogo, mandante_id, visitante_id,
                        placar_mandante, placar_visitante, updated_at
                 FROM fato_jogos
                 WHERE id = ?
                   AND competicao_id = (
                       SELECT competicao_id
                       FROM bolao_competicoes
                       WHERE bolao_competicao_id = ?
                       LIMIT 1
                   )
                 LIMIT 1'
            );
            $stmt_jogo->execute([$jogo_id, $competicao_id]);
            $estado_dw = $stmt_jogo->fetch(PDO::FETCH_ASSOC);

            if (!$estado_dw) {
                throw new Exception('Jogo não encontrado no DW para este bolão.');
            }

            $hash_estado_dw = hash(
                'sha256',
                wp_json_encode($estado_dw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $pdo->beginTransaction();

            try {
                $stmt_override = $pdo->prepare(
                    "UPDATE bolao_resultados_overrides
                     SET status = 'SUBSTITUIDO',
                         revogado_por_wp_user_id = ?,
                         dth_revogacao = NOW()
                     WHERE bolao_competicao_id = ?
                       AND jogo_id = ?
                       AND status = 'ATIVO'"
                );
                $stmt_override->execute([get_current_user_id(), $competicao_id, $jogo_id]);

                $stmt_override = $pdo->prepare(
                    "INSERT INTO bolao_resultados_overrides (
                        bolao_competicao_id,
                        jogo_id,
                        status,
                        placar_mandante,
                        placar_visitante,
                        fonte_oficial,
                        justificativa,
                        hash_estado_dw,
                        criado_por_wp_user_id,
                        dth_expiracao
                    ) VALUES (?, ?, 'ATIVO', ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))"
                );
                $stmt_override->execute([
                    $competicao_id,
                    $jogo_id,
                    (int) $placar_mandante,
                    (int) $placar_visitante,
                    $fonte_oficial,
                    $observacao,
                    $hash_estado_dw,
                    get_current_user_id(),
                    $validade_horas,
                ]);
                $override_id = (int) $pdo->lastInsertId();

                $stmt_audit = $pdo->prepare(
                    'INSERT INTO bolao_auditoria (
                        request_id,
                        evento,
                        entidade,
                        entidade_id,
                        bolao_competicao_id,
                        wp_user_id,
                        estado_anterior,
                        estado_novo,
                        contexto
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt_audit->execute([
                    wp_generate_uuid4(),
                    'RESULTADO_OVERRIDE_CRIADO',
                    'bolao_resultados_overrides',
                    (string) $override_id,
                    $competicao_id,
                    get_current_user_id(),
                    wp_json_encode($estado_dw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    wp_json_encode([
                        'placar_mandante' => (int) $placar_mandante,
                        'placar_visitante' => (int) $placar_visitante,
                        'fonte_oficial' => $fonte_oficial,
                        'validade_horas' => $validade_horas,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    wp_json_encode(['jogo_id' => $jogo_id], JSON_UNESCAPED_UNICODE),
                ]);

                $sql = "
                INSERT INTO bolao_resultados_partidas (
                    bolao_competicao_id,
                    jogo_id,
                    status_resultado_id,
                    fonte_resultado,
                    placar_mandante,
                    placar_visitante,
                    dth_resultado_confirmado,
                    observacao,
                    ind_recalcular
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR),
                    ?,
                    ?
                )
                ON DUPLICATE KEY UPDATE
                    status_resultado_id = VALUES(status_resultado_id),
                    fonte_resultado = VALUES(fonte_resultado),
                    placar_mandante = VALUES(placar_mandante),
                    placar_visitante = VALUES(placar_visitante),
                    dth_resultado_confirmado = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR),
                    observacao = VALUES(observacao),
                    ind_recalcular = VALUES(ind_recalcular)
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $competicao_id,
                    $jogo_id,
                    $status_resultado_id,
                    $fonte,
                    (int) $placar_mandante,
                    (int) $placar_visitante,
                    $observacao . ' | Fonte: ' . $fonte_oficial,
                    $ind_recalcular,
                ]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }

        private static function acao_atualizar_rankings($pdo, $competicao_id, $jogo_id) {
            $sql_data = "SELECT DATE(data_jogo) FROM fato_jogos WHERE id = ? LIMIT 1";
            $stmt = $pdo->prepare($sql_data);
            $stmt->execute([$jogo_id]);
            $data_jogo = $stmt->fetchColumn();

            if (!$data_jogo) {
                throw new Exception('Data do jogo não encontrada.');
            }

            self::call_sp($pdo, 'sp_bolao_atualizar_ranking_geral', [$competicao_id]);
            self::call_sp($pdo, 'sp_bolao_atualizar_ranking_liga', [$competicao_id]);
            self::call_sp($pdo, 'sp_bolao_atualizar_ranking_dia', [$competicao_id, $data_jogo]);
        }

        private static function call_sp($pdo, $nome_sp, $params = []) {
            $placeholders = implode(',', array_fill(0, count($params), '?'));
            $sql = "CALL {$nome_sp}({$placeholders})";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            do {
                $stmt->fetchAll(PDO::FETCH_ASSOC);
            } while ($stmt->nextRowset());

            $stmt->closeCursor();
        }

        private static function render_css_inline() {
            echo '<style>
                .m360-admin-bolao .m360-admin-filtros {
                    display: flex;
                    align-items: end;
                    gap: 14px;
                    padding: 16px;
                    margin: 16px 0;
                    background: #fff;
                    border: 1px solid #dcdcde;
                    border-radius: 10px;
                }
                .m360-admin-bolao select,
                .m360-admin-bolao input[type="date"],
                .m360-admin-bolao input[type="number"],
                .m360-admin-bolao input[type="text"] {
                    min-height: 34px;
                    min-width: 160px;
                }
                .m360-admin-resumo {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                    margin: 0 0 16px;
                }
                .m360-admin-resumo span {
                    background: #fff;
                    border: 1px solid #dcdcde;
                    border-radius: 999px;
                    padding: 8px 12px;
                }
                .m360-admin-jogo-card {
                    background: #fff;
                    border: 1px solid #dcdcde;
                    border-radius: 12px;
                    padding: 18px;
                    margin: 0 0 16px;
                    box-shadow: 0 6px 18px rgba(0,0,0,.04);
                }
                .m360-admin-jogo-topo {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                    margin-bottom: 14px;
                }
                .m360-admin-jogo-topo h2 {
                    margin: 0 0 4px;
                    font-size: 20px;
                }
                .m360-admin-badges {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                    justify-content: flex-end;
                }
                .m360-admin-badges .badge {
                    background: #f0f0f1;
                    border-radius: 999px;
                    padding: 6px 10px;
                    font-weight: 700;
                }
                .m360-admin-badges .badge-bolao {
                    background: #fee2e2;
                    color: #991b1b;
                }
                .m360-admin-grid {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 10px;
                    margin-bottom: 14px;
                }
                .m360-admin-box {
                    background: #f6f7f7;
                    border-radius: 10px;
                    padding: 12px;
                }
                .m360-admin-form-row {
                    display: flex;
                    align-items: end;
                    gap: 10px;
                    flex-wrap: wrap;
                    padding: 12px;
                    background: #f9fafb;
                    border-radius: 10px;
                    margin-bottom: 12px;
                }
                .m360-admin-obs {
                    flex: 1 1 280px;
                }
                .m360-admin-obs input {
                    width: 100%;
                }
                .m360-admin-acoes {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .m360-admin-acoes .button[disabled] {
                    opacity: .45;
                    cursor: not-allowed;
                }
                .m360-admin-alerta-parcial {
                    color: #92400e;
                    background: #fff7ed;
                    border: 1px solid #fed7aa;
                    border-radius: 999px;
                    padding: 7px 10px;
                    font-size: 12px;
                    font-weight: 700;
                }
                .m360-admin-conciliacao {
                    background: #fff;
                    border: 1px solid #dcdcde;
                    border-radius: 12px;
                    padding: 16px;
                    margin: 0 0 18px;
                    box-shadow: 0 6px 18px rgba(0,0,0,.04);
                }
                .m360-admin-section-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                    margin-bottom: 14px;
                }
                .m360-admin-section-head h2 {
                    margin: 0 0 4px;
                    font-size: 19px;
                }
                .m360-admin-section-head p {
                    margin: 0;
                    color: #646970;
                }
                .m360-admin-conciliacao-cards {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 10px;
                    margin-bottom: 16px;
                }
                .m360-admin-card-kpi {
                    background: #f6f7f7;
                    border: 1px solid #e5e7eb;
                    border-radius: 10px;
                    padding: 12px;
                }
                .m360-admin-card-kpi strong {
                    display: block;
                    font-size: 22px;
                    line-height: 1.2;
                }
                .m360-admin-card-kpi span {
                    color: #646970;
                    font-weight: 700;
                }
                .m360-admin-card-kpi.alta {
                    background: #fef2f2;
                    border-color: #fecaca;
                }
                .m360-admin-card-kpi.media {
                    background: #fff7ed;
                    border-color: #fed7aa;
                }
                .m360-admin-card-kpi.info {
                    background: #eff6ff;
                    border-color: #bfdbfe;
                }
                .m360-admin-alertas-wrap h3 {
                    margin: 8px 0 10px;
                }
                .m360-admin-alertas-table small {
                    color: #646970;
                }
                .m360-admin-severidade,
                .badge-alerta {
                    border-radius: 999px;
                    padding: 5px 9px;
                    font-size: 12px;
                    font-weight: 800;
                    display: inline-block;
                }
                .sev-alta {
                    color: #991b1b !important;
                    background: #fee2e2 !important;
                    border: 1px solid #fecaca;
                }
                .sev-media {
                    color: #92400e !important;
                    background: #ffedd5 !important;
                    border: 1px solid #fed7aa;
                }
                .sev-info {
                    color: #1d4ed8 !important;
                    background: #dbeafe !important;
                    border: 1px solid #bfdbfe;
                }
                .m360-admin-alerta-jogo {
                    border-radius: 10px;
                    padding: 10px 12px;
                    margin: 0 0 12px;
                    font-weight: 600;
                }
                .m360-admin-alerta-jogo span {
                    display: inline-block;
                    margin-left: 8px;
                    color: #646970;
                    font-weight: 500;
                }
                .m360-admin-sem-alertas {
                    margin: 0;
                    color: #646970;
                }

                .m360-admin-card-kpi.ultima strong {
                    font-size: 18px;
                    word-break: keep-all;
                }
                .m360-admin-alertas-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                    margin: 8px 0 10px;
                }
                .m360-admin-alertas-head h3 {
                    margin: 0;
                }
                .m360-admin-alertas-head p {
                    margin: 2px 0 0;
                    color: #646970;
                }
                .m360-admin-estado-ok,
                .m360-admin-estado-atencao {
                    display: flex;
                    flex-direction: column;
                    gap: 3px;
                    border-radius: 12px;
                    padding: 12px 14px;
                    margin: 0 0 12px;
                    border: 1px solid;
                }
                .m360-admin-estado-ok {
                    color: #14532d;
                    background: #f0fdf4;
                    border-color: #bbf7d0;
                }
                .m360-admin-estado-atencao {
                    color: #7f1d1d;
                    background: #fef2f2;
                    border-color: #fecaca;
                }
                .m360-admin-estado-ok span,
                .m360-admin-estado-atencao span {
                    color: inherit;
                    opacity: .82;
                }
                .m360-admin-info-details {
                    border: 1px solid #dbeafe;
                    background: #f8fbff;
                    border-radius: 12px;
                    padding: 0;
                    overflow: hidden;
                }
                .m360-admin-info-details summary {
                    cursor: pointer;
                    padding: 12px 14px;
                    font-weight: 800;
                    color: #1d4ed8;
                    list-style-position: inside;
                }
                .m360-admin-info-details[open] summary {
                    border-bottom: 1px solid #dbeafe;
                    background: #eff6ff;
                }
                .m360-admin-alertas-subtitulo {
                    margin: 12px 0 8px;
                    font-size: 14px;
                    color: #1d2327;
                }
                .m360-admin-table-scroll {
                    overflow-x: auto;
                    border: 1px solid #dcdcde;
                    border-radius: 10px;
                    background: #fff;
                }
                .m360-admin-table-scroll .widefat {
                    border: 0;
                    min-width: 980px;
                }
                .m360-admin-msg-alerta {
                    max-width: 420px;
                }
                .m360-alerta-row.sev-alta td {
                    background: #fff7f7;
                }
                .m360-alerta-row.sev-media td {
                    background: #fffaf4;
                }
                @media (max-width: 960px) {
                    .m360-admin-grid,
                    .m360-admin-conciliacao-cards {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }
                    .m360-admin-jogo-topo,
                    .m360-admin-section-head,
                    .m360-admin-filtros {
                        flex-direction: column;
                        align-items: stretch;
                    }
                }
                @media (max-width: 600px) {
                    .m360-admin-grid,
                    .m360-admin-conciliacao-cards {
                        grid-template-columns: 1fr;
                    }
                }
            </style>';
        }
    }

    Mengao360_Bolao_Admin::init();
}
