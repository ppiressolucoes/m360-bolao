<?php
/**
 * Helpers públicos de idioma/localização do Portal Mengão 360.
 *
 * Escopo:
 * - Usado no frontend público.
 * - Não altera dados técnicos do DW/API/Admin.
 * - Idiomas MVP: pt-BR e en-US.
 * - Lookup principal na tabela m360_i18n_publico do DW Esportivo.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('m360_supported_langs')) {
    function m360_supported_langs(): array {
        return ['pt-BR', 'en-US'];
    }
}

if (!function_exists('m360_normalize_lang')) {
    function m360_normalize_lang($lang): string {
        $lang = is_string($lang) ? trim($lang) : '';
        $lang = str_replace('_', '-', $lang);

        $map = [
            'pt'    => 'pt-BR',
            'pt-BR' => 'pt-BR',
            'pt-br' => 'pt-BR',
            'en'    => 'en-US',
            'en-US' => 'en-US',
            'en-us' => 'en-US',
        ];

        return $map[$lang] ?? 'pt-BR';
    }
}

if (!function_exists('m360_get_lang')) {
    /**
     * Resolve o idioma público do visitante.
     * Ordem:
     * 1) ?lang=pt-BR|en-US
     * 2) cookie m360_lang
     * 3) idioma do navegador
     * 4) fallback pt-BR
     */
    function m360_get_lang(): string {
        $supported = m360_supported_langs();

        if (isset($_GET['lang'])) {
            $lang = m360_normalize_lang(sanitize_text_field(wp_unslash($_GET['lang'])));
            if (in_array($lang, $supported, true)) {
                m360_set_lang_cookie($lang);
                return $lang;
            }
        }

        if (isset($_COOKIE['m360_lang'])) {
            $lang = m360_normalize_lang(sanitize_text_field(wp_unslash($_COOKIE['m360_lang'])));
            if (in_array($lang, $supported, true)) {
                return $lang;
            }
        }

        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accept_language = sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE']));
            $browser_lang = strtolower(substr($accept_language, 0, 2));

            if ($browser_lang === 'en') {
                return 'en-US';
            }
        }

        return 'pt-BR';
    }
}

if (!function_exists('m360_set_lang_cookie')) {
    function m360_set_lang_cookie(string $lang): void {
        $lang = m360_normalize_lang($lang);

        if (!in_array($lang, m360_supported_langs(), true)) {
            $lang = 'pt-BR';
        }

        if (!headers_sent()) {
            setcookie(
                'm360_lang',
                $lang,
                [
                    'expires'  => time() + (DAY_IN_SECONDS * 180),
                    'path'     => COOKIEPATH ?: '/',
                    'domain'   => COOKIE_DOMAIN ?: '',
                    'secure'   => is_ssl(),
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]
            );
        }

        $_COOKIE['m360_lang'] = $lang;
    }
}

if (!function_exists('m360_i18n_dw_pdo')) {
    /**
     * Retorna conexão PDO do DW Esportivo.
     * Mantém fallback leve para evitar erro fatal caso a classe ainda não esteja disponível.
     */
    function m360_i18n_dw_pdo() {
        if (class_exists('Mengao360_Bolao_DB') && method_exists('Mengao360_Bolao_DB', 'conectar')) {
            return Mengao360_Bolao_DB::conectar();
        }

        if (function_exists('conectar_dw_esportes_m360')) {
            return conectar_dw_esportes_m360();
        }

        return null;
    }
}

if (!function_exists('m360_i18n')) {
    /**
     * Busca texto público localizado na tabela m360_i18n_publico do DW.
     *
     * @param string      $entidade_tipo  Ex.: TIME, STATUS_JOGO, FASE, LABEL, BOTAO, MENSAGEM.
     * @param string      $entidade_chave Chave original do DW/API/código.
     * @param string|null $idioma         pt-BR/en-US. Se nulo, resolve automaticamente.
     * @param string|null $fallback       Texto usado quando não existir tradução.
     * @param string      $modulo         Ex.: GLOBAL, BOLAO, COMPETICOES.
     * @param bool        $use_short      Se true, tenta retornar texto_curto antes de texto_exibicao.
     */
    function m360_i18n(
        string $entidade_tipo,
        string $entidade_chave,
        ?string $idioma = null,
        ?string $fallback = null,
        string $modulo = 'GLOBAL',
        bool $use_short = false
    ): string {
        $idioma = $idioma ? m360_normalize_lang($idioma) : m360_get_lang();
        $fallback = $fallback ?? $entidade_chave;
        $entidade_chave = trim($entidade_chave);

        if ($entidade_chave === '') {
            return (string) $fallback;
        }

        static $cache = [];

        $cache_key = implode('|', [
            $modulo,
            $entidade_tipo,
            $entidade_chave,
            $idioma,
            $use_short ? 'short' : 'full',
        ]);

        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        $pdo = m360_i18n_dw_pdo();
        if (!$pdo) {
            $cache[$cache_key] = (string) $fallback;
            return $cache[$cache_key];
        }

        $column = $use_short
            ? "COALESCE(NULLIF(texto_curto, ''), texto_exibicao)"
            : 'texto_exibicao';

        $sql = "
            SELECT {$column} AS texto
            FROM m360_i18n_publico
            WHERE modulo = ?
              AND entidade_tipo = ?
              AND entidade_chave = ?
              AND idioma = ?
              AND ind_ativo = 1
            LIMIT 1
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$modulo, $entidade_tipo, $entidade_chave, $idioma]);
            $texto = $stmt->fetchColumn();

            if (($texto === false || $texto === null || $texto === '') && $modulo !== 'GLOBAL') {
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['GLOBAL', $entidade_tipo, $entidade_chave, $idioma]);
                $texto = $stmt->fetchColumn();
            }

            $cache[$cache_key] = ($texto !== false && $texto !== null && $texto !== '')
                ? (string) $texto
                : (string) $fallback;

            return $cache[$cache_key];
        } catch (Exception $e) {
            error_log('M360 i18n: erro no lookup público: ' . $e->getMessage());
            $cache[$cache_key] = (string) $fallback;
            return $cache[$cache_key];
        }
    }
}

if (!function_exists('m360_i18n_esc')) {
    /**
     * Helper para uso em HTML, já escapando a saída.
     */
    function m360_i18n_esc(
        string $entidade_tipo,
        string $entidade_chave,
        ?string $idioma = null,
        ?string $fallback = null,
        string $modulo = 'GLOBAL',
        bool $use_short = false
    ): string {
        return esc_html(m360_i18n($entidade_tipo, $entidade_chave, $idioma, $fallback, $modulo, $use_short));
    }
}
