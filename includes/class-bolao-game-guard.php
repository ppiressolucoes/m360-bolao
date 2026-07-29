<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Regra única de bloqueio de palpites, reutilizável pelo AJAX e pela UI.
 */
class Mengao360_Bolao_Game_Guard {

    public static function evaluate($game, $closing_minutes = 10, $now = null) {
        $home_id = (int) ($game->mandante_id ?? 0);
        $away_id = (int) ($game->visitante_id ?? 0);

        if ($home_id <= 0 || $away_id <= 0 || $home_id === 9999 || $away_id === 9999 || $home_id === $away_id) {
            return self::blocked('TEAMS_UNDEFINED', 'Aguardando definição dos times.');
        }

        $status = strtoupper(trim((string) ($game->status_jogo ?? '')));
        $open_statuses = ['TIMED', 'SCHEDULED', 'NOT_STARTED', 'NS'];

        if (!in_array($status, $open_statuses, true)) {
            return self::blocked('MATCH_NOT_SCHEDULED', 'O jogo já iniciou, foi encerrado ou não está disponível.');
        }

        try {
            $timezone = new DateTimeZone('America/Sao_Paulo');
            $match = new DateTimeImmutable((string) $game->data_jogo, $timezone);
            $current = $now instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($now)
                : new DateTimeImmutable('now', $timezone);
            $closing = $match->modify('-' . max(0, (int) $closing_minutes) . ' minutes');

            if ($current >= $closing) {
                return self::blocked('PREDICTION_WINDOW_CLOSED', 'A janela de palpites deste jogo foi encerrada.');
            }
        } catch (Exception $e) {
            return self::blocked('INVALID_MATCH_TIME', 'Horário do jogo indisponível para validação.');
        }

        return [
            'allowed' => true,
            'code' => 'OPEN',
            'message' => '',
        ];
    }

    private static function blocked($code, $message) {
        return [
            'allowed' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
