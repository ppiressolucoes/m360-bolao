<?php
/**
 * Plugin Name: M360 - Mega Bolão 360
 * Description: Módulo de Bolões Esportivos do Portal Mengão 360.
 * Version: 0.2.2
 * Author: Mengão 360
 * Text Domain: mengao360-bolao
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MENGAO360_BOLAO_PLUGIN_VERSION', '0.2.2');
define('MENGAO360_BOLAO_VERSION', MENGAO360_BOLAO_PLUGIN_VERSION);
define('MENGAO360_BOLAO_BUILD', 'commercial-c2-product-hub-pre-homologation.3');
define(
    'MENGAO360_BOLAO_ASSET_VERSION',
    MENGAO360_BOLAO_VERSION . '-' . MENGAO360_BOLAO_BUILD
);
define('MENGAO360_BOLAO_PATH', plugin_dir_path(__FILE__));
define('MENGAO360_BOLAO_URL', plugin_dir_url(__FILE__));

function mengao360_bolao_load_textdomain() {
    load_plugin_textdomain(
        'mengao360-bolao',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'mengao360_bolao_load_textdomain');

require_once MENGAO360_BOLAO_PATH . 'includes/helpers-i18n.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-db.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-schema.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-competition-model.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-context.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-game-guard.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-opening-gate.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-sync.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-shortcodes.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-product-hub.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-user.php';
require_once MENGAO360_BOLAO_PATH . 'includes/class-bolao-ajax.php';

/**
 * Resolve o idioma atual do Bolão.
 * Prioridade: ?lang=URL > prefixo da URL pública > locale da página >
 * helper global do projeto > fallback pt-BR.
 */
if (!function_exists('m360_bolao_get_lang')) {
    function m360_bolao_get_lang() {
        $idiomas_permitidos = ['pt-BR', 'en-US', 'es-ES'];

        if (!empty($_GET['lang'])) {
            $lang = sanitize_text_field(wp_unslash($_GET['lang']));

            if (in_array($lang, $idiomas_permitidos, true)) {
                return $lang;
            }
        }

        $request_path = isset($_SERVER['REQUEST_URI'])
            ? (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH)
            : '';
        if (preg_match('#^/en(?:/|$)#i', $request_path)) {
            return 'en-US';
        }
        if (preg_match('#^/es(?:/|$)#i', $request_path)) {
            return 'es-ES';
        }

        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $locale_normalized = str_replace('_', '-', (string) $locale);
        $locale_map = [
            'pt' => 'pt-BR',
            'pt-BR' => 'pt-BR',
            'en' => 'en-US',
            'en-US' => 'en-US',
            'es' => 'es-ES',
            'es-ES' => 'es-ES',
        ];
        if (isset($locale_map[$locale_normalized])) {
            return $locale_map[$locale_normalized];
        }

        if (function_exists('m360_get_lang')) {
            $lang = m360_get_lang();

            if (in_array($lang, $idiomas_permitidos, true)) {
                return $lang;
            }
        }

        return 'pt-BR';
    }
}

/**
 * Dicionário curto para mensagens dinâmicas do JS/AJAX do Bolão.
 */
if (!function_exists('m360_bolao_i18n_labels')) {
    function m360_bolao_i18n_labels($lang = '') {
        $idiomas_permitidos = ['pt-BR', 'en-US', 'es-ES'];

        if (!in_array($lang, $idiomas_permitidos, true)) {
            $lang = m360_bolao_get_lang();
        }

        $dict = [
            'pt-BR' => [
                'informe_dois_placares' => 'Informe os dois placares antes de salvar.',
                'salvando' => 'Salvando...',
                'salvo' => 'Salvo ✓',
                'salvar_palpite' => 'Salvar palpite',
                'palpite_salvo' => 'Palpite salvo com sucesso!',
                'erro_salvar_palpite' => 'Erro ao salvar palpite.',
                'falha_comunicacao' => 'Falha de comunicação com o servidor.',
                'informe_nome_liga' => 'Informe um nome de liga com pelo menos 3 caracteres.',
                'criando' => 'Criando...',
                'liga_criada' => 'Liga criada ✓',
                'liga_criada_msg' => 'Liga "{nome}" criada com sucesso! Código: {codigo}',
                'criar_liga' => 'Criar liga',
                'erro_criar_liga' => 'Erro ao criar liga.',
                'informe_codigo_liga' => 'Informe um código de convite válido.',
                'entrando' => 'Entrando...',
                'entrou' => 'Entrou ✓',
                'entrar' => 'Entrar',
                'entrou_liga' => 'Você entrou na liga com sucesso!',
                'erro_entrar_liga' => 'Erro ao entrar na liga.',
            ],
            'en-US' => [
                'informe_dois_placares' => 'Enter both scores before saving.',
                'salvando' => 'Saving...',
                'salvo' => 'Saved ✓',
                'salvar_palpite' => 'Save prediction',
                'palpite_salvo' => 'Prediction saved successfully!',
                'erro_salvar_palpite' => 'Error saving prediction.',
                'falha_comunicacao' => 'Communication failure with the server.',
                'informe_nome_liga' => 'Enter a league name with at least 3 characters.',
                'criando' => 'Creating...',
                'liga_criada' => 'League created ✓',
                'liga_criada_msg' => 'League "{nome}" created successfully! Code: {codigo}',
                'criar_liga' => 'Create league',
                'erro_criar_liga' => 'Error creating league.',
                'informe_codigo_liga' => 'Enter a valid invitation code.',
                'entrando' => 'Joining...',
                'entrou' => 'Joined ✓',
                'entrar' => 'Join',
                'entrou_liga' => 'You joined the league successfully!',
                'erro_entrar_liga' => 'Error joining league.',
            ],
            'es-ES' => [
                'informe_dois_placares' => 'Introduce los dos marcadores antes de guardar.',
                'salvando' => 'Guardando...',
                'salvo' => 'Guardado ✓',
                'salvar_palpite' => 'Guardar pronóstico',
                'palpite_salvo' => '¡Pronóstico guardado con éxito!',
                'erro_salvar_palpite' => 'Error al guardar el pronóstico.',
                'falha_comunicacao' => 'Error de comunicación con el servidor.',
                'informe_nome_liga' => 'Introduce un nombre de liga con al menos 3 caracteres.',
                'criando' => 'Creando...',
                'liga_criada' => 'Liga creada ✓',
                'liga_criada_msg' => 'Liga "{nome}" creada con éxito. Código: {codigo}',
                'criar_liga' => 'Crear liga',
                'erro_criar_liga' => 'Error al crear la liga.',
                'informe_codigo_liga' => 'Introduce un código de invitación válido.',
                'entrando' => 'Entrando...',
                'entrou' => 'Entró ✓',
                'entrar' => 'Entrar',
                'entrou_liga' => '¡Entraste en la liga con éxito!',
                'erro_entrar_liga' => 'Error al entrar en la liga.',
            ],
        ];

        return array_merge($dict['pt-BR'], $dict[$lang] ?? []);
    }
}

