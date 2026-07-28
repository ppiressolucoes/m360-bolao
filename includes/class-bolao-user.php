<?php

if (!defined('ABSPATH')) {
    exit;
}

class Mengao360_Bolao_User {

    public static function get_or_create_usuario_bolao() {
        if (!is_user_logged_in()) {
            return null;
        }

        $wp_user = wp_get_current_user();

        if (!$wp_user || empty($wp_user->ID)) {
            return null;
        }

        $pdo = Mengao360_Bolao_DB::conectar();

        if (!$pdo) {
            return null;
        }

        /*
         * Sprint 6.1 - Notificações
         * Coleta e sincroniza o e-mail do usuário autenticado no WordPress
         * para a tabela própria do bolão.
         *
         * Motivo:
         * - O WordPress continua responsável pela autenticação;
         * - O DW/Bolão passa a ter os dados necessários para notificações;
         * - Evita dependência direta da tabela wp_users nas procedures SQL.
         */
        $email = !empty($wp_user->user_email)
            ? sanitize_email($wp_user->user_email)
            : null;

        $nome_exibicao = $wp_user->display_name ?: $wp_user->user_login;

        $email_hash = !empty($email)
            ? hash('sha256', strtolower(trim($email)))
            : null;

        try {
            $stmt = $pdo->prepare("
                SELECT usuario_bolao_id
                FROM bolao_usuarios
                WHERE wordpress_user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$wp_user->ID]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                /*
                 * Usuário já existe no bolão:
                 * atualiza e-mail, hash e último login para manter o DW consistente.
                 */
                $stmt = $pdo->prepare("
                    UPDATE bolao_usuarios
                    SET
                        email = ?,
                        email_hash = ?,
                        nome_exibicao = COALESCE(NULLIF(nome_exibicao, ''), ?),
                        dth_ultimo_login = NOW(),
                        dth_atualizacao = NOW()
                    WHERE usuario_bolao_id = ?
                ");

                $stmt->execute([
                    $email,
                    $email_hash,
                    $nome_exibicao,
                    (int) $usuario->usuario_bolao_id
                ]);

                return (int) $usuario->usuario_bolao_id;
            }

            /*
             * Novo usuário do bolão:
             * grava o e-mail desde o primeiro acesso para permitir notificações.
             */
            $stmt = $pdo->prepare("
                INSERT INTO bolao_usuarios (
                    wordpress_user_id,
                    nome_exibicao,
                    email,
                    email_hash,
                    ind_aceita_email,
                    perfil,
                    status,
                    dth_cadastro,
                    dth_ultimo_login
                ) VALUES (
                    ?, ?, ?, ?, 1, 'PARTICIPANTE', 'ATIVO', NOW(), NOW()
                )
            ");

            $stmt->execute([
                $wp_user->ID,
                $nome_exibicao,
                $email,
                $email_hash
            ]);

            return (int) $pdo->lastInsertId();

        } catch (Exception $e) {
            error_log('Meu Bolão 360 - erro usuário: ' . $e->getMessage());
            return null;
        }
    }
}
