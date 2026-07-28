<?php

if (!defined('ABSPATH')) {
    exit;
}

class Mengao360_Bolao_Ajax {

    private const PLACAR_MAXIMO_PALPITE = 20;

    /**
     * Registra todos os endpoints AJAX do plugin.
     */
    public static function init() {
        // Sprint 1/2 - Palpites
        add_action('wp_ajax_m360_salvar_palpite', [__CLASS__, 'salvar_palpite']);

        // Sprint 3 - Ligas privadas
        add_action('wp_ajax_m360_criar_liga', [__CLASS__, 'criar_liga']);
        add_action('wp_ajax_m360_entrar_liga', [__CLASS__, 'entrar_liga']);
    }

    /**
     * Resolve idioma para respostas AJAX do Bolão.
     */
    private static function get_lang() {
        $idiomas_permitidos = ['pt-BR', 'en-US', 'es-ES'];
        $lang = '';

        if (!empty($_POST['idioma'])) {
            $lang = sanitize_text_field(wp_unslash($_POST['idioma']));
        } elseif (!empty($_POST['lang'])) {
            $lang = sanitize_text_field(wp_unslash($_POST['lang']));
        } elseif (!empty($_GET['lang'])) {
            $lang = sanitize_text_field(wp_unslash($_GET['lang']));
        } elseif (function_exists('m360_bolao_get_lang')) {
            $lang = m360_bolao_get_lang();
        } elseif (function_exists('m360_get_lang')) {
            $lang = m360_get_lang();
        }

        if (!in_array($lang, $idiomas_permitidos, true)) {
            $lang = 'pt-BR';
        }

        return $lang;
    }

