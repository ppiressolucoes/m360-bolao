<!-- M360 TEMPLATE VERSION: v20260623-0015-suas-ligas-i18n-nextend-label -->
<?php
/**
 * Template principal do Bolão Mengão 360.
 *
 * Responsável por renderizar:
 * - Hero do bolão;
 * - Card de acesso/login;
 * - Painel básico do usuário logado;
 * - Agenda de palpites por data;
 * - Formulário de palpite quando o jogo estiver aberto;
 * - Palpite salvo quando existir;
 * - Bloqueio visual quando o jogo já tiver iniciado.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// Variáveis de segurança para evitar notices/warnings no template
// ============================================================
$datas_jogos      = $datas_jogos ?? [];
$jogos            = $jogos ?? [];
$palpites_usuario = $palpites_usuario ?? [];

// ============================================================
// Variáveis da Sprint 2: resumo do usuário e ranking geral
// ============================================================
$resumo_usuario = $resumo_usuario ?? null;
$resumo_dashboard = $resumo_dashboard ?? null;
$ranking_geral  = $ranking_geral ?? [];
$minhas_ligas  = $minhas_ligas ?? [];

$data_selecionada = $data_selecionada ?? '';
$data_anterior    = $data_anterior ?? '';
$data_proxima     = $data_proxima ?? '';
$competicao_slug  = $competicao_slug ?? 'fifa-world-cup';

// ============================================================
// URL pública da competição.
// Usada no botão "Agenda de jogos" dos cards finalizados.
// Mantém fallback por slug para futuros bolões/competições.
// ============================================================
$m360_competicao_public_urls = [
    'fifa-world-cup' => '/tabela-copa-do-mundo-fifa-2026/',
    'copa-libertadores' => '/tabela-copa-libertadores-2026/',
    'campeonato-brasileiro-serie-a' => '/brasileirao-serie-a/',
];

$m360_competicao_path = $m360_competicao_public_urls[$competicao_slug] ?? ('/' . sanitize_title($competicao_slug) . '/');
$m360_url_competicao = home_url($m360_competicao_path);


// ============================================================
// Sprint 6.5 — Camada pública multilíngue
// v20260622-idioma-shortcode-prioritario
//
// Ordem de prioridade:
// 1. idioma resolvido pelo shortcode em class-bolao-shortcodes.php
// 2. parâmetro ?lang= da URL
// 3. helper global m360_get_lang()
// 4. fallback pt-BR
// ============================================================
$m360_idiomas_permitidos = ['pt-BR', 'en-US', 'es-ES'];

$m360_lang = 'pt-BR';

// 1. Idioma já resolvido pelo shortcode [bolao_mengao idioma="..."]
if (
    !empty($m360_bolao_lang)
    && in_array($m360_bolao_lang, $m360_idiomas_permitidos, true)
) {
    $m360_lang = $m360_bolao_lang;

// 2. Parâmetro explícito de URL, mantido para testes e compatibilidade retroativa
} elseif (!empty($_GET['lang'])) {
    $m360_lang_param = sanitize_text_field(wp_unslash($_GET['lang']));

    if (in_array($m360_lang_param, $m360_idiomas_permitidos, true)) {
        $m360_lang = $m360_lang_param;
    }

// 3. Helper global do portal/plugin
} elseif (function_exists('m360_get_lang')) {
    $m360_lang_helper = m360_get_lang();

    if (in_array($m360_lang_helper, $m360_idiomas_permitidos, true)) {
        $m360_lang = $m360_lang_helper;
    }
}

// Preserva o idioma atual ao sair para a página pública da competição.
if (!empty($m360_lang) && $m360_lang !== 'pt-BR') {
    $m360_url_competicao = add_query_arg('lang', $m360_lang, $m360_url_competicao);
}

// Fallbacks locais do Bolão.
// Mantêm a tela pública trilíngue mesmo quando alguma chave ainda
// não estiver cadastrada em m360_i18n_publico.
$m360_bolao_fallbacks = [
    'pt-BR' => [
        'BOLAO|LABEL|meu_bolao_360' => 'Meu Bolão 360',
        'BOLAO|LABEL|titulo_bolao_wc26' => 'Bolão Copa do Mundo FIFA 2026',
        'BOLAO|MENSAGEM|hero_subtitulo_wc26' => 'Dê seus palpites, acompanhe sua pontuação, dispute rankings e participe de ligas com amigos.',
        'BOLAO|LABEL|entre_para_participar' => 'Entre para participar',
        'BOLAO|MENSAGEM|login_google_descricao' => 'Faça login com Google para salvar seus palpites, disputar rankings e participar das ligas do Bolão 360.',
        'BOLAO|MENSAGEM|painel_ativo' => 'Seu painel do [ Meu Bolão 360 ] está ativo.',
        'BOLAO|BOTAO|sair' => 'Sair',
        'BOLAO|LABEL|pontos' => 'Pontos',
        'BOLAO|LABEL|palpites_enviados' => 'Palpites enviados',
        'BOLAO|LABEL|ranking_geral' => 'Ranking geral',
        'BOLAO|LABEL|ranking_da_liga' => 'Ranking da liga',
        'BOLAO|LABEL|ranking_do_dia' => 'Ranking do dia',
        'BOLAO|LABEL|palpites_apurados' => 'Palpites apurados',
        'BOLAO|LABEL|agenda_de_palpites' => 'Agenda de Palpites',
        'BOLAO|BOTAO|salvar_palpite' => 'Salvar palpite',
        'BOLAO|BOTAO|atualizar_palpite' => 'Atualizar palpite',
        'GLOBAL|STATUS_JOGO|FINISHED' => 'Resultado final',
        'BOLAO|MENSAGEM|login_para_palpitar' => 'Quero palpitar',
        'BOLAO|LABEL|minhas_ligas' => 'Minhas Ligas',
        'BOLAO|LABEL|suas_ligas' => 'Suas ligas',
        'BOLAO|MENSAGEM|ligas_login_descricao' => 'Faça login com Google para criar ligas privadas, convidar seus amigos e disputar rankings exclusivos.',
        'BOLAO|LABEL|criar_liga_privada' => 'Criar liga privada',
        'BOLAO|MENSAGEM|criar_liga_privada_desc' => 'Crie um grupo fechado e compartilhe o código com seus amigos.',
        'BOLAO|BOTAO|criar_liga' => 'Criar liga',
        'BOLAO|LABEL|entrar_com_codigo' => 'Entrar com código',
        'BOLAO|MENSAGEM|entrar_com_codigo_desc' => 'Recebeu um convite? Informe o código da liga.',
        'BOLAO|BOTAO|entrar_na_liga' => 'Entrar',
        'BOLAO|LABEL|dono' => 'Dono',
        'BOLAO|LABEL|participante' => 'Participante',
        'BOLAO|LABEL|codigo' => 'Código',
        'BOLAO|BOTAO|compartilhar' => 'Compartilhar',
        'BOLAO|MENSAGEM|sem_liga_privada' => 'Você ainda não participa de nenhuma liga privada nesta competição.',
        'BOLAO|LABEL|posicao_abrev' => 'Pos',
        'BOLAO|LABEL|pontos_abrev' => 'Pts',
        'BOLAO|LABEL|placares_exatos_abrev' => 'Exatos',
    ],
    'en-US' => [
        'BOLAO|LABEL|meu_bolao_360' => 'My Bolão 360',
        'BOLAO|LABEL|titulo_bolao_wc26' => 'World Cup Pool 2026',
        'BOLAO|MENSAGEM|hero_subtitulo_wc26' => 'Make your predictions, track your score, compete in rankings and join leagues with friends.',
        'BOLAO|LABEL|entre_para_participar' => 'Sign in to participate',
        'BOLAO|MENSAGEM|login_google_descricao' => 'Sign in with Google to save your predictions, compete in rankings and join Bolão 360 leagues.',
        'BOLAO|MENSAGEM|painel_ativo' => 'Your [ My Bolão 360 ] dashboard is active.',
        'BOLAO|BOTAO|sair' => 'Log out',
        'BOLAO|LABEL|pontos' => 'Points',
        'BOLAO|LABEL|palpites_enviados' => 'Predictions sent',
        'BOLAO|LABEL|ranking_geral' => 'Overall ranking',
        'BOLAO|LABEL|ranking_da_liga' => 'League ranking',
        'BOLAO|LABEL|ranking_do_dia' => 'Daily ranking',
        'BOLAO|LABEL|palpites_apurados' => 'Predictions scored',
        'BOLAO|LABEL|agenda_de_palpites' => 'Prediction Schedule',
        'BOLAO|BOTAO|salvar_palpite' => 'Save prediction',
        'BOLAO|BOTAO|atualizar_palpite' => 'Update prediction',
        'GLOBAL|STATUS_JOGO|FINISHED' => 'Final result',
        'BOLAO|MENSAGEM|login_para_palpitar' => 'I want to predict',
        'BOLAO|LABEL|minhas_ligas' => 'My Leagues',
        'BOLAO|LABEL|suas_ligas' => 'Your leagues',
        'BOLAO|MENSAGEM|ligas_login_descricao' => 'Sign in with Google to create private leagues, invite friends and compete in exclusive rankings.',
        'BOLAO|LABEL|criar_liga_privada' => 'Create private league',
        'BOLAO|MENSAGEM|criar_liga_privada_desc' => 'Create a private group and share the code with your friends.',
        'BOLAO|BOTAO|criar_liga' => 'Create league',
        'BOLAO|LABEL|entrar_com_codigo' => 'Join with code',
        'BOLAO|MENSAGEM|entrar_com_codigo_desc' => 'Got an invitation? Enter the league code.',
        'BOLAO|BOTAO|entrar_na_liga' => 'Join',
        'BOLAO|LABEL|dono' => 'Owner',
        'BOLAO|LABEL|participante' => 'Participant',
        'BOLAO|LABEL|codigo' => 'Code',
        'BOLAO|BOTAO|compartilhar' => 'Share',
        'BOLAO|MENSAGEM|sem_liga_privada' => 'You are not in any private league for this competition yet.',
        'BOLAO|LABEL|posicao_abrev' => 'Pos',
        'BOLAO|LABEL|pontos_abrev' => 'Pts',
        'BOLAO|LABEL|placares_exatos_abrev' => 'Exact',
    ],
    'es-ES' => [
        'BOLAO|LABEL|meu_bolao_360' => 'Mi Bolão 360',
        'BOLAO|LABEL|titulo_bolao_wc26' => 'Bolão Copa del Mundo 2026',
        'BOLAO|MENSAGEM|hero_subtitulo_wc26' => 'Haz tus pronósticos, sigue tu puntuación, compite en clasificaciones y participa en ligas con amigos.',
        'BOLAO|LABEL|entre_para_participar' => 'Inicia sesión para participar',
        'BOLAO|MENSAGEM|login_google_descricao' => 'Inicia sesión con Google para guardar tus pronósticos, competir en clasificaciones y participar en las ligas de Bolão 360.',
        'BOLAO|MENSAGEM|painel_ativo' => 'Tu panel de [ Mi Bolão 360 ] está activo.',
        'BOLAO|BOTAO|sair' => 'Salir',
        'BOLAO|LABEL|pontos' => 'Puntos',
        'BOLAO|LABEL|palpites_enviados' => 'Pronósticos enviados',
        'BOLAO|LABEL|ranking_geral' => 'Clasificación general',
        'BOLAO|LABEL|ranking_da_liga' => 'Clasificación de la liga',
        'BOLAO|LABEL|ranking_do_dia' => 'Clasificación del día',
        'BOLAO|LABEL|palpites_apurados' => 'Pronósticos calculados',
        'BOLAO|LABEL|agenda_de_palpites' => 'Agenda de Pronósticos',
        'BOLAO|BOTAO|salvar_palpite' => 'Guardar pronóstico',
        'BOLAO|BOTAO|atualizar_palpite' => 'Actualizar pronóstico',
        'GLOBAL|STATUS_JOGO|FINISHED' => 'Resultado final',
        'BOLAO|MENSAGEM|login_para_palpitar' => 'Quiero pronosticar',
        'BOLAO|LABEL|minhas_ligas' => 'Mis Ligas',
        'BOLAO|LABEL|suas_ligas' => 'Tus ligas',
        'BOLAO|MENSAGEM|ligas_login_descricao' => 'Inicia sesión con Google para crear ligas privadas, invitar a tus amigos y competir en clasificaciones exclusivas.',
        'BOLAO|LABEL|criar_liga_privada' => 'Crear liga privada',
        'BOLAO|MENSAGEM|criar_liga_privada_desc' => 'Crea un grupo cerrado y comparte el código con tus amigos.',
        'BOLAO|BOTAO|criar_liga' => 'Crear liga',
        'BOLAO|LABEL|entrar_com_codigo' => 'Entrar con código',
        'BOLAO|MENSAGEM|entrar_com_codigo_desc' => '¿Recibiste una invitación? Ingresa el código de la liga.',
        'BOLAO|BOTAO|entrar_na_liga' => 'Entrar',
        'BOLAO|LABEL|dono' => 'Propietario',
        'BOLAO|LABEL|participante' => 'Participante',
        'BOLAO|LABEL|codigo' => 'Código',
        'BOLAO|BOTAO|compartilhar' => 'Compartir',
        'BOLAO|MENSAGEM|sem_liga_privada' => 'Todavía no participas en ninguna liga privada de esta competición.',
        'BOLAO|LABEL|posicao_abrev' => 'Pos',
        'BOLAO|LABEL|pontos_abrev' => 'Pts',
        'BOLAO|LABEL|placares_exatos_abrev' => 'Exactos',
    ],
];

$m360_inline = function($pt, $en = '', $es = '') use ($m360_lang) {
    if ($m360_lang === 'en-US') {
        return $en !== '' ? $en : $pt;
    }

    if ($m360_lang === 'es-ES') {
        return $es !== '' ? $es : $pt;
    }

    return $pt;
};

$m360_txt = function($entidade_tipo, $entidade_chave, $fallback, $modulo = 'GLOBAL', $use_short = false) use ($m360_lang, $m360_bolao_fallbacks) {
    $fallback_key = implode('|', [
        (string) $modulo,
        (string) $entidade_tipo,
        (string) $entidade_chave
    ]);

    $tem_fallback_idioma = isset($m360_bolao_fallbacks[$m360_lang][$fallback_key]);

    $fallback_resolvido = $m360_bolao_fallbacks[$m360_lang][$fallback_key]
        ?? $m360_bolao_fallbacks['pt-BR'][$fallback_key]
        ?? (string) $fallback;

    /*
     * v2026-06-23 00h05 — Prioridade ao fallback local do Bolão.
     *
     * Motivo:
     * - O shortcode já resolve corretamente pt-BR / en-US / es-ES.
     * - O template já possui dicionário local completo para o módulo BOLAO.
     * - A função global m360_i18n() pode retornar registros antigos/incompletos do DW
     *   e sobrescrever traduções corretas do template, especialmente em es-ES.
     *
     * Regra:
     * - Para chaves do módulo BOLAO com tradução local do idioma atual, usa o fallback local.
     * - Para chaves GLOBAL também usa fallback local quando existir uma chave explícita.
     * - Para TIME, STATUS e demais chaves sem fallback local, mantém consulta em m360_i18n().
     */
    if ($tem_fallback_idioma && in_array((string) $modulo, ['BOLAO', 'GLOBAL'], true)) {
        return (string) $fallback_resolvido;
    }

    if (function_exists('m360_i18n')) {
        return m360_i18n(
            (string) $entidade_tipo,
            (string) $entidade_chave,
            $m360_lang,
            (string) $fallback_resolvido,
            (string) $modulo,
            (bool) $use_short
        );
    }

    return (string) $fallback_resolvido;
};

