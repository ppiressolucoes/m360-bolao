<?php

if (!defined('ABSPATH')) {
    exit;
}

class Mengao360_Bolao_DB {

    /**
     * Conexão reutilizando o snippet global já existente no WordPress.
     */
    public static function conectar() {
        if (!function_exists('conectar_dw_esportes_m360')) {
            error_log('Bolão Mengão 360: função conectar_dw_esportes_m360 não encontrada.');
            return null;
        }

        $pdo = conectar_dw_esportes_m360();

        if ($pdo instanceof PDO) {
            // Falhas de escrita jamais podem virar uma confirmação visual falsa.
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        return $pdo;
    }

    /**
     * Busca todas as datas com jogos da competição.
     */
    public static function get_datas_jogos($competicao_slug, $bolao_competicao_id = 0) {
        $pdo = self::conectar();

        if (!$pdo) {
            return [];
        }

        try {
            $sql = "
                SELECT DISTINCT DATE(fj.data_jogo) AS data_jogo
                FROM fato_jogos fj
                INNER JOIN dim_competicoes dc
                    ON dc.id = fj.competicao_id
                LEFT JOIN bolao_competicoes bc
                    ON bc.competicao_id = dc.id
                WHERE dc.slug = ?
                  AND NOT (
                      UPPER(TRIM(COALESCE(fj.rodada, ''))) = 'LAST_16'
                      AND (fj.mandante_id IS NULL OR fj.visitante_id IS NULL)
                  )
                  AND (? = 0 OR bc.bolao_competicao_id = ?)
                ORDER BY DATE(fj.data_jogo)
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $competicao_slug,
                (int) $bolao_competicao_id,
                (int) $bolao_competicao_id,
            ]);

            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao buscar datas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca jogos de uma data específica.
     * Inclui flag palpite_aberto:
     * 1 = ainda permite palpite
     * 0 = jogo iniciado/encerrado, bloquear palpite
     */
    public static function get_jogos_por_data($competicao_slug, $data_jogo, $bolao_competicao_id = 0) {
        $pdo = self::conectar();

        if (!$pdo) {
            return [];
        }

        try {
            $has_overrides = class_exists('Mengao360_Bolao_Schema')
                && Mengao360_Bolao_Schema::table_exists($pdo, 'bolao_resultados_overrides');
            $override_join = $has_overrides
                ? "LEFT JOIN bolao_resultados_overrides bro
                    ON bro.bolao_competicao_id = bc.bolao_competicao_id
                   AND bro.jogo_id = fj.id
                   AND bro.status = 'ATIVO'
                   AND bro.dth_expiracao > NOW()"
                : '';
            $result_join_guard = $has_overrides
                ? "AND (brp.fonte_resultado <> 'ADMIN_MANUAL' OR bro.override_id IS NOT NULL)"
                : '';
            $home_result = $has_overrides
                ? "CASE WHEN brp.fonte_resultado = 'ADMIN_MANUAL'
                        THEN bro.placar_mandante ELSE brp.placar_mandante END"
                : 'brp.placar_mandante';
            $away_result = $has_overrides
                ? "CASE WHEN brp.fonte_resultado = 'ADMIN_MANUAL'
                        THEN bro.placar_visitante ELSE brp.placar_visitante END"
                : 'brp.placar_visitante';

            $sql = "
                SELECT
                    fj.id AS jogo_id,
                    DATE(fj.data_jogo) AS data_jogo,
                    fj.data_jogo AS data_jogo_completa,
                    TIME_FORMAT(fj.data_jogo, '%H:%i') AS hora_jogo,
                    fj.rodada,
                    fj.estadio_nome,
                    fj.status_jogo,
                    fj.mandante_id,
                    fj.visitante_id,
                    COALESCE(tm.nome_popular, tm.nome) AS mandante,
                    tm.escudo_url AS mandante_bandeira_url,
                    COALESCE(tv.nome_popular, tv.nome) AS visitante,
                    tv.escudo_url AS visitante_bandeira_url,

                    /*
                     * Sprint 6 - Resultado no card da agenda.
                     * Traz o resultado já confirmado/apurado no bolão, quando existir,
                     * sem depender de atualização imediata da API na fato_jogos.
                     */
                    {$home_result} AS resultado_placar_mandante,
                    {$away_result} AS resultado_placar_visitante,
                    brp.fonte_resultado,
                    bsr.codigo AS resultado_status_codigo,
                    bsr.nome AS resultado_status_nome,
                    bsr.permite_apuracao AS resultado_permite_apuracao,

                    CASE
                        WHEN fj.data_jogo > NOW() THEN 1
                        ELSE 0
                    END AS palpite_aberto
                FROM fato_jogos fj
                INNER JOIN dim_competicoes dc
                    ON dc.id = fj.competicao_id
                LEFT JOIN bolao_competicoes bc
                    ON bc.competicao_id = dc.id
                {$override_join}
                LEFT JOIN bolao_resultados_partidas brp
                    ON brp.bolao_competicao_id = bc.bolao_competicao_id
                   AND brp.jogo_id = fj.id
                   {$result_join_guard}
                LEFT JOIN bolao_status_resultado bsr
                    ON bsr.status_resultado_id = brp.status_resultado_id
                LEFT JOIN dim_times tm
                    ON tm.id = fj.mandante_id
                LEFT JOIN dim_times tv
                    ON tv.id = fj.visitante_id
                WHERE dc.slug = ?
                  AND NOT (
                      UPPER(TRIM(COALESCE(fj.rodada, ''))) = 'LAST_16'
                      AND (fj.mandante_id IS NULL OR fj.visitante_id IS NULL)
                  )
                  AND DATE(fj.data_jogo) = ?
                  AND (? = 0 OR bc.bolao_competicao_id = ?)
                ORDER BY fj.data_jogo, fj.id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $competicao_slug,
                $data_jogo,
                (int) $bolao_competicao_id,
                (int) $bolao_competicao_id,
            ]);

            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao buscar jogos por data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca palpites já salvos pelo usuário na data exibida.
     */
    public static function get_palpites_usuario_por_data($competicao_slug, $usuario_bolao_id, $data_jogo, $bolao_competicao_id = 0) {
        $pdo = self::conectar();

        if (!$pdo || empty($usuario_bolao_id) || empty($data_jogo)) {
            return [];
        }

        try {
            $sql = "
                SELECT
                    bp.jogo_id,
                    bp.palpite_id,
                    bp.placar_mandante,
                    bp.placar_visitante,
                    bp.dth_palpite,
                    bp.dth_ultima_alteracao,

                    /*
                     * Sprint 6 - Pontuação do palpite na agenda.
                     * Disponível após CALL sp_bolao_apurar_jogo().
                     */
                    bpo.pontos_total,
                    bpo.ind_placar_exato,
                    bpo.ind_resultado_correto
                FROM bolao_palpites bp
                LEFT JOIN bolao_pontuacao bpo
                    ON bpo.palpite_id = bp.palpite_id
                INNER JOIN bolao_competicoes bc
                    ON bc.bolao_competicao_id = bp.bolao_competicao_id
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                INNER JOIN fato_jogos fj
                    ON fj.id = bp.jogo_id
                WHERE dc.slug = ?
                  AND bp.usuario_bolao_id = ?
                  AND DATE(fj.data_jogo) = ?
                  AND (? = 0 OR bp.bolao_competicao_id = ?)
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $competicao_slug,
                $usuario_bolao_id,
                $data_jogo,
                (int) $bolao_competicao_id,
                (int) $bolao_competicao_id,
            ]);

            $linhas = $stmt->fetchAll();
            $palpites = [];

            foreach ($linhas as $linha) {
                $palpites[(int) $linha->jogo_id] = $linha;
            }

            return $palpites;

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao buscar palpites do usuário: ' . $e->getMessage());
            return [];
        }
    }
    /**
     * Busca o resumo consolidado do usuário no ranking geral do bolão.
     *
     * Retorna valores padrão quando o usuário ainda não possui pontuação apurada.
     */
    public static function get_resumo_usuario($competicao_slug, $usuario_bolao_id, $bolao_competicao_id = 0) {
        $pdo = self::conectar();

        if (!$pdo || empty($competicao_slug) || empty($usuario_bolao_id)) {
            return (object) [
                'pontos_total' => 0,
                'posicao' => null,
                'qtd_palpites' => 0,
                'qtd_placar_exato' => 0,
                'qtd_resultado_correto' => 0,
            ];
        }

        try {
            $sql = "
                SELECT
                    br.posicao,
                    br.pontos_total,
                    br.qtd_palpites,
                    br.qtd_placar_exato,
                    br.qtd_resultado_correto
                FROM bolao_ranking br
                INNER JOIN bolao_tipo_ranking tr
                    ON tr.tipo_ranking_id = br.tipo_ranking_id
                INNER JOIN bolao_competicoes bc
                    ON bc.bolao_competicao_id = br.bolao_competicao_id
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                WHERE dc.slug = ?
                  AND br.usuario_bolao_id = ?
                  AND tr.codigo = 'GERAL'
                  AND br.liga_id IS NULL
                  AND (? = 0 OR br.bolao_competicao_id = ?)
                ORDER BY br.dth_atualizacao DESC
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $competicao_slug,
                $usuario_bolao_id,
                (int) $bolao_competicao_id,
                (int) $bolao_competicao_id,
            ]);

            $resumo = $stmt->fetch();

            if ($resumo) {
                return $resumo;
            }

            return (object) [
                'pontos_total' => 0,
                'posicao' => null,
                'qtd_palpites' => 0,
                'qtd_placar_exato' => 0,
                'qtd_resultado_correto' => 0,
            ];

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao buscar resumo do usuário: ' . $e->getMessage());

            return (object) [
                'pontos_total' => 0,
                'posicao' => null,
                'qtd_palpites' => 0,
                'qtd_placar_exato' => 0,
                'qtd_resultado_correto' => 0,
            ];
        }
    }

    /**
     * Busca o ranking geral público do bolão.
     *
     * Usado para exibir o Top 10 na página do bolão.
     */
    public static function get_ranking_geral($competicao_slug, $limite = 10, $bolao_competicao_id = 0) {
        $pdo = self::conectar();

        if (!$pdo || empty($competicao_slug)) {
            return [];
        }

        $limite = max(1, min((int) $limite, 50));

        try {
            $sql = "
                SELECT
                    br.posicao,
                    bu.nome_exibicao,
                    br.pontos_total,
                    br.qtd_palpites,
                    br.qtd_placar_exato,
                    br.qtd_resultado_correto
                FROM bolao_ranking br
                INNER JOIN bolao_usuarios bu
                    ON bu.usuario_bolao_id = br.usuario_bolao_id
                INNER JOIN bolao_tipo_ranking tr
                    ON tr.tipo_ranking_id = br.tipo_ranking_id
                INNER JOIN bolao_competicoes bc
                    ON bc.bolao_competicao_id = br.bolao_competicao_id
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                WHERE dc.slug = ?
                  AND tr.codigo = 'GERAL'
                  AND br.liga_id IS NULL
                  AND (? = 0 OR br.bolao_competicao_id = ?)
                ORDER BY
                    br.posicao ASC,
                    br.pontos_total DESC,
                    br.qtd_placar_exato DESC,
                    br.qtd_resultado_correto DESC,
                    bu.nome_exibicao ASC
                LIMIT {$limite}
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $competicao_slug,
                (int) $bolao_competicao_id,
                (int) $bolao_competicao_id,
            ]);

            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao buscar ranking geral: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Busca resumo ampliado do dashboard do usuário.
     *
     * Sprint 5.5:
     * - Mantém os cards superiores no padrão visual já validado;
     * - Exibe palpites enviados mesmo antes de qualquer jogo apurado;
     * - Busca posições nos rankings GERAL, LIGA e DIA quando já existirem.
     */
    public static function get_resumo_dashboard_usuario($competicao_slug, $usuario_bolao_id, $data_referencia = null, $bolao_competicao_id = 0) {
        $pdo = self::conectar();

        $resumo_padrao = (object) [
            'palpites_enviados' => 0,
            'posicao_geral' => null,
            'posicao_liga' => null,
            'posicao_dia' => null,
        ];

        if (!$pdo || empty($competicao_slug) || empty($usuario_bolao_id)) {
            return $resumo_padrao;
        }

        try {
            $data_referencia = !empty($data_referencia)
                ? $data_referencia
                : date('Y-m-d');

            /*
             * 1) Quantidade total de palpites enviados pelo usuário
             *    na competição atual, independentemente de apuração.
             */
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total
                FROM bolao_palpites bp
                INNER JOIN bolao_competicoes bc
                    ON bc.bolao_competicao_id = bp.bolao_competicao_id
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                WHERE dc.slug = ?
                  AND bp.usuario_bolao_id = ?
                  AND (? = 0 OR bp.bolao_competicao_id = ?)
            ");

            $stmt->execute([
                $competicao_slug,
                $usuario_bolao_id,
                (int) $bolao_competicao_id,
                (int) $bolao_competicao_id,
            ]);

            $palpites_enviados = (int) $stmt->fetchColumn();

            /*
             * 2) Posição no Ranking Geral.
             */
            $stmt = $pdo->prepare("
                SELECT br.posicao
                FROM bolao_ranking br
                INNER JOIN bolao_tipo_ranking tr
                    ON tr.tipo_ranking_id = br.tipo_ranking_id
                INNER JOIN bolao_competicoes bc
                    ON bc.bolao_competicao_id = br.bolao_competicao_id
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                WHERE dc.slug = ?
                  AND br.usuario_bolao_id = ?
                  AND tr.codigo = 'GERAL'
                  AND br.liga_id IS NULL
                  AND (? = 0 OR br.bolao_competicao_id = ?)
                ORDER BY br.dth_atualizacao DESC
                LIMIT 1
            ");

            $stmt->execute([
                $competicao_slug,
                $usuario_bolao_id,
                (int) $bolao_competicao_id,
                (int) $bolao_competicao_id,
            ]);

            $posicao_geral = $stmt->fetchColumn();
            $posicao_geral = $posicao_geral !== false ? (int) $posicao_geral : null;

            /*
             * 3) Posição no Ranking de Liga.
             *    Se o usuário participar de mais de uma liga, exibe a melhor posição.
             */
            $stmt = $pdo->prepare("
                SELECT MIN(br.posicao) AS posicao
                FROM bolao_ranking br
                INNER JOIN bolao_tipo_ranking tr
                    ON tr.tipo_ranking_id = br.tipo_ranking_id
                INNER JOIN bolao_competicoes bc
                    ON bc.bolao_competicao_id = br.bolao_competicao_id
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                WHERE dc.slug = ?
                  AND br.usuario_bolao_id = ?
                  AND tr.codigo = 'LIGA'
                  AND br.liga_id IS NOT NULL
                  AND (? = 0 OR br.bolao_competicao_id = ?)
            ");

            $stmt->execute([
                $competicao_slug,
                $usuario_bolao_id,
                (int) $bolao_competicao_id,
                (int) $bolao_competicao_id,
            ]);

            $posicao_liga = $stmt->fetchColumn();
            $posicao_liga = $posicao_liga !== false && $posicao_liga !== null ? (int) $posicao_liga : null;

            /*
             * 4) Posição no Ranking do Dia.
             *    Usa a data atualmente selecionada na agenda do bolão.
             */
            $stmt = $pdo->prepare("
                SELECT br.posicao
                FROM bolao_ranking br
                INNER JOIN bolao_tipo_ranking tr
                    ON tr.tipo_ranking_id = br.tipo_ranking_id
                INNER JOIN bolao_competicoes bc
                    ON bc.bolao_competicao_id = br.bolao_competicao_id
                INNER JOIN dim_competicoes dc
                    ON dc.id = bc.competicao_id
                WHERE dc.slug = ?
                  AND br.usuario_bolao_id = ?
                  AND tr.codigo = 'DIA'
                  AND br.liga_id IS NULL
                  AND br.rodada = ?
                  AND (? = 0 OR br.bolao_competicao_id = ?)
                ORDER BY br.dth_atualizacao DESC
                LIMIT 1
            ");

            $stmt->execute([
                $competicao_slug,
                $usuario_bolao_id,
                $data_referencia,
                (int) $bolao_competicao_id,
                (int) $bolao_competicao_id,
            ]);

            $posicao_dia = $stmt->fetchColumn();
            $posicao_dia = $posicao_dia !== false ? (int) $posicao_dia : null;

            return (object) [
                'palpites_enviados' => $palpites_enviados,
                'posicao_geral' => $posicao_geral,
                'posicao_liga' => $posicao_liga,
                'posicao_dia' => $posicao_dia,
            ];

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao buscar resumo ampliado do dashboard: ' . $e->getMessage());
            return $resumo_padrao;
        }
    }



    /**
     * Sprint 6.1 - Sincroniza o e-mail do usuário logado do WordPress
     * para a tabela bolao_usuarios.
     *
     * Objetivo:
     * - Evitar dependência direta da tabela wp_users nas procedures do DW;
     * - Permitir que bolao_notificacoes use bolao_usuarios.email;
     * - Manter o e-mail atualizado sempre que o participante acessar o bolão.
     *
     * Observação:
     * Este método deve ser chamado logo após:
     * Mengao360_Bolao_User::get_or_create_usuario_bolao()
     */
    public static function sincronizar_email_usuario_logado($usuario_bolao_id) {
        $pdo = self::conectar();

        if (!$pdo || empty($usuario_bolao_id) || !function_exists('wp_get_current_user')) {
            return false;
        }

        $usuario_wp = wp_get_current_user();

        if (!$usuario_wp || empty($usuario_wp->ID) || empty($usuario_wp->user_email)) {
            return false;
        }

        $email = sanitize_email($usuario_wp->user_email);

        if (empty($email) || !is_email($email)) {
            return false;
        }

        try {
            $sql = "
                UPDATE bolao_usuarios
                SET
                    email = ?,
                    email_hash = SHA2(LOWER(TRIM(?)), 256),
                    dth_ultimo_login = NOW(),
                    dth_atualizacao = NOW()
                WHERE usuario_bolao_id = ?
                  AND wordpress_user_id = ?
            ";

            $stmt = $pdo->prepare($sql);

            return $stmt->execute([
                $email,
                $email,
                (int) $usuario_bolao_id,
                (int) $usuario_wp->ID
            ]);

        } catch (Exception $e) {
            error_log('Bolão Mengão 360 - erro ao sincronizar e-mail do usuário: ' . $e->getMessage());
            return false;
        }
    }


}
