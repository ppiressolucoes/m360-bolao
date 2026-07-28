<?php

if (!defined('ABSPATH')) {
    exit;
}

class Mengao360_Bolao_Ligas {


    /**
     * Resolve idioma para mensagens internas do módulo de ligas.
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
     * Tradução local mínima para retornos do módulo de ligas.
     */
    private static function t($chave) {
        $lang = self::get_lang();

        $dict = [
            'pt-BR' => [
                'dados_invalidos_criar_liga' => self::t('dados_invalidos_criar_liga'),
                'bolao_nao_encontrado' => self::t('bolao_nao_encontrado'),
                'tipo_liga_privada_nao_configurado' => self::t('tipo_liga_privada_nao_configurado'),
                'liga_criada' => self::t('liga_criada'),
                'erro_criar_liga' => self::t('erro_criar_liga'),
                'codigo_invalido' => self::t('codigo_invalido'),
                'liga_nao_encontrada' => self::t('liga_nao_encontrada'),
                'entrou_liga' => self::t('entrou_liga'),
                'erro_entrar_liga' => self::t('erro_entrar_liga'),
            ],
            'en-US' => [
                'dados_invalidos_criar_liga' => 'Invalid data to create league.',
                'bolao_nao_encontrado' => 'Pool not found for this competition.',
                'tipo_liga_privada_nao_configurado' => 'Private league type is not configured.',
                'liga_criada' => 'League created successfully!',
                'erro_criar_liga' => 'Error creating league.',
                'codigo_invalido' => 'Invalid code.',
                'liga_nao_encontrada' => 'League not found or inactive.',
                'entrou_liga' => 'You joined the league successfully!',
                'erro_entrar_liga' => 'Error joining league.',
            ],
            'es-ES' => [
                'dados_invalidos_criar_liga' => 'Datos inválidos para crear la liga.',
                'bolao_nao_encontrado' => 'Bolão no encontrado para esta competición.',
                'tipo_liga_privada_nao_configurado' => 'El tipo de liga privada no está configurado.',
                'liga_criada' => 'Liga creada con éxito.',
                'erro_criar_liga' => 'Error al crear la liga.',
                'codigo_invalido' => self::t('codigo_invalido'),
                'liga_nao_encontrada' => 'Liga no encontrada o inactiva.',
                'entrou_liga' => '¡Entraste en la liga con éxito!',
                'erro_entrar_liga' => 'Error al entrar en la liga.',
            ],
        ];

        return $dict[$lang][$chave] ?? $dict['pt-BR'][$chave] ?? $chave;
    }

    /**
     * Gera um código de convite simples e legível.
     * Exemplo: M360-A7K9
     */
    public static function gerar_codigo_convite() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = '';

        for ($i = 0; $i < 4; $i++) {
            $codigo .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return 'M360-' . $codigo;
    }