$m360_txt_esc = function($entidade_tipo, $entidade_chave, $fallback, $modulo = 'GLOBAL', $use_short = false) use ($m360_txt) {
    return esc_html($m360_txt($entidade_tipo, $entidade_chave, $fallback, $modulo, $use_short));
};

// ============================================================
// Sprint I18N-2B — Ajuste visual do botão Google do Nextend.
// O Nextend Social Login renderiza o texto do botão fora do DW.
// Esta função preserva o HTML do plugin e troca apenas o rótulo visível.
// ============================================================
$m360_google_login_shortcode = function() use ($m360_inline) {
    $html = do_shortcode('[nextend_social_login provider="google"]');
    $label = esc_html($m360_inline('Continue com Google', 'Continue with Google', 'Continuar con Google'));

    return str_replace(
        [
            'Continue com Google',
            'Entrar com Google',
            'Sign in with Google',
            'Continue with Google',
            'Continuar con Google'
        ],
        $label,
        $html
    );
};

// ============================================================
// Sprint 7 — Ranking por jogo dentro do card da agenda
// Busca top participantes na view canônica de ranking por jogo.
// Mantém fallback silencioso caso a view ainda não exista no banco.
// ============================================================
$m360_get_ranking_por_jogo = function($bolao_competicao_id, $jogo_id, $limit = 5) {
    $bolao_competicao_id = (int) $bolao_competicao_id;
    $jogo_id = (int) $jogo_id;
    $limit = max(1, min(10, (int) $limit));

    // O jogo_id é obrigatório. O bolao_competicao_id pode ser resolvido dinamicamente pelo jogo.
    if ($jogo_id <= 0) {
        return [];
    }

    $pdo = null;

    /*
     * v4 — Conexão robusta.
     * Primeiro tenta a conexão canônica do plugin; depois a função global.
     * Isso evita retorno vazio quando o template é carregado em contexto diferente.
     */
    try {
        if (class_exists('Mengao360_Bolao_DB') && method_exists('Mengao360_Bolao_DB', 'conectar')) {
            $pdo = Mengao360_Bolao_DB::conectar();
        }

        if (!$pdo && function_exists('conectar_dw_esportes_m360')) {
            $pdo = conectar_dw_esportes_m360();
        }
    } catch (Throwable $e) {
        if (function_exists('error_log')) {
            error_log('Bolão M360 ranking por jogo - falha ao conectar: ' . $e->getMessage());
        }
        $pdo = null;
    }

    if (!$pdo) {
        if (function_exists('error_log')) {
            error_log('Bolão M360 ranking por jogo: conexão DW indisponível. bolao_competicao_id=' . $bolao_competicao_id . ' jogo_id=' . $jogo_id);
        }
        return [];
    }

    // v5 — Resolve o bolao_competicao_id dinamicamente quando o template não recebeu esse valor.
    // Isso evita ID fixo e permite reutilização em outros bolões/competições.
    if ($bolao_competicao_id <= 0) {
        try {
            $sql_resolve_bolao = "
                SELECT bc.bolao_competicao_id
                FROM bolao_competicoes bc
                INNER JOIN fato_jogos fj
                    ON fj.competicao_id = bc.competicao_id
                WHERE fj.id = :jogo_id
                  AND bc.ind_ativo = 1
                ORDER BY bc.bolao_competicao_id DESC
                LIMIT 1
            ";

            $stmt_resolve = $pdo->prepare($sql_resolve_bolao);
            $stmt_resolve->execute([':jogo_id' => $jogo_id]);
            $bolao_resolvido = $stmt_resolve->fetch(PDO::FETCH_OBJ);

            if ($bolao_resolvido && !empty($bolao_resolvido->bolao_competicao_id)) {
                $bolao_competicao_id = (int) $bolao_resolvido->bolao_competicao_id;
            }
        } catch (Throwable $e) {
            if (function_exists('error_log')) {
                error_log('Bolão M360 ranking por jogo - falha ao resolver bolao_competicao_id: ' . $e->getMessage());
            }
        }
    }

    if ($bolao_competicao_id <= 0) {
        if (function_exists('error_log')) {
            error_log('Bolão M360 ranking por jogo: bolao_competicao_id não resolvido para jogo_id=' . $jogo_id);
        }
        return [];
    }

    $queries = [];

    // Consulta principal pela view consolidada.
    $queries[] = "
        SELECT
            jogo_id,
            posicao,
            nome_exibicao,
            avatar_url,
            palpite_mandante,
            palpite_visitante,
            resultado_mandante,
            resultado_visitante,
            pontos_total,
            qtd_placar_exato,
            qtd_resultado_correto
        FROM vw_bolao_ranking_por_jogo
        WHERE bolao_competicao_id = :bolao_competicao_id
          AND jogo_id = :jogo_id
        ORDER BY posicao ASC, pontos_total DESC
        LIMIT {$limit}
    ";

    // Fallback direto nas tabelas, caso a view não esteja acessível ou esteja vazia.
    $queries[] = "
        SELECT
            br.jogo_id,
            br.posicao,
            COALESCE(NULLIF(u.nome_exibicao, ''), CONCAT('Participante #', u.usuario_bolao_id)) AS nome_exibicao,
            u.avatar_url,
            p.placar_mandante AS palpite_mandante,
            p.placar_visitante AS palpite_visitante,
            fj.placar_mandante AS resultado_mandante,
            fj.placar_visitante AS resultado_visitante,
            br.pontos_total,
            br.qtd_placar_exato,
            br.qtd_resultado_correto
        FROM bolao_ranking br
        INNER JOIN bolao_tipo_ranking tr
            ON tr.tipo_ranking_id = br.tipo_ranking_id
           AND tr.codigo = 'JOGO'
        INNER JOIN bolao_usuarios u
            ON u.usuario_bolao_id = br.usuario_bolao_id
        INNER JOIN fato_jogos fj
            ON fj.id = br.jogo_id
        LEFT JOIN bolao_pontuacao bp
            ON bp.bolao_competicao_id = br.bolao_competicao_id
           AND bp.jogo_id = br.jogo_id
           AND bp.usuario_bolao_id = br.usuario_bolao_id
        LEFT JOIN bolao_palpites p
            ON p.palpite_id = bp.palpite_id
        WHERE br.bolao_competicao_id = :bolao_competicao_id
          AND br.jogo_id = :jogo_id
        ORDER BY br.posicao ASC, br.pontos_total DESC
        LIMIT {$limit}
    ";

    foreach ($queries as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':bolao_competicao_id' => $bolao_competicao_id,
                ':jogo_id' => $jogo_id,
            ]);

            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            if (is_array($rows) && !empty($rows)) {
                return $rows;
            }
        } catch (Throwable $e) {
            if (function_exists('error_log')) {
                error_log('Bolão M360 ranking por jogo - consulta falhou: ' . $e->getMessage());
            }
        }
    }

    return [];
};