    /**
     * Tradução local mínima para respostas AJAX.
     */
    private static function t($chave, $vars = []) {
        $lang = self::get_lang();

        $dict = [
            'pt-BR' => [
                'precisa_logado_palpitar' => 'Você precisa estar logado para palpitar.',
                'dados_palpite_invalidos' => 'Dados do palpite inválidos.',
                'placares_invalidos' => 'Informe placares válidos entre 0 e 20.',
                'conexao_indisponivel' => 'Conexão com o DW indisponível.',
                'usuario_nao_identificado' => 'Não foi possível identificar o usuário do bolão.',
                'bolao_inativo' => 'Bolão não está ativo para esta competição.',
                'jogo_nao_localizado' => 'Jogo não localizado para esta competição.',
                'jogo_fechado' => 'Este jogo não está mais aberto para palpites.',
                'palpite_salvo' => 'Palpite salvo com sucesso!',
                'erro_salvar_palpite' => 'Erro ao salvar palpite.',

                'precisa_logado_criar_liga' => 'Você precisa estar logado para criar uma liga.',
                'modulo_ligas_indisponivel' => 'Módulo de ligas não está disponível.',
                'informe_nome_liga' => 'Informe o nome da liga.',
                'nome_liga_minimo' => 'O nome da liga deve ter pelo menos 3 caracteres.',
                'nome_liga_longo' => 'O nome da liga é muito longo.',
                'erro_criar_liga' => 'Erro ao criar liga.',
                'liga_criada' => 'Liga "{nome}" criada com sucesso! Código: {codigo}',

                'precisa_logado_entrar_liga' => 'Você precisa estar logado para entrar em uma liga.',
                'informe_codigo_liga' => 'Informe o código da liga.',
                'erro_entrar_liga' => 'Erro ao entrar na liga.',
                'entrou_liga' => 'Você entrou na liga com sucesso!',
            ],
            'en-US' => [
                'precisa_logado_palpitar' => 'You need to be logged in to make predictions.',
                'dados_palpite_invalidos' => 'Invalid prediction data.',
                'placares_invalidos' => 'Enter valid scores from 0 to 20.',
                'conexao_indisponivel' => 'DW connection unavailable.',
                'usuario_nao_identificado' => 'Could not identify the pool user.',
                'bolao_inativo' => 'The pool is not active for this competition.',
                'jogo_nao_localizado' => 'Match not found for this competition.',
                'jogo_fechado' => 'This match is no longer open for predictions.',
                'palpite_salvo' => 'Prediction saved successfully!',
                'erro_salvar_palpite' => 'Error saving prediction.',

                'precisa_logado_criar_liga' => 'You need to be logged in to create a league.',
                'modulo_ligas_indisponivel' => 'League module is not available.',
                'informe_nome_liga' => 'Enter the league name.',
                'nome_liga_minimo' => 'The league name must have at least 3 characters.',
                'nome_liga_longo' => 'The league name is too long.',
                'erro_criar_liga' => 'Error creating league.',
                'liga_criada' => 'League "{nome}" created successfully! Code: {codigo}',

                'precisa_logado_entrar_liga' => 'You need to be logged in to join a league.',
                'informe_codigo_liga' => 'Enter the league code.',
                'erro_entrar_liga' => 'Error joining league.',
                'entrou_liga' => 'You joined the league successfully!',
            ],
            'es-ES' => [
                'precisa_logado_palpitar' => 'Debes iniciar sesión para pronosticar.',
                'dados_palpite_invalidos' => 'Datos del pronóstico inválidos.',
                'placares_invalidos' => 'Introduce marcadores válidos entre 0 y 20.',
                'conexao_indisponivel' => 'Conexión con el DW no disponible.',
                'usuario_nao_identificado' => 'No fue posible identificar al usuario del bolão.',
                'bolao_inativo' => 'El bolão no está activo para esta competición.',
                'jogo_nao_localizado' => 'Partido no encontrado para esta competición.',
                'jogo_fechado' => 'Este partido ya no está abierto para pronósticos.',
                'palpite_salvo' => '¡Pronóstico guardado con éxito!',
                'erro_salvar_palpite' => 'Error al guardar el pronóstico.',

                'precisa_logado_criar_liga' => 'Debes iniciar sesión para crear una liga.',
                'modulo_ligas_indisponivel' => 'El módulo de ligas no está disponible.',
                'informe_nome_liga' => 'Introduce el nombre de la liga.',
                'nome_liga_minimo' => 'El nombre de la liga debe tener al menos 3 caracteres.',
                'nome_liga_longo' => 'El nombre de la liga es demasiado largo.',
                'erro_criar_liga' => 'Error al crear la liga.',
                'liga_criada' => 'Liga "{nome}" creada con éxito. Código: {codigo}',

                'precisa_logado_entrar_liga' => 'Debes iniciar sesión para entrar en una liga.',
                'informe_codigo_liga' => 'Introduce el código de la liga.',
                'erro_entrar_liga' => 'Error al entrar en la liga.',
                'entrou_liga' => '¡Entraste en la liga con éxito!',
            ],
        ];

        $texto = $dict[$lang][$chave] ?? $dict['pt-BR'][$chave] ?? $chave;

        foreach ($vars as $key => $value) {
            $texto = str_replace('{' . $key . '}', (string) $value, $texto);
        }

        return $texto;
    }

    /**
     * Valida placar informado pelo front sem conversões permissivas.
     */
    private static function validar_placar_post($campo) {
        if (!isset($_POST[$campo])) {
            return null;
        }

        $valor = trim((string) wp_unslash($_POST[$campo]));

        if ($valor === '' || !preg_match('/^\d{1,2}$/', $valor)) {
            return null;
        }

        $placar = (int) $valor;

        if ($placar < 0 || $placar > self::PLACAR_MAXIMO_PALPITE) {
            return null;
        }

        return $placar;
    }