    /**
     * Busca o bolao_competicao_id a partir do slug da competição.
     */
    public static function get_bolao_competicao_id($competicao_slug) {
        $pdo = Mengao360_Bolao_DB::conectar();

        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT bc.bolao_competicao_id
                FROM bolao_competicoes bc
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                WHERE dc.slug = ?
                  AND bc.ind_ativo = 1
                LIMIT 1
            ");

            $stmt->execute([$competicao_slug]);
            $row = $stmt->fetch();

            return $row ? (int) $row->bolao_competicao_id : null;

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao buscar competição do bolão: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria uma liga privada e adiciona o criador como DONO.
     */
    public static function criar_liga($competicao_slug, $nome_liga, $usuario_bolao_id) {
        $pdo = Mengao360_Bolao_DB::conectar();

        if (!$pdo || empty($usuario_bolao_id) || empty($nome_liga)) {
            return [
                'sucesso' => false,
                'mensagem' => self::t('dados_invalidos_criar_liga')
            ];
        }

        try {
            $bolao_competicao_id = self::get_bolao_competicao_id($competicao_slug);

            if (!$bolao_competicao_id) {
                return [
                    'sucesso' => false,
                    'mensagem' => self::t('bolao_nao_encontrado')
                ];
            }

            $stmt = $pdo->prepare("
                SELECT tipo_liga_id
                FROM bolao_tipo_liga
                WHERE codigo = 'PRIVADA'
                  AND ind_ativo = 1
                LIMIT 1
            ");
            $stmt->execute();
            $tipo = $stmt->fetch();

            if (!$tipo) {
                return [
                    'sucesso' => false,
                    'mensagem' => self::t('tipo_liga_privada_nao_configurado')
                ];
            }

            $codigo_convite = self::gerar_codigo_convite();
            $slug_base = sanitize_title($nome_liga);
            $slug = $slug_base . '-' . strtolower(str_replace('M360-', '', $codigo_convite));

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO bolao_ligas (
                    bolao_competicao_id,
                    tipo_liga_id,
                    nome,
                    slug,
                    descricao,
                    codigo_convite,
                    criada_por_usuario_id,
                    limite_participantes,
                    status,
                    ind_ranking_publico,
                    ind_ativo,
                    dth_criacao
                ) VALUES (
                    ?, ?, ?, ?, NULL, ?, ?, NULL, 'ATIVA', 0, 1, NOW()
                )
            ");

            $stmt->execute([
                $bolao_competicao_id,
                (int) $tipo->tipo_liga_id,
                $nome_liga,
                $slug,
                $codigo_convite,
                $usuario_bolao_id
            ]);

            $liga_id = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO bolao_liga_participantes (
                    liga_id,
                    usuario_bolao_id,
                    papel,
                    status,
                    dth_entrada
                ) VALUES (
                    ?, ?, 'DONO', 'ATIVO', NOW()
                )
            ");

            $stmt->execute([
                $liga_id,
                $usuario_bolao_id
            ]);

            $pdo->commit();

            return [
                'sucesso' => true,
                'mensagem' => self::t('liga_criada'),
                'liga_id' => $liga_id,
                'codigo_convite' => $codigo_convite,
                'nome' => $nome_liga
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Bolão Mengão 360 - erro ao criar liga: ' . $e->getMessage());

            return [
                'sucesso' => false,
                'mensagem' => self::t('erro_criar_liga')
            ];
        }
    }

    /**
     * Lista ligas em que o usuário participa.
     */
    public static function get_minhas_ligas($competicao_slug, $usuario_bolao_id) {
        $pdo = Mengao360_Bolao_DB::conectar();

        if (!$pdo || empty($usuario_bolao_id)) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    bl.liga_id,
                    bl.nome,
                    bl.codigo_convite,
                    bl.slug,
                    bl.status,
                    blp.papel,
                    blp.dth_entrada
                FROM bolao_liga_participantes blp
                INNER JOIN bolao_ligas bl
                    ON bl.liga_id = blp.liga_id
                INNER JOIN bolao_competicoes bc
                    ON bc.bolao_competicao_id = bl.bolao_competicao_id
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                WHERE dc.slug = ?
                  AND blp.usuario_bolao_id = ?
                  AND blp.status = 'ATIVO'
                  AND bl.ind_ativo = 1
                ORDER BY bl.dth_criacao DESC
            ");

            $stmt->execute([
                $competicao_slug,
                $usuario_bolao_id
            ]);

            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao listar ligas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Entra em uma liga privada usando código de convite.
     */
    public static function entrar_liga_por_codigo($competicao_slug, $codigo_convite, $usuario_bolao_id) {
        $pdo = Mengao360_Bolao_DB::conectar();

        if (!$pdo || empty($usuario_bolao_id) || empty($codigo_convite)) {
            return [
                'sucesso' => false,
                'mensagem' => self::t('codigo_invalido')
            ];
        }

        try {
            $codigo_convite = strtoupper(trim($codigo_convite));

            $stmt = $pdo->prepare("
                SELECT
                    bl.liga_id,
                    bl.nome
                FROM bolao_ligas bl
                INNER JOIN bolao_competicoes bc
                    ON bc.bolao_competicao_id = bl.bolao_competicao_id
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                WHERE dc.slug = ?
                  AND bl.codigo_convite = ?
                  AND bl.status = 'ATIVA'
                  AND bl.ind_ativo = 1
                LIMIT 1
            ");

            $stmt->execute([
                $competicao_slug,
                $codigo_convite
            ]);

            $liga = $stmt->fetch();

            if (!$liga) {
                return [
                    'sucesso' => false,
                    'mensagem' => self::t('liga_nao_encontrada')
                ];
            }

            $stmt = $pdo->prepare("
                INSERT INTO bolao_liga_participantes (
                    liga_id,
                    usuario_bolao_id,
                    papel,
                    status,
                    dth_entrada
                ) VALUES (
                    ?, ?, 'PARTICIPANTE', 'ATIVO', NOW()
                )
                ON DUPLICATE KEY UPDATE
                    status = 'ATIVO',
                    dth_saida = NULL
            ");

            $stmt->execute([
                (int) $liga->liga_id,
                $usuario_bolao_id
            ]);

            return [
                'sucesso' => true,
                'mensagem' => self::t('entrou_liga'),
                'liga_id' => (int) $liga->liga_id,
                'nome' => $liga->nome
            ];

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao entrar na liga: ' . $e->getMessage());

            return [
                'sucesso' => false,
                'mensagem' => self::t('erro_entrar_liga')
            ];
        }
    }
}