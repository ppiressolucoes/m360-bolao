<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Landing comercial. O DW é somente leitura; o WordPress fornece somente
 * o estado administrado e a página pública de cada bolão.
 */
class Mengao360_Bolao_Product_Hub {

    public static function render($atts) {
        return self::render_sections([
            'hero', 'menu', 'overview', 'competitions', 'benefits',
            'plans', 'how', 'trust', 'faq', 'cta',
        ], $atts, 'mega_bolao_360_home');
    }

    public static function render_component($component, $atts = []) {
        $allowed = [
            'hero', 'menu', 'overview', 'competitions', 'benefits',
            'plans', 'how', 'trust', 'faq', 'cta',
        ];
        if (!in_array($component, $allowed, true)) {
            return '';
        }
        return self::render_sections([$component], $atts, 'm360_hub_' . $component);
    }

    private static function render_sections($sections, $atts, $shortcode_tag) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();

        $atts = shortcode_atts([
            'idioma' => '',
            'competicoes' => 'fifa-world-cup,brasileirao-serie-a,copa-libertadores,copa-do-brasil,cl,bl1,ded,pd,fl1,elc,ppl,ec,sa,pl',
            'free_url' => '#m360-cta',
            'jogador_url' => '#m360-cta',
            'dirigente_url' => '#m360-cta',
            'cta_url' => '',
        ], $atts, $shortcode_tag);

        $lang = sanitize_text_field((string) $atts['idioma']);
        if (!in_array($lang, ['pt-BR', 'en-US'], true)) {
            $lang = function_exists('m360_bolao_get_lang') ? m360_bolao_get_lang() : 'pt-BR';
        }
        if (!in_array($lang, ['pt-BR', 'en-US'], true)) {
            $lang = 'pt-BR';
        }

        $slugs = array_values(array_filter(array_unique(array_map(
            'sanitize_title',
            explode(',', (string) $atts['competicoes'])
        ))));
        $hub_copy = self::copy($lang);
        $needs_catalog = (bool) array_intersect(
            $sections,
            ['hero', 'overview', 'competitions']
        );
        $hub_items = $needs_catalog ? self::catalog($slugs, $lang) : [];
        $hub_open_count = count(array_filter($hub_items, function ($item) {
            return $item['state'] === 'ABERTO';
        }));
        $hub_total_games = array_sum(array_column($hub_items, 'total_games'));
        $hub_future_games = array_sum(array_column($hub_items, 'future_games'));
        $hub_next_item = null;
        foreach ($hub_items as $item) {
            if (empty($item['next_game'])) {
                continue;
            }
            if ($hub_next_item === null
                || strtotime($item['next_game']) < strtotime($hub_next_item['next_game'])) {
                $hub_next_item = $item;
            }
        }

        $hub_sections = array_fill_keys($sections, true);
        $show = static function ($section) use ($hub_sections) {
            return isset($hub_sections[$section]);
        };
        $hub_plan_urls = [
            'free' => esc_url((string) $atts['free_url']),
            'jogador' => esc_url((string) $atts['jogador_url']),
            'dirigente' => esc_url((string) $atts['dirigente_url']),
        ];
        $hub_cta_url = esc_url((string) $atts['cta_url']);
        if ($hub_cta_url === '') {
            $hub_cta_url = wp_registration_url();
        }