    /**
     * Salva ou atualiza o palpite de um usuário para um jogo.
     * Mantém a regra: 1 usuário + 1 competição + 1 jogo = 1 palpite.
     */
    public static function salvar_palpite() {
        check_ajax_referer('m360_bolao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['mensagem' => self::t('precisa_logado_palpitar')]);
        }

        $jogo_id = isset($_POST['jogo_id']) ? absint($_POST['jogo_id']) : 0;
        $placar_mandante = self::validar_placar_post('placar_mandante');
        $placar_visitante = self::validar_placar_post('placar_visitante');
        $competicao_slug = isset($_POST['competicao_slug']) ? sanitize_text_field(wp_unslash($_POST['competicao_slug'])) : '';

        if ($jogo_id <= 0 || empty($competicao_slug)) {
            wp_send_json_error(['mensagem' => self::t('dados_palpite_invalidos')]);
        }

        if ($placar_mandante === null || $placar_visitante === null) {
            wp_send_json_error(['mensagem' => self::t('placares_invalidos')]);
        }

        $pdo = Mengao360_Bolao_DB::conectar();

        if (!$pdo) {
            wp_send_json_error(['mensagem' => self::t('conexao_indisponivel')]);
        }

        $usuario_bolao_id = Mengao360_Bolao_User::get_or_create_usuario_bolao();

        if (!$usuario_bolao_id) {
            wp_send_json_error(['mensagem' => self::t('usuario_nao_identificado')]);
        }

        try {
            // ------------------------------------------------------------
            // Busca bolão ativo e status ABERTO para o palpite.
            // ------------------------------------------------------------
            $stmt = $pdo->prepare("
                SELECT 
                    bc.bolao_competicao_id,
                    bsp.status_palpite_id
                FROM bolao_competicoes bc
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                INNER JOIN bolao_status_palpite bsp
                    ON bsp.codigo = 'ABERTO'
                WHERE dc.slug = ?
                  AND bc.ind_ativo = 1
                LIMIT 1
            ");
            $stmt->execute([$competicao_slug]);
            $bolao = $stmt->fetch();

            if (!$bolao) {
                wp_send_json_error(['mensagem' => self::t('bolao_inativo')]);
            }

            // ------------------------------------------------------------
            // Proteção server-side: bloqueia palpites 10 minutos antes do jogo.
            // ------------------------------------------------------------
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    data_jogo,
                    status_jogo,
                    mandante_id,
                    visitante_id
                FROM fato_jogos
                WHERE id = ?
                  AND competicao_id = (
                      SELECT competicao_id
                      FROM bolao_competicoes
                      WHERE bolao_competicao_id = ?
                      LIMIT 1
                  )
                  /*
                   * Defesa server-side: confrontos com placeholder da API
                   * nunca podem receber palpites, ainda que o endpoint AJAX
                   * seja chamado fora da interface do bolão.
                   */
                  AND mandante_id IS NOT NULL
                  AND visitante_id IS NOT NULL
                  AND mandante_id <> 9999
                  AND visitante_id <> 9999
                  AND mandante_id <> visitante_id
                LIMIT 1
            ");
            $stmt->execute([$jogo_id, $bolao->bolao_competicao_id]);
            $jogo = $stmt->fetch();

            if (!$jogo) {
                wp_send_json_error(['mensagem' => self::t('jogo_nao_localizado')]);
            }

            $status_jogo = strtoupper((string) ($jogo->status_jogo ?? ''));

            if (in_array($status_jogo, ['FINISHED', 'FINISH', 'FT', 'CANCELLED', 'CANCELED', 'POSTPONED', 'SUSPENDED'], true)) {
                wp_send_json_error(['mensagem' => self::t('jogo_fechado')]);
            }

            $timezone_brasilia = new DateTimeZone('America/Sao_Paulo');

            $data_jogo_dt = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                (string) $jogo->data_jogo,
                $timezone_brasilia
            );

            if (!$data_jogo_dt) {
                $data_jogo_dt = new DateTime((string) $jogo->data_jogo, $timezone_brasilia);
            }

            $timestamp_jogo = $data_jogo_dt->getTimestamp();
            $timestamp_bloqueio = $timestamp_jogo - (10 * MINUTE_IN_SECONDS);
            $timestamp_agora = (new DateTime('now', $timezone_brasilia))->getTimestamp();

            if ($timestamp_agora >= $timestamp_bloqueio) {
                wp_send_json_error(['mensagem' => self::t('jogo_fechado')]);
            }

            // ------------------------------------------------------------
            // Grava ou atualiza o palpite.
            // ------------------------------------------------------------
            $stmt = $pdo->prepare("
                INSERT INTO bolao_palpites (
                    bolao_competicao_id,
                    usuario_bolao_id,
                    jogo_id,
                    status_palpite_id,
                    placar_mandante,
                    placar_visitante,
                    dth_palpite
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    placar_mandante = VALUES(placar_mandante),
                    placar_visitante = VALUES(placar_visitante),
                    status_palpite_id = VALUES(status_palpite_id),
                    dth_ultima_alteracao = NOW()
            ");

            $stmt->execute([
                (int) $bolao->bolao_competicao_id,
                $usuario_bolao_id,
                $jogo_id,
                (int) $bolao->status_palpite_id,
                $placar_mandante,
                $placar_visitante
            ]);

            wp_send_json_success([
                'mensagem' => self::t('palpite_salvo')
            ]);

        } catch (Exception $e) {
            error_log('Meu Bolão 360 - erro salvar palpite: ' . $e->getMessage());
            wp_send_json_error(['mensagem' => self::t('erro_salvar_palpite')]);
        }
    }

    /**
     * Cria uma liga privada para a competição atual.
     */
    public static function criar_liga() {
        check_ajax_referer('m360_bolao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['mensagem' => self::t('precisa_logado_criar_liga')]);
        }

        if (!class_exists('Mengao360_Bolao_Ligas')) {
            wp_send_json_error(['mensagem' => self::t('modulo_ligas_indisponivel')]);
        }

        $competicao_slug = isset($_POST['competicao_slug']) ? sanitize_text_field(wp_unslash($_POST['competicao_slug'])) : '';
        $nome_liga = isset($_POST['nome_liga']) ? sanitize_text_field(wp_unslash($_POST['nome_liga'])) : '';

        $nome_liga = trim($nome_liga);

        if (empty($competicao_slug) || empty($nome_liga)) {
            wp_send_json_error(['mensagem' => self::t('informe_nome_liga')]);
        }

        if (mb_strlen($nome_liga) < 3) {
            wp_send_json_error(['mensagem' => self::t('nome_liga_minimo')]);
        }

        if (mb_strlen($nome_liga) > 150) {
            wp_send_json_error(['mensagem' => self::t('nome_liga_longo')]);
        }

        $usuario_bolao_id = Mengao360_Bolao_User::get_or_create_usuario_bolao();

        if (!$usuario_bolao_id) {
            wp_send_json_error(['mensagem' => self::t('usuario_nao_identificado')]);
        }

        $resultado = Mengao360_Bolao_Ligas::criar_liga(
            $competicao_slug,
            $nome_liga,
            $usuario_bolao_id
        );

        if (empty($resultado['sucesso'])) {
            wp_send_json_error([
                'mensagem' => self::t('erro_criar_liga')
            ]);
        }

        $link_convite = add_query_arg(
            [
                'codigo_liga' => $resultado['codigo_convite'],
                'lang' => self::get_lang(),
            ],
            site_url('/bolao-copa-do-mundo-fifa-2026/')
        );

        wp_send_json_success([
            'mensagem' => self::t('liga_criada', [
                'nome' => $resultado['nome'],
                'codigo' => $resultado['codigo_convite'],
            ]),
            'liga_id' => $resultado['liga_id'],
            'nome' => $resultado['nome'],
            'codigo_convite' => $resultado['codigo_convite'],
            'link_convite' => $link_convite,
        ]);
    }

    /**
     * Permite entrar em uma liga usando código de convite.
     */
    public static function entrar_liga() {
        check_ajax_referer('m360_bolao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['mensagem' => self::t('precisa_logado_entrar_liga')]);
        }

        if (!class_exists('Mengao360_Bolao_Ligas')) {
            wp_send_json_error(['mensagem' => self::t('modulo_ligas_indisponivel')]);
        }

        $competicao_slug = isset($_POST['competicao_slug']) ? sanitize_text_field(wp_unslash($_POST['competicao_slug'])) : '';
        $codigo_convite = isset($_POST['codigo_convite']) ? sanitize_text_field(wp_unslash($_POST['codigo_convite'])) : '';

        $codigo_convite = strtoupper(trim($codigo_convite));

        if (empty($competicao_slug) || empty($codigo_convite)) {
            wp_send_json_error(['mensagem' => self::t('informe_codigo_liga')]);
        }

        $usuario_bolao_id = Mengao360_Bolao_User::get_or_create_usuario_bolao();

        if (!$usuario_bolao_id) {
            wp_send_json_error(['mensagem' => self::t('usuario_nao_identificado')]);
        }

        $resultado = Mengao360_Bolao_Ligas::entrar_liga_por_codigo(
            $competicao_slug,
            $codigo_convite,
            $usuario_bolao_id
        );

        if (empty($resultado['sucesso'])) {
            wp_send_json_error([
                'mensagem' => self::t('erro_entrar_liga')
            ]);
        }

        wp_send_json_success([
            'mensagem' => $resultado['mensagem'] ?? self::t('entrou_liga'),
            'liga_id' => $resultado['liga_id'],
            'nome' => $resultado['nome'],
        ]);
    }
}