if (is_admin()) {
    $m360_bolao_admin_file = MENGAO360_BOLAO_PATH . 'includes/class-bolao-admin.php';
    if (file_exists($m360_bolao_admin_file)) {
        require_once $m360_bolao_admin_file;
    }

    $m360_bolao_admin_pools_file = MENGAO360_BOLAO_PATH . 'includes/class-bolao-admin-pools.php';
    if (file_exists($m360_bolao_admin_pools_file)) {
        require_once $m360_bolao_admin_pools_file;
    }

    $m360_bolao_pre_homologation_file = MENGAO360_BOLAO_PATH . 'includes/class-bolao-pre-homologation.php';
    if (file_exists($m360_bolao_pre_homologation_file)) {
        require_once $m360_bolao_pre_homologation_file;
    }
}

function mengao360_bolao_enqueue_assets() {
    wp_enqueue_style(
        'mengao360-bolao-css',
        MENGAO360_BOLAO_URL . 'assets/css/bolao.css',
        [],
        MENGAO360_BOLAO_ASSET_VERSION
    );

    wp_enqueue_script(
        'mengao360-bolao-js',
        MENGAO360_BOLAO_URL . 'assets/js/bolao.js',
        ['jquery'],
        MENGAO360_BOLAO_ASSET_VERSION,
        true
    );
	
    $m360_bolao_lang = m360_bolao_get_lang();
    $m360_bolao_i18n = m360_bolao_i18n_labels($m360_bolao_lang);

    wp_localize_script(
        'mengao360-bolao-js',
        'm360Bolao',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('m360_bolao_nonce'),
            'lang'    => $m360_bolao_lang,
            'i18n'    => $m360_bolao_i18n,
        ]
    );

    wp_localize_script(
        'mengao360-bolao-js',
        'M360_BOLAO_I18N',
        array_merge(
            ['lang' => $m360_bolao_lang],
            $m360_bolao_i18n
        )
    );
}

add_action('wp_enqueue_scripts', 'mengao360_bolao_enqueue_assets');

function mengao360_bolao_init() {
    Mengao360_Bolao_Shortcodes::init();
	Mengao360_Bolao_Ajax::init();

    if (is_admin() && class_exists('Mengao360_Bolao_Admin')) {
        Mengao360_Bolao_Admin::init();
    }

    if (is_admin() && class_exists('Mengao360_Bolao_Admin_Pools')) {
        Mengao360_Bolao_Admin_Pools::init();
    }

    if (is_admin() && class_exists('Mengao360_Bolao_Pre_Homologation')) {
        Mengao360_Bolao_Pre_Homologation::init();
    }
}
add_action('init', 'mengao360_bolao_init');

/**
 * Oculta a barra superior do WordPress apenas nas páginas do bolão.
 */
function mengao360_bolao_ocultar_admin_bar_somente_bolao($show_admin_bar) {
    if (is_admin()) {
        return $show_admin_bar;
    }

    if (!is_user_logged_in()) {
        return $show_admin_bar;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

    if (
        strpos($request_uri, 'bolao') !== false ||
        strpos($request_uri, 'mega-bolao') !== false
    ) {
        return false;
    }

    return $show_admin_bar;
}
add_filter('show_admin_bar', 'mengao360_bolao_ocultar_admin_bar_somente_bolao');
