<?php

if (!defined('ABSPATH')) {
    exit;
}

class Mengao360_Bolao_Shortcodes {

    public static function init() {
        add_shortcode('bolao_mengao', [__CLASS__, 'render_bolao']);
    }

    public static function render_bolao($atts) {
        $atts = shortcode_atts([
            'bolao' => '',
            'competicao' => 'fifa-world-cup',
            'idioma' => '',
        ], $atts, 'bolao_mengao');

        // ============================================================
        // 1. Parâmetros principais do shortcode e usuário WordPress
        // ============================================================
        $bolao_slug = sanitize_title($atts['bolao']);
        $competicao_slug = sanitize_title($atts['competicao']);
        $bolao_competicao_id = 0;
        $minutos_bloqueio_palpite = 10;
        $bolao_aberto = false;
        $bolao_estado_operacional = 'RASCUNHO';
        $usuario_logado = is_user_logged_in();
        $usuario_atual = wp_get_current_user();

        // ============================================================
        // 1.1. Idioma do Bolão
        //      Ordem de prioridade:
        //      - atributo idioma no shortcode;
        //      - helper global do plugin m360_bolao_get_lang();
        //      - parâmetro ?lang= da URL;
        //      - fallback pt-BR.
        // ============================================================
        $idiomas_permitidos = ['pt-BR', 'en-US', 'es-ES'];
        $idioma_bolao = '';

        if (!empty($atts['idioma'])) {
            $idioma_bolao = sanitize_text_field($atts['idioma']);
        } elseif (function_exists('m360_bolao_get_lang')) {
            $idioma_bolao = m360_bolao_get_lang();
        } elseif (!empty($_GET['lang'])) {
            $idioma_bolao = sanitize_text_field(wp_unslash($_GET['lang']));
        }

        if (!in_array($idioma_bolao, $idiomas_permitidos, true)) {
            $idioma_bolao = 'pt-BR';
        }

        $m360_bolao_lang = $idioma_bolao;
        $context_messages = [
            'pt-BR' => [
                'unavailable' => 'O bolão está temporariamente indisponível.',
                'not_found' => 'Bolão não encontrado ou inativo.',
                'ambiguous' => 'Há mais de um bolão ativo para esta competição. Informe o atributo bolao no shortcode.',
            ],
            'en-US' => [
                'unavailable' => 'The prediction pool is temporarily unavailable.',
                'not_found' => 'Prediction pool not found or inactive.',
                'ambiguous' => 'There is more than one active pool for this competition. Set the bolao shortcode attribute.',
            ],
            'es-ES' => [
                'unavailable' => 'La quiniela no está disponible temporalmente.',
                'not_found' => 'Quiniela no encontrada o inactiva.',
                'ambiguous' => 'Hay más de una quiniela activa para esta competición. Define el atributo bolao del shortcode.',
            ],
        ];
        $context_text = $context_messages[$m360_bolao_lang] ?? $context_messages['pt-BR'];

        // ============================================================
        // 2. Variáveis padrão utilizadas pelo template
        // ============================================================
        $datas_jogos = [];
        $jogos = [];
        $palpites_usuario = [];

        $data_selecionada = '';
        $data_anterior = '';
        $data_proxima = '';

        // ============================================================
        // 2.1. Variáveis da Sprint 2: resumo do usuário e ranking geral
        //      - $resumo_usuario alimenta os cards de pontos/posição.
        //      - $ranking_geral alimenta o Top 10 público do bolão.
        // ============================================================
        $usuario_bolao_id = null;
        $resumo_usuario = null;
        $ranking_geral = [];
        $resumo_dashboard = null;

        // ============================================================
        // 2.2. Variáveis da Sprint 3: ligas privadas
        //      - $minhas_ligas alimenta o card "Minhas Ligas".
        //      - A criação/entrada em ligas será feita via AJAX.
        // ============================================================
        $minhas_ligas = [];

        // ============================================================
        // 3. Carrega datas, navegação, jogos, palpites e ranking no DW
        // ============================================================
        if (class_exists('Mengao360_Bolao_DB')) {
            $pdo = Mengao360_Bolao_DB::conectar();

            if (!$pdo || !class_exists('Mengao360_Bolao_Context')) {
                return '<div class="m360-bolao-aviso">' .
                    esc_html($context_text['unavailable']) .
                    '</div>';
            }

            try {
                $contexto_bolao = Mengao360_Bolao_Context::resolve(
                    $pdo,
                    $bolao_slug,
                    $competicao_slug
                );
            } catch (Throwable $e) {
                error_log('Bolão Mengão 360 - erro ao resolver contexto: ' . $e->getMessage());
                $contexto_bolao = new WP_Error(
                    'm360_bolao_contexto_indisponivel',
                    __('Não foi possível carregar este bolão.', 'mengao360-bolao')
                );
            }

            if (is_wp_error($contexto_bolao)) {
                $message_key = $contexto_bolao->get_error_code() === 'm360_bolao_contexto_ambiguo'
                    ? 'ambiguous'
                    : 'not_found';
                return '<div class="m360-bolao-aviso">' .
                    esc_html($context_text[$message_key]) .
                    '</div>';
            }

            $bolao_competicao_id = (int) $contexto_bolao->bolao_competicao_id;
            $bolao_slug = (string) $contexto_bolao->slug_bolao;
            $competicao_slug = (string) $contexto_bolao->competicao_slug;
            $minutos_bloqueio_palpite = (int) $contexto_bolao->janela_fechamento_minutos;
            $bolao_estado_operacional = strtoupper((string) $contexto_bolao->estado_operacional);
            $bolao_aberto = $bolao_estado_operacional === 'ABERTO';

            // ------------------------------------------------------------
            // 3.1. Carrega todas as datas com jogos da competição
            // ------------------------------------------------------------
            $datas_jogos = Mengao360_Bolao_DB::get_datas_jogos($competicao_slug);

            $data_selecionada = isset($_GET['data_jogo'])
                ? sanitize_text_field($_GET['data_jogo'])
                : '';

            if (empty($data_selecionada) && !empty($datas_jogos)) {
                $datas_array_inicial = array_map(function($item) {
                    return $item->data_jogo;
                }, $datas_jogos);

                $data_hoje_wp = current_time('Y-m-d');

                if (in_array($data_hoje_wp, $datas_array_inicial, true)) {
                    $data_selecionada = $data_hoje_wp;
                } else {
                    foreach ($datas_array_inicial as $data_disponivel) {
                        if ($data_disponivel >= $data_hoje_wp) {
                            $data_selecionada = $data_disponivel;
                            break;
                        }
                    }

                    if (empty($data_selecionada)) {
                        $data_selecionada = $datas_jogos[0]->data_jogo;
                    }
                }
            }

            // ------------------------------------------------------------
            // 3.2. Calcula data anterior e próxima data com jogos
            // ------------------------------------------------------------
            if (!empty($datas_jogos) && !empty($data_selecionada)) {
                $datas_array = array_map(function($item) {
                    return $item->data_jogo;
                }, $datas_jogos);

                $indice_atual = array_search($data_selecionada, $datas_array, true);

                if ($indice_atual !== false) {
                    if (isset($datas_array[$indice_atual - 1])) {
                        $data_anterior = $datas_array[$indice_atual - 1];
                    }

                    if (isset($datas_array[$indice_atual + 1])) {
                        $data_proxima = $datas_array[$indice_atual + 1];
                    }
                }
            }

            // ------------------------------------------------------------
            // 3.3. Carrega jogos da data selecionada
            // ------------------------------------------------------------
            if (!empty($data_selecionada)) {
                $jogos = Mengao360_Bolao_DB::get_jogos_por_data(
                    $competicao_slug,
                    $data_selecionada,
                    $bolao_competicao_id
                );
            }

            // ------------------------------------------------------------
            // 3.4. Identifica/cria o usuário do bolão, quando logado
            //      Essa etapa é reutilizada para:
            //      - buscar palpites salvos;
            //      - carregar resumo de pontos/posição.
            // ------------------------------------------------------------
            if (
                $usuario_logado
                && class_exists('Mengao360_Bolao_User')
            ) {
                $usuario_bolao_id = Mengao360_Bolao_User::get_or_create_usuario_bolao();
            }

            // ------------------------------------------------------------
            // 3.5. Carrega palpites já salvos do usuário para esta data
            //      Requer:
            //      - usuário logado;
            //      - usuário_bolao_id válido;
            //      - data selecionada;
            //      - função get_palpites_usuario_por_data() no DB.
            // ------------------------------------------------------------
            if (
                !empty($usuario_bolao_id)
                && !empty($data_selecionada)
                && method_exists('Mengao360_Bolao_DB', 'get_palpites_usuario_por_data')
            ) {
                $palpites_usuario = Mengao360_Bolao_DB::get_palpites_usuario_por_data(
                    $competicao_slug,
                    $usuario_bolao_id,
                    $data_selecionada,
                    $bolao_competicao_id
                );
            }

            // ------------------------------------------------------------
            // 3.6. Carrega resumo do usuário no ranking geral
            //      Exemplo esperado:
            //      - pontos_total;
            //      - posicao;
            //      - qtd_palpites;
            //      - qtd_placar_exato.
            // ------------------------------------------------------------
            if (
                !empty($usuario_bolao_id)
                && method_exists('Mengao360_Bolao_DB', 'get_resumo_usuario')
            ) {
                $resumo_usuario = Mengao360_Bolao_DB::get_resumo_usuario(
                    $competicao_slug,
                    $usuario_bolao_id,
                    $bolao_competicao_id
                );
            }


            // ------------------------------------------------------------
            // 3.6.1. Carrega resumo ampliado do dashboard
            //        Usado para exibir:
            //        - palpites enviados;
            //        - posição geral;
            //        - posição na liga;
            //        - posição no ranking do dia.
            // ------------------------------------------------------------
            if (
                !empty($usuario_bolao_id)
                && method_exists('Mengao360_Bolao_DB', 'get_resumo_dashboard_usuario')
            ) {
                $resumo_dashboard = Mengao360_Bolao_DB::get_resumo_dashboard_usuario(
                    $competicao_slug,
                    $usuario_bolao_id,
                    $data_selecionada,
                    $bolao_competicao_id
                );
            }

            // ------------------------------------------------------------
            // 3.7. Carrega ranking geral público do bolão
            //      Mesmo visitantes não logados poderão visualizar o Top 10.
            // ------------------------------------------------------------
            if (method_exists('Mengao360_Bolao_DB', 'get_ranking_geral')) {
                $ranking_geral = Mengao360_Bolao_DB::get_ranking_geral(
                    $competicao_slug,
                    10,
                    $bolao_competicao_id
                );
            }

            // ------------------------------------------------------------
            // 3.8. Carrega ligas privadas do usuário logado
            //      Requer:
            //      - usuário_bolao_id válido;
            //      - classe Mengao360_Bolao_Ligas carregada;
            //      - função get_minhas_ligas().
            // ------------------------------------------------------------
            if (
                !empty($usuario_bolao_id)
                && class_exists('Mengao360_Bolao_Ligas')
                && method_exists('Mengao360_Bolao_Ligas', 'get_minhas_ligas')
            ) {
                $minhas_ligas = Mengao360_Bolao_Ligas::get_minhas_ligas(
                    $competicao_slug,
                    $usuario_bolao_id,
                    $bolao_competicao_id
                );
            }
        }

        // ============================================================
        // 4. Renderiza o template
        // ============================================================
        ob_start();

        include MENGAO360_BOLAO_PATH . 'templates/bolao-home.php';

        return ob_get_clean();
    }
}