$m360_bolao_encerrado = isset($bolao_estado_operacional)
    && $bolao_estado_operacional === 'ENCERRADO';

?>

<div class="m360-bolao"
     data-bolao-id="<?php echo esc_attr($bolao_competicao_id); ?>"
     data-bolao="<?php echo esc_attr($bolao_slug); ?>"
     data-competicao="<?php echo esc_attr($competicao_slug); ?>"
     data-lang="<?php echo esc_attr($m360_lang); ?>">

    <!-- ============================================================
         Hero do Bolão
         ============================================================ -->
    <section class="m360-bolao-hero">
        <div>
            <span class="m360-bolao-tag"><?php echo $m360_txt_esc('LABEL', 'meu_bolao_360', 'Meu Bolão 360', 'BOLAO'); ?></span>
            <h1>
                <?php if (!empty($bolao_usar_conteudo_legado_wc)): ?>
                    <?php echo $m360_txt_esc('LABEL', 'titulo_bolao_wc26', 'Bolão Copa do Mundo FIFA 2026', 'BOLAO'); ?>
                <?php else: ?>
                    <?php echo esc_html($bolao_titulo_publico); ?>
                <?php endif; ?>
            </h1>
            <p>
                <?php if ($m360_bolao_encerrado): ?>
                    <?php echo esc_html($m360_inline(
                        'Competição encerrada. Consulte os resultados, palpites apurados e o ranking final.',
                        'Pool closed. Browse the results, scored predictions and final ranking.',
                        'Quiniela finalizada. Consulta los resultados, pronósticos calculados y la clasificación final.'
                    )); ?>
                <?php else: ?>
                    <?php if (!empty($bolao_usar_conteudo_legado_wc)): ?>
                        <?php echo $m360_txt_esc('MENSAGEM', 'hero_subtitulo_wc26', 'Dê seus palpites, acompanhe sua pontuação, dispute rankings e participe de ligas com amigos.', 'BOLAO'); ?>
                    <?php else: ?>
                        <?php echo esc_html($bolao_descricao_publica); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
    </section>

    <?php if (!$bolao_aberto): ?>
        <section class="m360-bolao-card m360-bolao-estado-card">
            <h2>
                <?php echo esc_html($m360_bolao_encerrado
                    ? $m360_inline('Bolão encerrado', 'Pool closed', 'Quiniela finalizada')
                    : $m360_inline('Bolão indisponível para participação', 'Pool unavailable for participation', 'Quiniela no disponible para participar')); ?>
            </h2>
            <p>
                <?php echo esc_html($m360_bolao_encerrado
                    ? $m360_inline(
                        'O período de palpites e participação em ligas terminou. Resultados e ranking permanecem disponíveis para consulta.',
                        'Predictions and league participation have ended. Results and rankings remain available for viewing.',
                        'El período de pronósticos y participación en ligas terminó. Los resultados y clasificaciones siguen disponibles.'
                    )
                    : $m360_inline(
                        'Este bolão ainda não está aberto ou foi temporariamente bloqueado.',
                        'This pool is not open yet or has been temporarily blocked.',
                        'Esta quiniela aún no está abierta o fue bloqueada temporalmente.'
                    )); ?>
            </p>
        </section>
    <?php endif; ?>

    <!-- ============================================================
         Card de acesso para visitantes
         ============================================================ -->
    <?php if (!$usuario_logado && $bolao_aberto): ?>

        <section id="m360-bolao-login" class="m360-bolao-card">
            <!-- ============================================================
                 Visitante: acesso ao Bolão com Login Google.
                 Mantido separado para facilitar futuras evoluções.
                 ============================================================ -->
            <h2><?php echo $m360_txt_esc('LABEL', 'entre_para_participar', 'Entre para participar', 'BOLAO'); ?></h2>

            <p>
                <?php echo $m360_txt_esc('MENSAGEM', 'login_google_descricao', 'Faça login com Google para salvar seus palpites, disputar rankings e participar das ligas do Bolão 360.', 'BOLAO'); ?>
            </p>

            <div class="m360-bolao-login-social">
                <?php echo $m360_google_login_shortcode(); ?>
            </div>
        </section>

    <?php elseif ($usuario_logado): ?>

        <!-- ============================================================
             Painel resumido do usuário logado
             Sprint 2: pontos, palpites e posição passam a vir do ranking.
             ============================================================ -->
        <?php
        // ============================================================
        // Valores padrão do painel do usuário
        // ============================================================
        $painel_pontos = $resumo_usuario->pontos_total ?? 0;
        $painel_palpites_apurados = $resumo_usuario->qtd_palpites ?? 0;
        $painel_palpites_enviados = $resumo_dashboard->palpites_enviados ?? 0;

        $formatar_posicao = function($posicao) {
            return !empty($posicao) ? $posicao . 'º' : '-';
        };

        $painel_posicao_geral = $formatar_posicao(
            $resumo_dashboard->posicao_geral ?? ($resumo_usuario->posicao ?? null)
        );

        $painel_posicao_liga = $formatar_posicao(
            $resumo_dashboard->posicao_liga ?? null
        );

        $painel_posicao_dia = $formatar_posicao(
            $resumo_dashboard->posicao_dia ?? null
        );

        // ============================================================
        // Sprint 5.5 - UX: logout com retorno para a página pública do bolão
        // Mantém o usuário na experiência do produto, sem cair na tela padrão do WP.
        // ============================================================
        $url_logout_bolao = wp_logout_url(get_permalink());
        ?>

        <section class="m360-bolao-card">
            <div class="m360-bolao-painel-topo">
                <div>
                    <h2><?php echo esc_html($m360_inline('Olá', 'Hello', 'Hola')); ?>, <?php echo esc_html($usuario_atual->display_name); ?>!</h2>
                    <p>
                        <?php echo $m360_txt_esc('MENSAGEM', 'painel_ativo', 'Seu painel do [ Meu Bolão 360 ] está ativo.', 'BOLAO'); ?>
                    </p>
                </div>

                <a
                    class="m360-bolao-logout"
                    href="<?php echo esc_url($url_logout_bolao); ?>"
                >
                    <?php echo $m360_txt_esc('BOTAO', 'sair', 'Sair', 'BOLAO'); ?>
                </a>
            </div>

            <div class="m360-bolao-grid m360-bolao-grid-dashboard">
                <div class="m360-bolao-stat">
                    <strong><?php echo esc_html($painel_pontos); ?></strong>
                    <span><?php echo $m360_txt_esc('LABEL', 'pontos', 'Pontos', 'BOLAO'); ?></span>
                </div>

                <div class="m360-bolao-stat">
                    <strong><?php echo esc_html($painel_palpites_enviados); ?></strong>
                    <span><?php echo $m360_txt_esc('LABEL', 'palpites_enviados', 'Palpites enviados', 'BOLAO'); ?></span>
                </div>

                <div class="m360-bolao-stat">
                    <strong><?php echo esc_html($painel_posicao_geral); ?></strong>
                    <span><?php echo $m360_txt_esc('LABEL', 'ranking_geral', 'Ranking geral', 'BOLAO'); ?></span>
                </div>

                <div class="m360-bolao-stat">
                    <strong><?php echo esc_html($painel_posicao_liga); ?></strong>
                    <span><?php echo $m360_txt_esc('LABEL', 'ranking_da_liga', 'Ranking da liga', 'BOLAO'); ?></span>
                </div>

                <div class="m360-bolao-stat">
                    <strong><?php echo esc_html($painel_posicao_dia); ?></strong>
                    <span><?php echo $m360_txt_esc('LABEL', 'ranking_do_dia', 'Ranking do dia', 'BOLAO'); ?></span>
                </div>

                <div class="m360-bolao-stat">
                    <strong><?php echo esc_html($painel_palpites_apurados); ?></strong>
                    <span><?php echo $m360_txt_esc('LABEL', 'palpites_apurados', 'Palpites apurados', 'BOLAO'); ?></span>
                </div>
            </div>
        </section>

    <?php endif; ?>

    <!-- ============================================================
         Agenda de Palpites
         ============================================================ -->
    <section id="m360-agenda-palpites" class="m360-bolao-card">
        <h2><?php echo $m360_txt_esc('LABEL', 'agenda_de_palpites', 'Agenda de Palpites', 'BOLAO'); ?></h2>

        <?php if (!empty($datas_jogos)): ?>

            <!-- ============================================================
                 Navegação entre datas com jogos da competição

                 Sprint 2.5 UX:
                 - Controles passam a usar apenas ícones;
                 - Datas anterior/próxima ficam como apoio visual;
                 - Estrutura fica estável em desktop, Edge, Chrome e mobile.
                 ============================================================ -->
            <div class="m360-bolao-navegacao" aria-label="Navegação entre dias de jogos">

                <div class="m360-bolao-nav-lado m360-bolao-nav-esquerda">
                    <?php if (!empty($data_anterior)): ?>
                        <a class="m360-bolao-nav-icone"
                           href="<?php echo esc_url(add_query_arg('data_jogo', $data_anterior) . '#m360-agenda-palpites'); ?>"
                           aria-label="<?php echo esc_attr($m360_inline('Dia anterior', 'Previous day', 'Día anterior')); ?>">
                            <span aria-hidden="true">‹</span>
                        </a>
                        <span class="m360-bolao-nav-data-apoio">
                            <?php echo esc_html(date('d/m', strtotime($data_anterior))); ?>
                        </span>
                    <?php else: ?>
                        <span class="m360-bolao-nav-icone m360-bolao-nav-disabled" aria-hidden="true">
                            ‹
                        </span>
                    <?php endif; ?>
                </div>

                <div class="m360-bolao-nav-centro">
                    <span class="m360-bolao-nav-label"><?php echo esc_html($m360_inline('Jogos de', 'Matches on', 'Partidos de')); ?></span>

                    <strong>
                        <?php if (!empty($data_selecionada)): ?>
                            <?php echo esc_html(date('d/m/Y', strtotime($data_selecionada))); ?>
                        <?php else: ?>
                            <?php echo esc_html($m360_inline('Data indisponível', 'Date unavailable', 'Fecha no disponible')); ?>
                        <?php endif; ?>
                    </strong>
                </div>

                <div class="m360-bolao-nav-lado m360-bolao-nav-direita">
                    <?php if (!empty($data_proxima)): ?>
                        <span class="m360-bolao-nav-data-apoio">
                            <?php echo esc_html(date('d/m', strtotime($data_proxima))); ?>
                        </span>
                        <a class="m360-bolao-nav-icone"
                           href="<?php echo esc_url(add_query_arg('data_jogo', $data_proxima) . '#m360-agenda-palpites'); ?>"
                           aria-label="<?php echo esc_attr($m360_inline('Próximo dia', 'Next day', 'Día siguiente')); ?>">
                            <span aria-hidden="true">›</span>
                        </a>
                    <?php else: ?>
                        <span class="m360-bolao-nav-icone m360-bolao-nav-disabled" aria-hidden="true">
                            ›
                        </span>
                    <?php endif; ?>
                </div>

            </div>

            <?php if (!empty($jogos)): ?>

                <?php foreach ($jogos as $jogo): ?>

                    <?php
                    // ============================================================
                    // Recupera o palpite salvo do usuário para o jogo atual.
                    // Quando não houver palpite salvo, o valor permanece null.
                    // ============================================================
                    $palpite_salvo = $palpites_usuario[(int) $jogo->jogo_id] ?? null;

                    // ============================================================
                    // Define o texto do botão de ação.
                    // ============================================================
                    $texto_botao_palpite = $palpite_salvo
                        ? $m360_txt('BOTAO', 'atualizar_palpite', $m360_inline('Atualizar palpite', 'Update prediction', 'Actualizar pronóstico'), 'BOLAO')
                        : $m360_txt('BOTAO', 'salvar_palpite', 'Salvar palpite', 'BOLAO');

                    // ============================================================
                    // Controle de abertura/bloqueio do palpite.
                    //
                    // Regra configurada no bolão:
                    // - Palpites encerram N minutos antes da partida.
                    //
                    // Observação:
                    // - A coluna palpite_aberto ainda é respeitada como fallback;
                    // - O cálculo abaixo usa o horário completo do jogo quando disponível.
                    // ============================================================
                    $data_hora_jogo = $jogo->data_jogo_completa
                        ?? ($jogo->data_jogo ?? '');

                    if (empty($data_hora_jogo) && !empty($data_selecionada) && !empty($jogo->hora_jogo)) {
                        $data_hora_jogo = $data_selecionada . ' ' . $jogo->hora_jogo . ':00';
                    }

                    /*
                     * Sprint 5.6.2 - Correção crítica de fuso horário do bloqueio.
                     *
                     * Os horários da DW estão gravados em horário de Brasília.
                     * Por isso, o bloqueio deve ser calculado explicitamente com
                     * America/Sao_Paulo, sem depender do timezone do servidor,
                     * do MySQL NOW() ou da flag SQL palpite_aberto.
                     */
                    $timezone_bolao = new DateTimeZone('America/Sao_Paulo');
                    $data_hora_jogo_obj = false;

                    if (!empty($data_hora_jogo)) {
                        $data_hora_jogo_normalizada = trim((string) $data_hora_jogo);

                        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $data_hora_jogo_normalizada)) {
                            $data_hora_jogo_normalizada .= ':00';
                        }

                        $data_hora_jogo_obj = DateTimeImmutable::createFromFormat(
                            'Y-m-d H:i:s',
                            $data_hora_jogo_normalizada,
                            $timezone_bolao
                        );

                        if (!$data_hora_jogo_obj) {
                            $data_hora_jogo_obj = new DateTimeImmutable(
                                $data_hora_jogo_normalizada,
                                $timezone_bolao
                            );
                        }
                    }

                    $timestamp_jogo = $data_hora_jogo_obj
                        ? $data_hora_jogo_obj->getTimestamp()
                        : false;

                    $timestamp_bloqueio = $timestamp_jogo
                        ? $timestamp_jogo - ($minutos_bloqueio_palpite * MINUTE_IN_SECONDS)
                        : false;

                    $agora_bolao = new DateTimeImmutable('now', $timezone_bolao);
                    $timestamp_agora_bolao = $agora_bolao->getTimestamp();

                    $palpite_aberto_por_horario = $timestamp_bloqueio
                        ? $timestamp_agora_bolao < $timestamp_bloqueio
                        : false;

                    // Evita bloqueio antecipado por flag SQL baseada em NOW() de outro fuso.
                    $status_jogo_atual = strtoupper((string) ($jogo->status_jogo ?? ''));
                    $status_jogo_publico = $m360_txt(
                        'STATUS_JOGO',
                        $status_jogo_atual,
                        $status_jogo_atual,
                        'GLOBAL',
                        false
                    );

                    $jogo_mandante_nome = $m360_txt(
                        'TIME',
                        (string) ($jogo->mandante ?? ''),
                        (string) ($jogo->mandante ?? ''),
                        'GLOBAL'
                    );

                    $jogo_visitante_nome = $m360_txt(
                        'TIME',
                        (string) ($jogo->visitante ?? ''),
                        (string) ($jogo->visitante ?? ''),
                        'GLOBAL'
                    );

                    $status_abertos = [
                        'TIMED',
                        'SCHEDULED',
                        'NOT_STARTED',
                        'NS',
                    ];

                    $palpite_aberto_por_status = in_array(
                        $status_jogo_atual,
                        $status_abertos,
                        true
                    );

                    // O confronto permanece visível, mas só aceita palpites
                    // quando os dois times reais estiverem definidos.
                    $mandante_id_jogo = isset($jogo->mandante_id) ? (int) $jogo->mandante_id : 0;
                    $visitante_id_jogo = isset($jogo->visitante_id) ? (int) $jogo->visitante_id : 0;

                    $confronto_definido = $mandante_id_jogo > 0
                        && $visitante_id_jogo > 0
                        && $mandante_id_jogo !== 9999
                        && $visitante_id_jogo !== 9999
                        && $mandante_id_jogo !== $visitante_id_jogo;

                    $palpite_aberto = $confronto_definido
                        && $bolao_aberto
                        && $palpite_aberto_por_status
                        && $palpite_aberto_por_horario;

                    $hora_bloqueio_palpite = $timestamp_bloqueio
                        ? wp_date('H:i', $timestamp_bloqueio, $timezone_bolao)
                        : '';

                    /*
                     * Sprint 6 - Resultado e pontuação no card da agenda.
                     * Quando o jogo já tiver resultado confirmado/apurado, o card passa
                     * a mostrar o resultado oficial, o palpite do usuário e os pontos.
                     */
                    $resultado_mandante = $jogo->resultado_placar_mandante ?? null;
                    $resultado_visitante = $jogo->resultado_placar_visitante ?? null;
                    $resultado_status_codigo = strtoupper((string) ($jogo->resultado_status_codigo ?? ''));
                    $resultado_permite_apuracao = (int) ($jogo->resultado_permite_apuracao ?? 0);

                    $jogo_tem_resultado = $resultado_mandante !== null
                        && $resultado_visitante !== null
                        && ($resultado_permite_apuracao === 1 || in_array($resultado_status_codigo, ['FINAL_API', 'FINAL_CONFIRMADO', 'CORRIGIDO'], true));

                    $pontos_palpite = null;
                    if ($palpite_salvo && isset($palpite_salvo->pontos_total) && $palpite_salvo->pontos_total !== null) {
                        $pontos_palpite = (int) $palpite_salvo->pontos_total;
                    }

                    // ============================================================
                    // Sprint 7 — Ranking por jogo no próprio card finalizado.
                    // ============================================================
                    $jogo_id_ranking = (int) (
                        $jogo->jogo_id
                        ?? $jogo->id
                        ?? $jogo->partida_id
                        ?? 0
                    );

                    $jogo_bolao_competicao_id = (int) (
                        $jogo->bolao_competicao_id
                        ?? $bolao_competicao_id
                        ?? $resumo_usuario->bolao_competicao_id
                        ?? $resumo_dashboard->bolao_competicao_id
                        ?? 0
                    );

                    $ranking_jogo = ($jogo_tem_resultado && $jogo_id_ranking > 0)
                        ? $m360_get_ranking_por_jogo($jogo_bolao_competicao_id, $jogo_id_ranking, 5)
                        : [];
                    ?>

                    <div class="m360-bolao-jogo">

                        <!-- ============================================================
                             Informações principais do jogo
                             ============================================================ -->
                        <div class="m360-bolao-jogo-info">

                            <!-- ============================================================
                                 Sprint 5.6:
                                 - Exibe bandeiras/escudos vindos do DW quando disponíveis;
                                 - Valoriza visualmente os nomes das seleções;
                                 - Informa o horário limite para envio/alteração do palpite.
                                 ============================================================ -->
                            <div class="m360-bolao-confronto">

                                <div class="m360-bolao-time m360-bolao-time-mandante">
                                    <?php if (!empty($jogo->mandante_bandeira_url)): ?>
                                        <img
                                            class="m360-bolao-bandeira m360-bandeira"
                                            src="<?php echo esc_url($jogo->mandante_bandeira_url); ?>"
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                            width="26"
                                            height="18"
                                            style="width:26px!important;height:18px!important;max-width:26px!important;max-height:18px!important;object-fit:cover!important;display:inline-block!important;flex:0 0 26px!important;"
                                        >
                                    <?php endif; ?>

                                    <span><?php echo esc_html($jogo_mandante_nome); ?></span>
                                </div>

                                <?php if ($jogo_tem_resultado): ?>
                                    <span class="m360-bolao-placar-confronto">
                                        <?php echo esc_html((int) $resultado_mandante); ?>
                                        ×
                                        <?php echo esc_html((int) $resultado_visitante); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="m360-bolao-versus">×</span>
                                <?php endif; ?>

                                <div class="m360-bolao-time m360-bolao-time-visitante">
                                    <?php if (!empty($jogo->visitante_bandeira_url)): ?>
                                        <img
                                            class="m360-bolao-bandeira m360-bandeira"
                                            src="<?php echo esc_url($jogo->visitante_bandeira_url); ?>"
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                            width="26"
                                            height="18"
                                            style="width:26px!important;height:18px!important;max-width:26px!important;max-height:18px!important;object-fit:cover!important;display:inline-block!important;flex:0 0 26px!important;"
                                        >
                                    <?php endif; ?>

                                    <span><?php echo esc_html($jogo_visitante_nome); ?></span>
                                </div>

                            </div>

                            <span class="m360-bolao-jogo-meta">
                                <?php echo esc_html($jogo->hora_jogo); ?>
                                ·
                                <?php echo esc_html($jogo->estadio_nome); ?>
                                <?php if (!empty($status_jogo_publico)): ?>
                                    · <?php echo esc_html($status_jogo_publico); ?>
                                <?php endif; ?>
                            </span>

                            <?php if ($jogo_tem_resultado): ?>
                                <span class="m360-bolao-fechamento-palpite m360-bolao-fechamento-resultado">
                                    <?php echo $m360_txt_esc('STATUS_JOGO', 'FINISHED', 'Resultado final', 'GLOBAL'); ?>
                                    <?php echo esc_html((int) $resultado_mandante); ?>
                                    ×
                                    <?php echo esc_html((int) $resultado_visitante); ?>
                                </span>
                            <?php elseif (!$confronto_definido): ?>
                                <span class="m360-bolao-fechamento-palpite">
                                    <?php echo esc_html($m360_inline('Aguardando definição dos times', 'Waiting for teams to be confirmed', 'Esperando la definición de los equipos')); ?>
                                </span>
                            <?php elseif (!empty($hora_bloqueio_palpite)): ?>
                                <span class="m360-bolao-fechamento-palpite">
                                    <?php echo esc_html($m360_inline('Palpites até', 'Predictions until', 'Pronósticos hasta')); ?> <?php echo esc_html($hora_bloqueio_palpite); ?>
                                </span>
                            <?php endif; ?>

                        </div>

                        <?php if ($usuario_logado && $palpite_aberto): ?>

                            <!-- ============================================================
                                 Usuário logado + jogo aberto:
                                 permite salvar ou atualizar o palpite.
                                 ============================================================ -->
                            <div class="m360-bolao-palpite-area">

                                <div class="m360-bolao-palpite-demo">

                                    <input
                                        type="number"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        min="0"
                                        max="20"
                                        step="1"
                                        placeholder="0"
                                        autocomplete="off"
                                        onkeydown="return ['Backspace','Delete','Tab','Escape','Enter','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes(event.key) || ((event.ctrlKey || event.metaKey) &amp;&amp; ['a','c','v','x'].includes(event.key.toLowerCase())) || /^[0-9]$/.test(event.key);"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 2); if (this.value !== '' &amp;&amp; Number(this.value) > 20) this.value = '20';"
                                        class="m360-palpite-mandante m360-palpite-placar"
                                        value="<?php echo esc_attr($palpite_salvo->placar_mandante ?? ''); ?>"
                                    >

                                    <span>x</span>

                                    <input
                                        type="number"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        min="0"
                                        max="20"
                                        step="1"
                                        placeholder="0"
                                        autocomplete="off"
                                        onkeydown="return ['Backspace','Delete','Tab','Escape','Enter','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes(event.key) || ((event.ctrlKey || event.metaKey) &amp;&amp; ['a','c','v','x'].includes(event.key.toLowerCase())) || /^[0-9]$/.test(event.key);"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 2); if (this.value !== '' &amp;&amp; Number(this.value) > 20) this.value = '20';"
                                        class="m360-palpite-visitante m360-palpite-placar"
                                        value="<?php echo esc_attr($palpite_salvo->placar_visitante ?? ''); ?>"
                                    >

                                    <button
                                        type="button"
                                        class="m360-bolao-btn-secundario m360-bolao-salvar-palpite"
                                        data-jogo-id="<?php echo esc_attr($jogo->jogo_id); ?>"
                                    >
                                        <?php echo esc_html($texto_botao_palpite); ?>
                                    </button>

                                </div>

                                <?php if ($palpite_salvo): ?>
                                    <div class="m360-bolao-feedback sucesso">
                                        <?php echo esc_html($m360_inline('Palpite já registrado.', 'Prediction already saved.', 'Pronóstico ya registrado.')); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="m360-bolao-feedback"></div>
                                <?php endif; ?>

                            </div>

                        <?php elseif (!$usuario_logado && $palpite_aberto): ?>

                            <!-- ============================================================
                                 Visitante + jogo aberto:
                                 exibe chamada para login/acesso.
                                 ============================================================ -->
                            <a class="m360-bolao-btn-secundario"
                               href="#m360-bolao-login">
                                <?php echo $m360_txt_esc('MENSAGEM', 'login_para_palpitar', 'Quero palpitar', 'BOLAO'); ?>
                            </a>

                        <?php else: ?>

                            <!-- ============================================================
                                 Jogo bloqueado ou finalizado:
                                 não permite novo palpite nem edição.
                                 Quando houver resultado/apuração, exibe também o resultado final
                                 e a pontuação obtida pelo usuário no jogo.
                                 ============================================================ -->
                            <div class="m360-bolao-bloqueado <?php echo $jogo_tem_resultado ? 'm360-bolao-bloqueado-apurado' : ''; ?>">

                                <span class="m360-bolao-cadeado"><?php echo $jogo_tem_resultado ? '🏁' : (!$confronto_definido ? '⏳' : '🔒'); ?></span>

                                <div>
                                    <?php if (!$confronto_definido): ?>
                                        <strong><?php echo esc_html($m360_inline('Aguardando definição dos times', 'Waiting for teams to be confirmed', 'Esperando la definición de los equipos')); ?></strong>
                                        <small><?php echo esc_html($m360_inline('Os palpites serão liberados automaticamente quando o confronto estiver completo.', 'Predictions will open automatically when both teams are confirmed.', 'Los pronósticos se habilitarán automáticamente cuando se confirmen ambos equipos.')); ?></small>
                                    <?php elseif ($jogo_tem_resultado): ?>
                                        <?php if ($palpite_salvo): ?>
                                            <strong>
                                                <?php echo esc_html($m360_inline('Seu palpite:', 'Your prediction:', 'Tu pronóstico:')); ?>
                                                <?php echo esc_html($palpite_salvo->placar_mandante); ?>
                                                ×
                                                <?php echo esc_html($palpite_salvo->placar_visitante); ?>
                                                <?php if ($pontos_palpite !== null): ?>
                                                    · <?php echo esc_html($pontos_palpite); ?> pts
                                                <?php endif; ?>
                                            </strong>
                                        <?php else: ?>
                                            <strong><?php echo esc_html($m360_inline('Você não registrou palpite para este jogo.', 'You did not save a prediction for this match.', 'No guardaste un pronóstico para este partido.')); ?></strong>
                                        <?php endif; ?>
                                    <?php elseif (!$bolao_aberto): ?>
                                        <?php if ($bolao_estado_operacional === 'RASCUNHO'): ?>
                                            <strong><?php echo esc_html($m360_inline('Bolão em preparação', 'Pool in preparation', 'Quiniela en preparación')); ?></strong>
                                            <small><?php echo esc_html($m360_inline(
                                                'Este jogo é futuro. Os palpites serão liberados somente após a abertura oficial do bolão.',
                                                'This is a future match. Predictions will open only after the pool is officially opened.',
                                                'Este es un partido futuro. Los pronósticos se habilitarán únicamente después de la apertura oficial de la quiniela.'
                                            )); ?></small>
                                        <?php else: ?>
                                            <strong><?php echo esc_html($m360_inline('Palpites indisponíveis', 'Predictions unavailable', 'Pronósticos no disponibles')); ?></strong>
                                            <small><?php echo esc_html($m360_inline(
                                                'O estado atual do bolão não permite novos palpites.',
                                                'The current pool status does not allow new predictions.',
                                                'El estado actual de la quiniela no permite nuevos pronósticos.'
                                            )); ?></small>
                                        <?php endif; ?>
                                    <?php elseif ($palpite_salvo): ?>
                                        <strong>
                                            <?php echo esc_html($m360_inline('Seu palpite:', 'Your prediction:', 'Tu pronóstico:')); ?> <?php echo esc_html($palpite_salvo->placar_mandante); ?>
                                            ×
                                            <?php echo esc_html($palpite_salvo->placar_visitante); ?>
                                        </strong>
                                        <small><?php echo esc_html($m360_inline('Palpites encerrados para este jogo.', 'Predictions are closed for this match.', 'Los pronósticos están cerrados para este partido.')); ?></small>
                                    <?php else: ?>
                                        <strong><?php echo esc_html($m360_inline('Palpites encerrados', 'Predictions closed', 'Pronósticos cerrados')); ?></strong>
                                        <small><?php echo esc_html($m360_inline('Este jogo já foi iniciado e não aceita novos palpites.', 'This match has already started and no longer accepts new predictions.', 'Este partido ya comenzó y no acepta nuevos pronósticos.')); ?></small>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <?php if ($jogo_tem_resultado): ?>
                                <div class="m360-bolao-ranking-jogo-acoes">
                                    <?php if (!empty($ranking_jogo)): ?>
                                        <button
                                            type="button"
                                            class="m360-bolao-btn-ranking-jogo"
                                            data-ranking-jogo-id="<?php echo esc_attr($jogo_id_ranking); ?>"
                                        >
                                            <?php echo esc_html($m360_inline('Ver ranking do jogo', 'View match ranking', 'Ver clasificación del partido')); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="m360-bolao-ranking-jogo-sem-dados">
                                            <?php echo esc_html($m360_inline('Ranking em processamento', 'Ranking processing', 'Clasificación en procesamiento')); ?>
                                        </span>
                                    <?php endif; ?>

                                    <a class="m360-bolao-link-agenda" href="<?php echo esc_url($m360_url_competicao); ?>">
                                        <?php echo esc_html($m360_inline('Agenda de jogos', 'Match schedule', 'Agenda de partidos')); ?>
                                    </a>
                                </div>

                                <?php if (!empty($ranking_jogo)): ?>
                                    <div
                                        class="m360-bolao-ranking-jogo-box"
                                        id="m360-ranking-jogo-<?php echo esc_attr($jogo_id_ranking); ?>"
                                        hidden
                                    >
                                        <div class="m360-bolao-ranking-jogo-topo">
                                            <strong><?php echo esc_html($m360_inline('Ranking do jogo', 'Match ranking', 'Clasificación del partido')); ?></strong>
                                            <span>
                                                <?php echo esc_html($jogo_mandante_nome); ?>
                                                <?php echo esc_html((int) $resultado_mandante); ?> × <?php echo esc_html((int) $resultado_visitante); ?>
                                                <?php echo esc_html($jogo_visitante_nome); ?>
                                            </span>
                                        </div>

                                        <ol class="m360-bolao-ranking-jogo-lista">
                                            <?php foreach ($ranking_jogo as $linha_jogo): ?>
                                                <?php
                                                    $nome_ranking_jogo = $linha_jogo->nome_exibicao
                                                        ?? $linha_jogo->usuario_nome
                                                        ?? $linha_jogo->nome_usuario
                                                        ?? $linha_jogo->participante
                                                        ?? $m360_inline('Participante', 'Participant', 'Participante');
                                                ?>
                                                <li>
                                                    <span class="m360-bolao-ranking-jogo-pos">
                                                        <?php echo esc_html((int) ($linha_jogo->posicao ?? 0)); ?>º
                                                    </span>

                                                    <span class="m360-bolao-ranking-jogo-nome">
                                                        <?php echo esc_html($nome_ranking_jogo); ?>
                                                    </span>

                                                    <?php
                                                        $palpite_ranking_mandante = isset($linha_jogo->palpite_mandante) ? (int) $linha_jogo->palpite_mandante : null;
                                                        $palpite_ranking_visitante = isset($linha_jogo->palpite_visitante) ? (int) $linha_jogo->palpite_visitante : null;
                                                        $palpite_ranking_valido = $palpite_ranking_mandante !== null
                                                            && $palpite_ranking_visitante !== null
                                                            && $palpite_ranking_mandante >= 0
                                                            && $palpite_ranking_visitante >= 0
                                                            && $palpite_ranking_mandante <= 30
                                                            && $palpite_ranking_visitante <= 30;
                                                    ?>
                                                    <span class="m360-bolao-ranking-jogo-palpite">
                                                        <?php if ($palpite_ranking_valido): ?>
                                                            <?php echo esc_html($palpite_ranking_mandante); ?> × <?php echo esc_html($palpite_ranking_visitante); ?>
                                                        <?php else: ?>
                                                            —
                                                        <?php endif; ?>
                                                    </span>

                                                    <strong class="m360-bolao-ranking-jogo-pontos">
                                                        <?php echo esc_html((int) ($linha_jogo->pontos_total ?? 0)); ?> pts
                                                    </strong>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p><?php echo esc_html($m360_inline('Nenhum jogo encontrado para esta data.', 'No matches found for this date.', 'No se encontraron partidos para esta fecha.')); ?></p>

            <?php endif; ?>

        <?php else: ?>

            <p><?php echo esc_html($m360_inline('Nenhuma data de jogo encontrada para esta competição.', 'No match dates found for this competition.', 'No se encontraron fechas de partidos para esta competición.')); ?></p>

        <?php endif; ?>
    </section>




    <!-- ============================================================
         Minhas Ligas
         Sprint 3: criação de ligas privadas, entrada por código
         e compartilhamento de convite.
         ============================================================ -->
    <section class="m360-bolao-card m360-bolao-ligas-card">
        <h2><?php echo $m360_txt_esc('LABEL', 'minhas_ligas', 'Minhas Ligas', 'BOLAO'); ?></h2>

        <?php if (!$bolao_aberto): ?>

            <p class="m360-bolao-aviso">
                <?php echo esc_html($m360_inline(
                    'A criação e a entrada em ligas estão encerradas. Usuários autenticados ainda podem consultar suas ligas históricas.',
                    'Creating and joining leagues is closed. Signed-in users can still view their historical leagues.',
                    'La creación y el ingreso a ligas están cerrados. Los usuarios autenticados aún pueden consultar sus ligas históricas.'
                )); ?>
            </p>

        <?php elseif (!$usuario_logado): ?>

            <!-- ============================================================
                 Visitante: chamada para login antes de criar/entrar em liga.
                 ============================================================ -->
            <!-- ============================================================
                 Visitante: login Google antes da utilização das ligas.
                 Mantido separado para facilitar futuras evoluções.
                 ============================================================ -->
            <p>
                <?php echo $m360_txt_esc('MENSAGEM', 'ligas_login_descricao', 'Faça login com Google para criar ligas privadas, convidar seus amigos e disputar rankings exclusivos.', 'BOLAO'); ?>
            </p>

            <div class="m360-bolao-login-social">
                <?php echo $m360_google_login_shortcode(); ?>
            </div>

        <?php else: ?>

            <!-- ============================================================
                 Formulários de criação e entrada em ligas.
                 A gravação será feita via AJAX:
                 - m360_criar_liga
                 - m360_entrar_liga
                 ============================================================ -->
            <div class="m360-bolao-ligas-acoes">

                <div class="m360-bolao-liga-box">
                    <h3><?php echo $m360_txt_esc('LABEL', 'criar_liga_privada', 'Criar liga privada', 'BOLAO'); ?></h3>
                    <p><?php echo $m360_txt_esc('MENSAGEM', 'criar_liga_privada_desc', 'Crie um grupo fechado e compartilhe o código com seus amigos.', 'BOLAO'); ?></p>

                    <div class="m360-bolao-liga-form">
                        <input
                            type="text"
                            class="m360-liga-nome"
                            maxlength="150"
                            placeholder="<?php echo esc_attr($m360_inline('Ex.: Família Pires', 'Ex.: Family League', 'Ej.: Liga de amigos')); ?>"
                        >

                        <button
                            type="button"
                            class="m360-bolao-btn-secundario m360-bolao-criar-liga"
                        >
                            <?php echo $m360_txt_esc('BOTAO', 'criar_liga', 'Criar liga', 'BOLAO'); ?>
                        </button>
                    </div>

                    <div class="m360-bolao-liga-feedback m360-bolao-criar-liga-feedback"></div>
                </div>

                <div class="m360-bolao-liga-box">
                    <h3><?php echo $m360_txt_esc('LABEL', 'entrar_com_codigo', 'Entrar com código', 'BOLAO'); ?></h3>
                    <p><?php echo $m360_txt_esc('MENSAGEM', 'entrar_com_codigo_desc', 'Recebeu um convite? Informe o código da liga.', 'BOLAO'); ?></p>

                    <div class="m360-bolao-liga-form">
                        <input
                            type="text"
                            class="m360-liga-codigo"
                            maxlength="30"
                            placeholder="Ex.: M360-A7K9"
                        >

                        <button
                            type="button"
                            class="m360-bolao-btn-secundario m360-bolao-entrar-liga"
                        >
                            <?php echo $m360_txt_esc('BOTAO', 'entrar_na_liga', 'Entrar', 'BOLAO'); ?>
                        </button>
                    </div>

                    <div class="m360-bolao-liga-feedback m360-bolao-entrar-liga-feedback"></div>
                </div>

            </div>

        <?php endif; ?>

        <?php if ($usuario_logado): ?>

            <!-- ============================================================
                 Lista de ligas do usuário.
                 Cada liga exibe papel, código e link de compartilhamento.
                 ============================================================ -->
            <div class="m360-bolao-minhas-ligas">

                <h3 class="m360-bolao-ligas-subtitulo"><?php echo $m360_txt_esc('LABEL', 'suas_ligas', 'Suas ligas', 'BOLAO'); ?></h3>

                <?php if (!empty($minhas_ligas)): ?>

                    <?php foreach ($minhas_ligas as $liga): ?>

                        <?php
                        // ============================================================
                        // Monta link compartilhável da liga.
                        // No futuro, esse parâmetro poderá autoinscrever o usuário
                        // após login social.
                        // ============================================================
                        $link_liga = add_query_arg(
                            'liga',
                            $liga->codigo_convite,
                            get_permalink()
                        );

                        // ============================================================
                        // Texto de convite do WhatsApp
                        // Observações:
                        // - Sem emojis e sem acentos para evitar caracteres quebrados
                        //   em alguns navegadores/dispositivos.
                        // - Quebras de linha preservadas com \n.
                        // - Texto genérico para funcionar com qualquer competição.
                        // ============================================================
                                if ($m360_lang === 'en-US') {
                                    $texto_whatsapp =
                                        "🏆 Bolao 360 Invitation 👉 " .
                                        "League: " . $liga->nome . " 👉 " .
                                        "Code: " . $liga->codigo_convite . " 👉 " .
                                        "Open: " . $link_liga . " 👉 " .
                                        "Sign in with Google and join!";
                                } elseif ($m360_lang === 'es-ES') {
                                    $texto_whatsapp =
                                        "🏆 Invitación Bolao 360 👉 " .
                                        "Liga: " . $liga->nome . " 👉 " .
                                        "Código: " . $liga->codigo_convite . " 👉 " .
                                        "Abrir: " . $link_liga . " 👉 " .
                                        "Inicia sesión con Google y participa!";
                                } else {
                                    $texto_whatsapp =
                                        "🏆 Convite Bolao 360 👉 " .
                                        "Liga: " . $liga->nome . " 👉 " .
                                        "Codigo: " . $liga->codigo_convite . " 👉 " .
                                        "Acesse: " . $link_liga . " 👉 " .
                                        "Faca login com Google e participe!";
                                }

								$link_whatsapp = 'https://api.whatsapp.com/send?text=' . rawurlencode($texto_whatsapp);
								// $link_whatsapp = 'https://wa.me/?text=' . rawurlencode($texto_whatsapp);
						
                        ?>

                        <div class="m360-bolao-liga-item">

                            <div class="m360-bolao-liga-info">
                                <strong><?php echo esc_html($liga->nome); ?></strong>

                                <span>
                                    <?php echo esc_html($liga->papel === 'DONO' ? '👑 ' . $m360_txt('LABEL', 'dono', 'Dono', 'BOLAO') : '👤 ' . $m360_txt('LABEL', 'participante', 'Participante', 'BOLAO')); ?>
                                </span>
                            </div>

                            <div class="m360-bolao-liga-convite">
                                <span><?php echo $m360_txt_esc('LABEL', 'codigo', 'Código', 'BOLAO'); ?></span>
                                <strong><?php echo esc_html($liga->codigo_convite); ?></strong>
                            </div>

                            <div class="m360-bolao-liga-acoes">
                                <a
                                    class="m360-bolao-btn-whatsapp"
                                    href="<?php echo esc_url($link_whatsapp); ?>"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <?php echo $m360_txt_esc('BOTAO', 'compartilhar', 'Compartilhar', 'BOLAO'); ?>
                                </a>
                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="m360-bolao-aviso">
                        <?php echo $m360_txt_esc('MENSAGEM', 'sem_liga_privada', 'Você ainda não participa de nenhuma liga privada nesta competição.', 'BOLAO'); ?>
                    </p>

                <?php endif; ?>

            </div>

        <?php endif; ?>
    </section>


    <!-- ============================================================
         Ranking Geral
         Sprint 2: Top 10 público do Bolão FIFA 2026.
         ============================================================ -->
    <section class="m360-bolao-card m360-bolao-ranking-card">
        <h2><?php echo $m360_txt_esc('LABEL', 'ranking_geral', 'Ranking Geral', 'BOLAO'); ?></h2>

        <?php if (!empty($ranking_geral)): ?>

            <!-- ============================================================
                 Sprint 2.5 UX: ranking em formato de tabela/card responsivo.
                 Top 3 recebe medalhas; demais posições mantêm ordinal.
                 ============================================================ -->
            <div class="m360-bolao-ranking-tabela" role="table" aria-label="<?php echo esc_attr($m360_inline('Ranking geral do bolão', 'Overall prediction ranking', 'Clasificación general del bolão')); ?>">

                <div class="m360-bolao-ranking-cabecalho" role="row">
                    <span role="columnheader"><?php echo $m360_txt_esc('LABEL', 'posicao_abrev', 'Pos', 'BOLAO'); ?></span>
                    <span role="columnheader"><?php echo $m360_txt_esc('LABEL', 'participante', 'Participante', 'BOLAO'); ?></span>
                    <span role="columnheader"><?php echo $m360_txt_esc('LABEL', 'pontos_abrev', 'Pts', 'BOLAO'); ?></span>
                    <span role="columnheader"><?php echo $m360_txt_esc('LABEL', 'placares_exatos_abrev', 'Exatos', 'BOLAO'); ?></span>
                </div>

                <?php foreach ($ranking_geral as $linha_ranking): ?>

                    <?php
                    // ============================================================
                    // Medalhas visuais para os três primeiros colocados.
                    // ============================================================
                    $ranking_posicao = (int) ($linha_ranking->posicao ?? 0);

                    if ($ranking_posicao === 1) {
                        $ranking_selo = '🥇';
                    } elseif ($ranking_posicao === 2) {
                        $ranking_selo = '🥈';
                    } elseif ($ranking_posicao === 3) {
                        $ranking_selo = '🥉';
                    } else {
                        $ranking_selo = $ranking_posicao . 'º';
                    }
                    ?>

                    <div class="m360-bolao-ranking-linha" role="row">

                        <div class="m360-bolao-ranking-posicao" role="cell">
                            <?php echo esc_html($ranking_selo); ?>
                        </div>

                        <div class="m360-bolao-ranking-usuario" role="cell">
                            <strong><?php echo esc_html($linha_ranking->nome_exibicao); ?></strong>
                            <span>
                                <?php echo esc_html($linha_ranking->qtd_palpites ?? 0); ?> <?php echo $m360_txt_esc('LABEL', 'palpites_apurados', 'palpites apurados', 'BOLAO'); ?>
                            </span>
                        </div>

                        <div class="m360-bolao-ranking-pontos" role="cell">
                            <strong><?php echo esc_html($linha_ranking->pontos_total); ?></strong>
                            <span><?php echo $m360_txt_esc('LABEL', 'pontos_abrev', 'pts', 'BOLAO'); ?></span>
                        </div>

                        <div class="m360-bolao-ranking-exatos" role="cell">
                            <?php echo esc_html($linha_ranking->qtd_placar_exato ?? 0); ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p class="m360-bolao-aviso">
                <?php echo esc_html($m360_inline('O ranking será exibido após a primeira apuração de resultados.', 'The ranking will be shown after the first result calculation.', 'La clasificación se mostrará después del primer cálculo de resultados.')); ?>
            </p>

        <?php endif; ?>
    </section>

    <style>
        /* Sprint 7.1 — ranking do jogo em linha abaixo do card, sem coluna lateral. */
        .m360-bolao .m360-bolao-jogo {
            display: grid !important;
            grid-template-columns: minmax(0, 1.15fr) minmax(260px, .85fr) auto !important;
            gap: 18px 22px !important;
            align-items: center !important;
        }

        .m360-bolao .m360-bolao-jogo-info {
            min-width: 0 !important;
        }

        .m360-bolao .m360-bolao-bloqueado,
        .m360-bolao .m360-bolao-palpite-area,
        .m360-bolao .m360-bolao-btn-secundario {
            min-width: 0 !important;
        }

        .m360-bolao-ranking-jogo-acoes {
            grid-column: 3 / 4 !important;
            grid-row: 1 !important;
            display: flex !important;
            justify-content: flex-end !important;
            gap: 10px !important;
            margin-top: 0 !important;
            flex-wrap: wrap !important;
            align-self: center !important;
        }

        .m360-bolao-btn-ranking-jogo,
        .m360-bolao-link-agenda {
            border: 0;
            border-radius: 999px;
            padding: 10px 14px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .m360-bolao-btn-ranking-jogo {
            background: #991b1b;
            color: #fff;
        }

        .m360-bolao-link-agenda {
            background: #f3f4f6;
            color: #374151;
        }

        .m360-bolao-ranking-jogo-sem-dados {
            border-radius: 999px;
            padding: 10px 14px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            background: #f3f4f6;
            color: #6b7280;
        }

        .m360-bolao-ranking-jogo-box {
            grid-column: 1 / -1 !important;
            grid-row: 2 !important;
            width: 100% !important;
            margin-top: 6px !important;
            padding: 16px !important;
            border-radius: 16px !important;
            background: #fff !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .06) !important;
        }

        .m360-bolao-ranking-jogo-topo {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            color: #374151;
        }

        .m360-bolao-ranking-jogo-topo strong {
            color: #991b1b;
            font-size: 16px;
        }

        .m360-bolao-ranking-jogo-topo span {
            color: #6b7280;
            font-size: 13px;
            text-align: right;
        }

        .m360-bolao-ranking-jogo-lista {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .m360-bolao-ranking-jogo-lista li {
            display: grid;
            grid-template-columns: 42px 1fr auto auto;
            gap: 10px;
            align-items: center;
            padding: 10px 0;
            border-top: 1px solid #f1f5f9;
        }

        .m360-bolao-ranking-jogo-pos {
            font-weight: 900;
            color: #991b1b;
        }

        .m360-bolao-ranking-jogo-nome {
            font-weight: 800;
            color: #374151;
        }

        .m360-bolao-ranking-jogo-palpite {
            color: #6b7280;
            font-size: 13px;
            white-space: nowrap;
        }

        .m360-bolao-ranking-jogo-pontos {
            color: #15803d;
            white-space: nowrap;
        }

        .m360-bolao-ranking-jogo-vazio {
            padding: 12px;
            border-radius: 12px;
            background: #f9fafb;
            color: #6b7280;
            border: 1px dashed #d1d5db;
        }

        .m360-bolao-ligas-subtitulo {
            margin: 28px 0 14px !important;
            font-size: 18px !important;
            font-weight: 800 !important;
            color: #374151 !important;
        }

        @media (max-width: 980px) {
            .m360-bolao .m360-bolao-jogo {
                grid-template-columns: 1fr !important;
            }

            .m360-bolao-ranking-jogo-acoes,
            .m360-bolao-ranking-jogo-box {
                grid-column: 1 / -1 !important;
                grid-row: auto !important;
                justify-content: flex-start !important;
            }
        }

        @media (max-width: 720px) {
            .m360-bolao .m360-bolao-jogo {
                grid-template-columns: 1fr !important;
            }

            .m360-bolao-ranking-jogo-acoes,
            .m360-bolao-ranking-jogo-box {
                grid-column: 1 / -1 !important;
                grid-row: auto !important;
            }

            .m360-bolao-ranking-jogo-acoes {
                justify-content: stretch;
            }

            .m360-bolao-btn-ranking-jogo,
            .m360-bolao-link-agenda {
                width: 100%;
            }

            .m360-bolao-ranking-jogo-topo {
                flex-direction: column;
            }

            .m360-bolao-ranking-jogo-topo span {
                text-align: left;
            }

            .m360-bolao-ranking-jogo-lista li {
                grid-template-columns: 36px 1fr;
            }

            .m360-bolao-ranking-jogo-palpite,
            .m360-bolao-ranking-jogo-pontos {
                grid-column: 2;
            }
        }
    </style>

    <script>
        document.addEventListener('click', function (event) {
            const btn = event.target.closest('.m360-bolao-btn-ranking-jogo');

            if (!btn) {
                return;
            }

            const jogoId = btn.getAttribute('data-ranking-jogo-id');
            const box = document.getElementById('m360-ranking-jogo-' + jogoId);

            if (!box) {
                return;
            }

            const aberto = !box.hasAttribute('hidden');

            if (aberto) {
                box.setAttribute('hidden', 'hidden');
                btn.textContent = <?php echo wp_json_encode($m360_inline('Ver ranking do jogo', 'View match ranking', 'Ver clasificación del partido')); ?>;
            } else {
                box.removeAttribute('hidden');
                btn.textContent = <?php echo wp_json_encode($m360_inline('Ocultar ranking', 'Hide ranking', 'Ocultar clasificación')); ?>;
            }
        });
    </script>

</div>
