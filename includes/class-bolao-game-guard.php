<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Regra única de bloqueio de palpites, reutilizável pelo AJAX e pela UI.
 */
class Mengao360_Bolao_Game_Guard {

    public static function get_open_statuses() {
        return ['TIMED', 'SCHEDULED', 'NOT_STARTED', 'NS'];
    }

    public static function teams_are_defined($game) {
        $home_id = (int) ($game->mandante_id ?? 0);
        $away_id = (int) ($game->visitante_id ?? 0);

        return $home_id > 0
            && $away_id > 0
            && $home_id !== 9999
            && $away_id !== 9999
            && $home_id !== $away_id;
    }

    public static function evaluate($game, $closing_minutes = 10, $now = null) {
        if (!self::teams_are_defined($game)) {
            return self::blocked('TEAMS_UNDEFINED', 'Aguardando definição dos times.');
        }

        $status = strtoupper(trim((string) ($game->status_jogo ?? '')));

        if (!in_array($status, self::get_open_statuses(), true)) {
            return self::blocked('MATCH_NOT_SCHEDULED', 'O jogo já iniciou, foi encerrado ou não está disponível.');
        }

        $match_value = $game->data_jogo_completa ?? ($game->data_jogo ?? '');

        try {
            $timezone = new DateTimeZone('America/Sao_Paulo');
            $match = self::parse_match_time($match_value, $timezone);
            $current = $now instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($now)->setTimezone($timezone)
                : new DateTimeImmutable('now', $timezone);
            $closing = $match->modify('-' . max(0, (int) $closing_minutes) . ' minutes');

            if ($current >= $closing) {
                return self::blocked('PREDICTION_WINDOW_CLOSED', 'A janela de palpites deste jogo foi encerrada.');
            }
        } catch (Throwable $e) {
            return self::blocked('INVALID_MATCH_TIME', 'Horário do jogo indisponível para validação.');
        }

        return [
            'allowed' => true,
            'code' => 'OPEN',
            'message' => '',
        ];
    }

    private static function parse_match_time($value, DateTimeZone $timezone) {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            throw new InvalidArgumentException('Horário vazio.');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
            $normalized .= ':00';
        }

        $match = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $normalized, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if (!$match || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Formato de horário inválido.');
        }

        return $match;
    }

    private static function blocked($code, $message) {
        return [
            'allowed' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
