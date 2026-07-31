<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Diagnóstico conservador executado antes de qualquer abertura de bolão.
 */
class Mengao360_Bolao_Opening_Gate {

    public static function evaluate($pdo, $pool_id, $now = null) {
        $pool_id = (int) $pool_id;
        $checks = [];
        $timezone = new DateTimeZone('America/Sao_Paulo');
        $current = $now instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($now)->setTimezone($timezone)
            : new DateTimeImmutable('now', $timezone);

        $stmt = $pdo->prepare(
            'SELECT bc.bolao_competicao_id, bc.titulo, bc.competicao_id,
                    bc.temporada, bc.estado_operacional, bc.visibilidade, bc.ind_ativo,
                    bc.janela_fechamento_minutos, dc.ativo AS competicao_ativa,
                    dcm.nome_modelo, dcm.slug_modelo, dcm.tipo_mata_mata,
                    dcm.ativo AS modelo_ativo
             FROM bolao_competicoes bc
             INNER JOIN dim_competicoes dc ON dc.id = bc.competicao_id
             LEFT JOIN dim_competicao_modelo dcm ON dcm.id = dc.modelo_id
             WHERE bc.bolao_competicao_id = ?
             LIMIT 1'
        );
        $stmt->execute([$pool_id]);
        $pool = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pool) {
            return [
                'ready' => false,
                'pool' => null,
                'counts' => [],
                'checks' => [[
                    'key' => 'pool',
                    'status' => 'BLOQUEADO',
                    'blocking' => true,
                    'label' => 'Bolão administrado',
                    'detail' => 'Bolão não localizado no DW Esportivo.',
                ]],
            ];
        }

        self::add_check(
            $checks,
            'pool_active',
            (int) $pool['ind_ativo'] === 1,
            'Bolão ativo',
            'O cadastro está ativo.',
            'O cadastro está inativo.'
        );
        self::add_check(
            $checks,
            'admin_visibility',
            strtoupper((string) $pool['visibilidade']) === 'ADMIN',
            'Visibilidade de homologação',
            'Apenas administradores podem acessar o bolão durante a abertura controlada.',
            'Defina a visibilidade como ADMIN antes de abrir o bolão.'
        );

        $model_supported = (int) $pool['competicao_ativa'] === 1
            && (int) $pool['modelo_ativo'] === 1
            && Mengao360_Bolao_Competition_Model::is_supported(
                $pool['slug_modelo'],
                $pool['nome_modelo'],
                $pool['tipo_mata_mata']
            );
        self::add_check(
            $checks,
            'competition_model',
            $model_supported,
            'Competição e modelo',
            'Competição ativa e modelo suportado pela engine.',
            'Competição inativa ou modelo ainda não suportado.'
        );

        $closing_minutes = (int) $pool['janela_fechamento_minutos'];
        self::add_check(
            $checks,
            'closing_window',
            $closing_minutes >= 0 && $closing_minutes <= 180,
            'Janela de fechamento',
            sprintf('%d minuto(s) antes de cada jogo.', $closing_minutes),
            'A janela deve estar entre 0 e 180 minutos.'
        );

