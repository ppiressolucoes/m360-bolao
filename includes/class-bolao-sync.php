<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sincronização idempotente do domínio do Bolão após atualização do DW/ETL.
 */
class Mengao360_Bolao_Sync {

    public static function run($pdo, $pool_id, $idempotency_key, $wp_user_id = 0) {
        $pool_id = (int) $pool_id;
        $idempotency_key = sanitize_key($idempotency_key);

        if ($pool_id <= 0 || $idempotency_key === '') {
            throw new InvalidArgumentException('Bolão e chave de idempotência são obrigatórios.');
        }

        $context = Mengao360_Bolao_Context::resolve($pdo, '', '', $pool_id);
        if (is_wp_error($context)) {
            throw new RuntimeException($context->get_error_message());
        }

        $state_hash = self::get_dw_state_hash($pdo, (int) $context->competicao_id);
        $stmt = $pdo->prepare(
            'SELECT status, hash_estado_dw, resumo
             FROM bolao_sincronizacoes
             WHERE bolao_competicao_id = ?
               AND chave_idempotencia = ?
             LIMIT 1'
        );
        $stmt->execute([$pool_id, $idempotency_key]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing
            && $existing['status'] === 'CONCLUIDO'
            && hash_equals((string) $existing['hash_estado_dw'], $state_hash)) {
            return [
                'skipped' => true,
                'hash' => $state_hash,
                'summary' => json_decode((string) $existing['resumo'], true),
            ];
        }

        $lock_name = 'm360_bolao_sync_' . $pool_id;
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$lock_name]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Já existe uma sincronização em andamento para este bolão.');
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO bolao_sincronizacoes
                    (bolao_competicao_id, chave_idempotencia, tipo, status, hash_estado_dw, dth_inicio)
                 VALUES (?, ?, 'POS_ETL', 'EXECUTANDO', ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    status = 'EXECUTANDO',
                    hash_estado_dw = VALUES(hash_estado_dw),
                    resumo = NULL,
                    dth_inicio = NOW(),
                    dth_fim = NULL"
            );
            $stmt->execute([$pool_id, $idempotency_key, $state_hash]);

            $stmt = $pdo->prepare('CALL sp_bolao_processar_jogos_finalizados_api(?)');
            $stmt->execute([$pool_id]);
            do {
                $stmt->fetchAll();
            } while ($stmt->nextRowset());
            $stmt->closeCursor();

            $reconciliation = self::reconcile_overrides($pdo, $pool_id, $wp_user_id);
            $summary = [
                'estado_dw' => $state_hash,
                'overrides_conciliados' => $reconciliation['matched'],
                'overrides_divergentes' => $reconciliation['divergent'],
                'overrides_expirados' => $reconciliation['expired'],
            ];

            $stmt = $pdo->prepare(
                "UPDATE bolao_sincronizacoes
                 SET status = 'CONCLUIDO',
                     resumo = ?,
                     dth_fim = NOW()
                 WHERE bolao_competicao_id = ?
                   AND chave_idempotencia = ?"
            );
            $stmt->execute([
                wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $pool_id,
                $idempotency_key,
            ]);

            return ['skipped' => false, 'hash' => $state_hash, 'summary' => $summary];
        } catch (Throwable $e) {
            $stmt = $pdo->prepare(
                "UPDATE bolao_sincronizacoes
                 SET status = 'ERRO', resumo = ?, dth_fim = NOW()
                 WHERE bolao_competicao_id = ? AND chave_idempotencia = ?"
            );
            $stmt->execute([
                wp_json_encode(['erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                $pool_id,
                $idempotency_key,
            ]);
            throw $e;
        } finally {
            $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lock_name]);
        }
    }

    private static function get_dw_state_hash($pdo, $competition_id) {
        $stmt = $pdo->prepare(
            'SELECT id, data_jogo, status_jogo, mandante_id, visitante_id,
                    placar_mandante, placar_visitante, updated_at
             FROM fato_jogos
             WHERE competicao_id = ?
             ORDER BY id'
        );
        $stmt->execute([(int) $competition_id]);

        return hash(
            'sha256',
            wp_json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private static function reconcile_overrides($pdo, $pool_id, $wp_user_id) {
        $result = ['matched' => 0, 'divergent' => 0, 'expired' => 0];
        $stmt = $pdo->prepare(
            "SELECT bro.override_id, bro.placar_mandante, bro.placar_visitante,
                    bro.dth_expiracao, fj.status_jogo,
                    fj.placar_mandante AS oficial_mandante,
                    fj.placar_visitante AS oficial_visitante
             FROM bolao_resultados_overrides bro
             INNER JOIN fato_jogos fj ON fj.id = bro.jogo_id
             WHERE bro.bolao_competicao_id = ?
               AND bro.status = 'ATIVO'"
        );
        $stmt->execute([$pool_id]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $override) {
            if (strtotime((string) $override['dth_expiracao']) <= time()) {
                self::finish_override($pdo, $override['override_id'], 'EXPIRADO', 'Validade encerrada.', $wp_user_id);
                $result['expired']++;
                continue;
            }

            $finished = in_array(strtoupper((string) $override['status_jogo']), ['FINISHED', 'FINISH', 'FT'], true);
            if (!$finished || $override['oficial_mandante'] === null || $override['oficial_visitante'] === null) {
                continue;
            }

            $matched = (int) $override['placar_mandante'] === (int) $override['oficial_mandante']
                && (int) $override['placar_visitante'] === (int) $override['oficial_visitante'];

            if ($matched) {
                self::finish_override($pdo, $override['override_id'], 'CONCILIADO', 'Resultado confirmado pelo DW/ETL.', $wp_user_id);
                $result['matched']++;
            } else {
                $stmt_divergence = $pdo->prepare(
                    "UPDATE bolao_resultados_overrides
                     SET resultado_conciliacao = ?
                     WHERE override_id = ?"
                );
                $stmt_divergence->execute([
                    'DIVERGENTE: resultado oficial difere do override; revisão obrigatória.',
                    (int) $override['override_id'],
                ]);
                $result['divergent']++;
            }
        }

        return $result;
    }

    private static function finish_override($pdo, $override_id, $status, $message, $wp_user_id) {
        $stmt = $pdo->prepare(
            'UPDATE bolao_resultados_overrides
             SET status = ?,
                 resultado_conciliacao = ?,
                 dth_conciliacao = NOW(),
                 revogado_por_wp_user_id = ?
             WHERE override_id = ?'
        );
        $stmt->execute([$status, $message, (int) $wp_user_id, (int) $override_id]);
    }
}