        ob_start();
        include MENGAO360_BOLAO_PATH . 'templates/product-hub.php';
        return ob_get_clean();
    }

    private static function catalog($slugs, $lang) {
        if (empty($slugs) || !class_exists('Mengao360_Bolao_DB')) {
            return [];
        }
        $pdo = Mengao360_Bolao_DB::conectar();
        if (!$pdo) {
            return [];
        }

        $marks = implode(',', array_fill(0, count($slugs), '?'));
        $sql = "
            SELECT dc.nome AS competicao_nome, dc.slug AS competicao_slug,
                dc.codigo AS competicao_codigo,
                dc.temporada, dcm.nome_modelo, dcm.slug_modelo,
                dcm.tipo_mata_mata, bc.bolao_competicao_id, bc.slug_bolao,
                bc.estado_operacional, bc.visibilidade,
                COUNT(fj.id) AS total_jogos,
                SUM(CASE WHEN fj.data_jogo > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR)
                    AND UPPER(COALESCE(fj.status_jogo, '')) NOT IN
                        ('FINISHED', 'FINALIZADO', 'CANCELLED')
                    THEN 1 ELSE 0 END) AS jogos_futuros,
                MIN(CASE WHEN fj.data_jogo > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR)
                    AND UPPER(COALESCE(fj.status_jogo, '')) NOT IN
                        ('FINISHED', 'FINALIZADO', 'CANCELLED')
                    THEN fj.data_jogo END) AS proximo_jogo
            FROM dim_competicoes dc
            INNER JOIN dim_competicao_modelo dcm
                ON dcm.id = dc.modelo_id AND dcm.ativo = 1
            INNER JOIN fato_jogos fj ON fj.competicao_id = dc.id
            LEFT JOIN bolao_competicoes bc
                ON bc.bolao_competicao_id = (
                    SELECT MAX(bc2.bolao_competicao_id)
                    FROM bolao_competicoes bc2
                    WHERE bc2.competicao_id = dc.id AND bc2.ind_ativo = 1
                )
            WHERE dc.ativo = 1 AND (
                bc.bolao_competicao_id IS NOT NULL
                OR dc.slug IN ($marks)
                OR LOWER(COALESCE(dc.codigo, '')) IN ($marks)
            )
            GROUP BY dc.id, dc.nome, dc.slug, dc.codigo, dc.temporada,
                dcm.nome_modelo, dcm.slug_modelo, dcm.tipo_mata_mata,
                bc.bolao_competicao_id, bc.slug_bolao,
                bc.estado_operacional, bc.visibilidade
            HAVING COUNT(fj.id) > 0
            ORDER BY
                CASE WHEN bc.bolao_competicao_id IS NOT NULL THEN 0 ELSE 1 END,
                FIELD(dc.slug, $marks),
                FIELD(LOWER(COALESCE(dc.codigo, '')), $marks),
                dc.nome
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($slugs, $slugs, $slugs, $slugs));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Mega Bolão 360 Product Hub: ' . $e->getMessage());
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!Mengao360_Bolao_Competition_Model::is_supported(
                $row['competicao_slug'],
                $row['nome_modelo'],
                $row['tipo_mata_mata']
            )) {
                continue;
            }

            $operation = strtoupper((string) ($row['estado_operacional'] ?? ''));
            $visibility = strtoupper((string) ($row['visibilidade'] ?? ''));
            $public = !empty($row['bolao_competicao_id']) && $visibility === 'PUBLICO';
            $state = 'EM_BREVE';
            if ($public && $operation === 'ABERTO') {
                $state = 'ABERTO';
            } elseif ($public && $operation === 'ENCERRADO') {
                $state = 'ENCERRADO';
            } elseif ($public && $operation === 'BLOQUEADO') {
                $state = 'BLOQUEADO';
            }

            $items[] = [
                'title' => self::competition_name($row, $lang),
                'description' => self::description($operation, $lang),
                'model' => (string) $row['nome_modelo'],
                'state' => $state,
                'total_games' => (int) $row['total_jogos'],
                'future_games' => (int) $row['jogos_futuros'],
                'next_game' => (string) ($row['proximo_jogo'] ?? ''),
                'url' => in_array($state, ['ABERTO', 'ENCERRADO'], true)
                    ? self::pool_url((string) $row['slug_bolao'], $lang)
                    : '',
            ];
        }
        return $items;
    }

    private static function pool_url($pool_slug, $lang) {
        if ($pool_slug === '') {
            return '';
        }

        global $wpdb;
        $like = '%' . $wpdb->esc_like($pool_slug) . '%';
        $page_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
               ON pm.post_id = p.ID AND pm.meta_key = '_elementor_data'
             WHERE p.post_type = 'page' AND p.post_status = 'publish'
               AND (p.post_content LIKE %s OR pm.meta_value LIKE %s)
             ORDER BY p.ID DESC LIMIT 20",
            $like,
            $like
        ));

        foreach ($page_ids as $page_id) {
            $url = get_permalink((int) $page_id);
            $path = (string) wp_parse_url($url, PHP_URL_PATH);
            $english = (bool) preg_match('#^/en(?:/|$)#i', $path);
            if (($lang === 'en-US' && $english) || ($lang === 'pt-BR' && !$english)) {
                return (string) apply_filters(
                    'm360_bolao_product_hub_pool_url',
                    $url,
                    $pool_slug,
                    $lang
                );
            }
        }

        $path = ($lang === 'en-US' ? '/en/' : '/') . $pool_slug . '/';
        return (string) apply_filters(
            'm360_bolao_product_hub_pool_url',
            home_url($path),
            $pool_slug,
            $lang
        );
    }

    private static function competition_name($row, $lang) {
        $english = [
            'fifa-world-cup' => 'FIFA World Cup',
            'brasileirao-serie-a' => 'Brazilian Série A',
            'copa-libertadores' => 'CONMEBOL Libertadores',
            'copa-do-brasil' => 'Copa do Brasil',
        ];
        $english_codes = [
            'cl' => 'UEFA Champions League',
            'bl1' => 'Bundesliga',
            'ded' => 'Eredivisie',
            'pd' => 'La Liga',
            'fl1' => 'Ligue 1',
            'elc' => 'Championship',
            'ppl' => 'Primeira Liga',
            'ec' => 'UEFA European Championship',
            'sa' => 'Italian Serie A',
            'pl' => 'Premier League',
        ];
        $slug = (string) $row['competicao_slug'];
        $code = strtolower((string) ($row['competicao_codigo'] ?? ''));
        $name = trim((string) $row['competicao_nome']);
        if ($lang === 'en-US') {
            if (isset($english[$slug])) {
                $name = $english[$slug];
            } elseif (isset($english_codes[$code])) {
                $name = $english_codes[$code];
            }
        }
        return trim($name . ' ' . (string) $row['temporada']);
    }

    private static function description($operation, $lang) {
        if ($lang === 'en-US') {
            return $operation === 'ENCERRADO'
                ? 'Final results, scored predictions and rankings remain available.'
                : 'Official pool with schedules and results supplied by the Sports DW.';
        }
        return $operation === 'ENCERRADO'
            ? 'Resultados finais, palpites apurados e rankings permanecem disponíveis.'
            : 'Bolão oficial com agenda e resultados fornecidos pelo DW Esportivo.';
    }

    private static function copy($lang) {
        $pt = [
            'eyebrow' => 'O futebol inteiro em um só lugar',
            'title' => 'Mega Bolão 360',
            'lead' => 'Palpite, acompanhe sua pontuação e dispute rankings nas principais competições acompanhadas pelo Mengão 360.',
            'primary_cta' => 'Ver competições', 'secondary_cta' => 'Como funciona',
            'menu' => ['Início', 'Competições', 'Vantagens', 'Planos', 'Como funciona', 'FAQ'],
            'overview_title' => 'O Mega Bolão 360 agora',
            'overview_lead' => 'Um painel rápido da operação esportiva disponível no portal.',
            'games_label' => 'jogos monitorados', 'future_label' => 'próximos jogos',
            'next_competition' => 'próxima competição em campo',
            'plans_title' => 'Um plano para cada jeito de jogar',
            'plans_lead' => 'A apresentação comercial já está preparada; limites definitivos e cobranças serão ativados somente após homologação.',
            'plans_badge' => ['Comece aqui', 'Para grupos', 'Para comunidades'],
            'plans' => [
                ['Free', 'Crie seu primeiro bolão', ['1 bolão próprio', 'Participantes limitados', 'Regras padrão', 'Convite por WhatsApp']],
                ['Jogador', 'Mais espaço para competir', ['Mais participantes', 'Ligas privadas', 'Rankings avançados', 'Mais recursos de comunidade']],
                ['Dirigente', 'Gestão para grandes comunidades', ['Múltiplos bolões', 'Personalização', 'Exportação CSV', 'Suporte prioritário']],
            ],
            'plan_cta' => ['Começar gratuitamente', 'Conhecer o plano', 'Falar com o Mengão 360'],
            'plan_notice' => 'Limites e disponibilidade sujeitos ao gate comercial.',
            'cta_title' => 'Pronto para entrar no jogo?',
            'cta_text' => 'Escolha uma competição aberta ou acompanhe o lançamento dos próximos planos.',
            'cta_button' => 'Começar agora',
            'benefits_title' => 'Tudo para viver cada competição',
            'benefits_lead' => 'Uma experiência única para palpitar, acompanhar e competir com segurança.',
            'benefits' => [
                ['Palpites por partida', 'Placar registrado antes do limite oficial de cada confronto.'],
                ['Rankings atualizados', 'Pontuação geral, diária, por jogo e por liga privada.'],
                ['Ligas entre amigos', 'Crie comunidades privadas e acompanhe a classificação.'],
                ['Português e inglês', 'Experiência preparada para as páginas PT-BR e EN-US do portal.'],
            ],
            'active_label' => 'bolões abertos', 'catalog_label' => 'competições monitoradas',
            'source_label' => 'dados esportivos via DW/ETL',
            'competitions_title' => 'Escolha sua competição',
            'competitions_lead' => 'Agenda, horários, times e resultados são atualizados pela operação esportiva do portal.',
            'empty' => 'O catálogo está temporariamente indisponível.',
            'status' => ['ABERTO' => 'Aberto', 'ENCERRADO' => 'Encerrado',
                'BLOQUEADO' => 'Temporariamente bloqueado', 'EM_BREVE' => 'Em breve'],
            'cta' => ['ABERTO' => 'Participar do bolão',
                'ENCERRADO' => 'Ver resultados e ranking',
                'BLOQUEADO' => 'Indisponível', 'EM_BREVE' => 'Aguarde a abertura'],
            'games' => 'jogos no calendário', 'future' => 'futuros',
            'next' => 'Próximo jogo', 'how_title' => 'Como funciona',
            'steps' => [
                ['Escolha o bolão', 'Entre em uma competição aberta e consulte a agenda oficial.'],
                ['Registre seus palpites', 'Envie os placares antes do fechamento de cada partida.'],
                ['Dispute o ranking', 'A apuração ocorre após a atualização oficial dos resultados.'],
            ],
            'trust_title' => 'Dados oficiais, competição divertida',
            'trust_text' => 'O DW Esportivo e os fluxos ETL são a fonte de verdade para jogos, times, horários e resultados. Seus palpites permanecem isolados no Mega Bolão 360.',
            'faq_title' => 'Perguntas frequentes',
            'faq' => [
                ['Preciso pagar para participar?', 'Os bolões atualmente publicados pelo portal não exigem pagamento.'],
                ['Até quando posso palpitar?', 'Cada partida informa o horário limite. O bloqueio também é aplicado no servidor.'],
                ['Os resultados são atualizados automaticamente?', 'Sim. A agenda e os resultados são lidos do DW Esportivo após as cargas ETL.'],
                ['Posso alterar um palpite salvo?', 'Sim, enquanto a janela da partida estiver aberta. Depois do limite, o servidor bloqueia novas alterações.'],
                ['Como funcionam os rankings?', 'Os pontos são calculados pelas regras do bolão após a confirmação do resultado oficial no DW.'],
                ['Quais competições estarão disponíveis?', 'O catálogo cresce conforme novas temporadas, modelos e calendários são homologados no DW Esportivo.'],
            ],
        ];
        $en = [
            'eyebrow' => 'Football pools in one place', 'title' => 'Mega Bolão 360',
            'lead' => 'Predict scores, track your points and compete in rankings across the main competitions covered by Mengão 360.',
            'primary_cta' => 'Browse competitions', 'secondary_cta' => 'How it works',
            'menu' => ['Home', 'Competitions', 'Benefits', 'Plans', 'How it works', 'FAQ'],
            'overview_title' => 'Mega Bolão 360 right now',
            'overview_lead' => 'A quick snapshot of the sports operation available on the portal.',
            'games_label' => 'matches monitored', 'future_label' => 'upcoming matches',
            'next_competition' => 'next competition on the pitch',
            'plans_title' => 'A plan for every way to play',
            'plans_lead' => 'The commercial presentation is ready; final limits and billing will only be enabled after approval.',
            'plans_badge' => ['Start here', 'For groups', 'For communities'],
            'plans' => [
                ['Free', 'Create your first pool', ['1 pool of your own', 'Limited participants', 'Standard rules', 'WhatsApp invitations']],
                ['Player', 'More room to compete', ['More participants', 'Private leagues', 'Advanced rankings', 'More community features']],
                ['Manager', 'Management for large communities', ['Multiple pools', 'Customization', 'CSV export', 'Priority support']],
            ],
            'plan_cta' => ['Start for free', 'Explore the plan', 'Talk to Mengão 360'],
            'plan_notice' => 'Limits and availability are subject to the commercial gate.',
            'cta_title' => 'Ready to join the game?',
            'cta_text' => 'Choose an open competition or follow the launch of upcoming plans.',
            'cta_button' => 'Get started',
            'benefits_title' => 'Everything you need for every competition',
            'benefits_lead' => 'One place to predict, follow and compete with confidence.',
            'benefits' => [
                ['Match predictions', 'Save each score before the official match deadline.'],
                ['Updated rankings', 'Overall, daily, match and private league standings.'],
                ['Leagues with friends', 'Create private communities and follow their standings.'],
                ['Portuguese and English', 'Built for the portal PT-BR and EN-US pages.'],
            ],
            'active_label' => 'open pools', 'catalog_label' => 'monitored competitions',
            'source_label' => 'sports data via DW/ETL',
            'competitions_title' => 'Choose your competition',
            'competitions_lead' => 'Schedules, kick-off times, teams and results are maintained by the portal sports operation.',
            'empty' => 'The competition catalog is temporarily unavailable.',
            'status' => ['ABERTO' => 'Open', 'ENCERRADO' => 'Closed',
                'BLOQUEADO' => 'Temporarily blocked', 'EM_BREVE' => 'Coming soon'],
            'cta' => ['ABERTO' => 'Join the pool',
                'ENCERRADO' => 'View results and rankings',
                'BLOQUEADO' => 'Unavailable', 'EM_BREVE' => 'Wait for launch'],
            'games' => 'matches scheduled', 'future' => 'upcoming',
            'next' => 'Next match', 'how_title' => 'How it works',
            'steps' => [
                ['Choose a pool', 'Open a competition and browse its official match schedule.'],
                ['Save your predictions', 'Submit each score before the match deadline.'],
                ['Climb the ranking', 'Points are calculated after official results are updated.'],
            ],
            'trust_title' => 'Official data, friendly competition',
            'trust_text' => 'The Sports DW and ETL workflows are the source of truth for matches, teams, times and results. Your predictions remain isolated inside Mega Bolão 360.',
            'faq_title' => 'Frequently asked questions',
            'faq' => [
                ['Do I need to pay to join?', 'Pools currently published by the portal do not require payment.'],
                ['When do predictions close?', 'Each match shows its deadline. The same restriction is enforced by the server.'],
                ['Are results updated automatically?', 'Yes. Schedules and results are read from the Sports DW after ETL loads.'],
                ['Can I change a saved prediction?', 'Yes, while the match window remains open. The server blocks changes after the deadline.'],
                ['How do rankings work?', 'Points are calculated by the pool rules after the official result is confirmed in the Sports DW.'],
                ['Which competitions will be available?', 'The catalog grows as new seasons, models and schedules are approved in the Sports DW.'],
            ],
        ];
        return $lang === 'en-US' ? $en : $pt;
    }
}
