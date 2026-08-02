<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normaliza os modelos esportivos do catálogo do DW.
 *
 * A engine não altera fases ou jogos. Ela apenas reconhece quais estruturas
 * podem ser administradas pelo Bolão.
 */
class Mengao360_Bolao_Competition_Model {

    const LEAGUE = 'PONTOS_CORRIDOS';
    const GROUPS_KNOCKOUT = 'GRUPOS_MATA_MATA';
    const KNOCKOUT = 'MATA_MATA';

    public static function resolve($slug, $name = '', $knockout_type = '') {
        $value = self::normalize($slug . ' ' . $name);
        $knockout_value = self::normalize($knockout_type);

        // A modelagem do DW identifica a Libertadores pelo slug/nome da
        // competição e registra o mata-mata como IDA_VOLTA. A primeira fase
        // possui grupos; portanto, ela usa o fluxo combinado da engine.
        if (strpos($value, 'libertadores') !== false) {
            return self::GROUPS_KNOCKOUT;
        }

        if (strpos($value, 'grupo') !== false) {
            return self::GROUPS_KNOCKOUT;
        }

        if (strpos($value, 'ponto') !== false
            || strpos($value, 'corrido') !== false
            || strpos($value, 'liga') !== false) {
            return self::LEAGUE;
        }

        if (strpos($value, 'mata') !== false
            || strpos($value, 'elimin') !== false) {
            return self::KNOCKOUT;
        }

        // Modelos cadastrados no DW podem usar um nome comercial sem as
        // palavras-chave acima. Neste caso, o contrato tipo_mata_mata ainda
        // permite reconhecer com segurança eliminatórias de ida/volta ou
        // jogo único, sempre sem inferir ou alterar confrontos.
        if (strpos($knockout_value, 'ida volta') !== false
            || strpos($knockout_value, 'jogo unico') !== false) {
            return self::KNOCKOUT;
        }

        return null;
    }

    public static function is_supported($slug, $name = '', $knockout_type = '') {
        return self::resolve($slug, $name, $knockout_type) !== null;
    }

    private static function normalize($value) {
        $value = remove_accents(strtolower((string) $value));
        return preg_replace('/[^a-z0-9]+/', ' ', $value);
    }
}