        $open_statuses = Mengao360_Bolao_Game_Guard::get_open_statuses();
        $status_placeholders = implode(',', array_fill(0, count($open_statuses), '?'));
        $cutoff = $current->modify('+' . max(0, $closing_minutes) . ' minutes')->format('Y-m-d H:i:s');
        $sql = "SELECT
                    COUNT(*) AS total_jogos,
                    SUM(CASE WHEN fj.data_jogo IS NULL THEN 1 ELSE 0 END) AS jogos_sem_horario,
                    SUM(CASE
                        WHEN fj.status_jogo IN ({$status_placeholders}) AND fj.data_jogo > ?
                        THEN 1 ELSE 0 END) AS jogos_futuros,
                    SUM(CASE
                        WHEN fj.status_jogo IN ({$status_placeholders}) AND fj.data_jogo > ?
                         AND fj.mandante_id > 0 AND fj.visitante_id > 0
                         AND fj.mandante_id <> 9999 AND fj.visitante_id <> 9999
                         AND fj.mandante_id <> fj.visitante_id
                        THEN 1 ELSE 0 END) AS jogos_liberaveis,
                    SUM(CASE
                        WHEN fj.status_jogo IN ({$status_placeholders}) AND fj.data_jogo > ?
                         AND (fj.mandante_id IS NULL OR fj.visitante_id IS NULL
                              OR fj.mandante_id <= 0 OR fj.visitante_id <= 0
                              OR fj.mandante_id = 9999 OR fj.visitante_id = 9999
                              OR fj.mandante_id = fj.visitante_id)
                        THEN 1 ELSE 0 END) AS confrontos_indefinidos,
                    MIN(CASE
                        WHEN fj.status_jogo IN ({$status_placeholders}) AND fj.data_jogo > ?
                        THEN fj.data_jogo ELSE NULL END) AS proximo_jogo
                FROM fato_jogos fj
                WHERE fj.competicao_id = ?";
        $params = array_merge(
            $open_statuses,
            [$cutoff],
            $open_statuses,
            [$cutoff],
            $open_statuses,
            [$cutoff],
            $open_statuses,
            [$cutoff, (int) $pool['competicao_id']]
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $counts = array_merge([
            'total_jogos' => 0,
            'jogos_sem_horario' => 0,
            'jogos_futuros' => 0,
            'jogos_liberaveis' => 0,
            'confrontos_indefinidos' => 0,
            'proximo_jogo' => null,
        ], $counts);

        self::add_check(
            $checks,
            'calendar',
            (int) $counts['total_jogos'] > 0 && (int) $counts['jogos_futuros'] > 0,
            'Calendário do DW',
            sprintf('%d jogo(s), %d futuro(s).', (int) $counts['total_jogos'], (int) $counts['jogos_futuros']),
            'Nenhum jogo futuro com status agendado e horário válido.'
        );
        self::add_check(
            $checks,
            'eligible_games',
            (int) $counts['jogos_liberaveis'] > 0,
            'Confrontos liberáveis',
            sprintf('%d jogo(s) futuro(s) com os dois times definidos.', (int) $counts['jogos_liberaveis']),
            'Nenhum jogo futuro possui os dois times definidos.'
        );

        $undefined_count = (int) $counts['confrontos_indefinidos'];
        $checks[] = [
            'key' => 'undefined_teams',
            'status' => $undefined_count === 0 ? 'OK' : 'AVISO',
            'blocking' => false,
            'label' => 'Confrontos ainda indefinidos',
            'detail' => $undefined_count === 0
                ? 'Nenhum confronto futuro pendente de times.'
                : sprintf('%d confronto(s) continuarão bloqueados até o DW definir os times.', $undefined_count),
        ];

        $missing_time_count = (int) $counts['jogos_sem_horario'];
        $checks[] = [
            'key' => 'missing_times',
            'status' => $missing_time_count === 0 ? 'OK' : 'AVISO',
            'blocking' => false,
            'label' => 'Horários ausentes',
            'detail' => $missing_time_count === 0
                ? 'Todos os jogos possuem horário no DW.'
                : sprintf('%d jogo(s) permanecerão bloqueados por falta de horário.', $missing_time_count),
        ];

        self::add_check(
            $checks,
            'post_etl',
            self::routine_exists($pdo, 'sp_bolao_processar_jogos_finalizados_api'),
            'Integração pós-ETL',
            'Procedure de apuração disponível.',
            'Procedure sp_bolao_processar_jogos_finalizados_api ausente.'
        );

        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM bolao_status_palpite WHERE codigo = 'ABERTO' AND ind_ativo = 1"
        );
        self::add_check(
            $checks,
            'prediction_status',
            (int) $stmt->fetchColumn() === 1,
            'Status de palpite',
            'Status ABERTO disponível e ativo.',
            'Status ABERTO ausente, duplicado ou inativo.'
        );

        $ready = true;
        foreach ($checks as $check) {
            if (!empty($check['blocking']) && $check['status'] !== 'OK') {
                $ready = false;
                break;
            }
        }

        return [
            'ready' => $ready,
            'pool' => $pool,
            'counts' => $counts,
            'checks' => $checks,
            'evaluated_at' => $current->format(DateTimeInterface::ATOM),
        ];
    }

    public static function blocking_messages($report) {
        $messages = [];

        foreach ($report['checks'] ?? [] as $check) {
            if (!empty($check['blocking']) && ($check['status'] ?? '') !== 'OK') {
                $messages[] = ($check['label'] ?? 'Gate') . ': ' . ($check['detail'] ?? 'bloqueado');
            }
        }

        return $messages;
    }

    private static function add_check(&$checks, $key, $condition, $label, $success, $failure) {
        $checks[] = [
            'key' => $key,
            'status' => $condition ? 'OK' : 'BLOQUEADO',
            'blocking' => true,
            'label' => $label,
            'detail' => $condition ? $success : $failure,
        ];
    }

    private static function routine_exists($pdo, $routine_name) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = \'PROCEDURE\''
        );
        $stmt->execute([$routine_name]);

        return (int) $stmt->fetchColumn() === 1;
    }
}
