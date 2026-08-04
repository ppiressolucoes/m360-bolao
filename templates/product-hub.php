<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="m360-product-hub" data-language="<?php echo esc_attr($lang); ?>">
    <header id="m360-inicio" class="m360-product-hero">
        <div class="m360-product-hero__content">
            <span class="m360-product-eyebrow"><?php echo esc_html($hub_copy['eyebrow']); ?></span>
            <h1><?php echo esc_html($hub_copy['title']); ?></h1>
            <p><?php echo esc_html($hub_copy['lead']); ?></p>
            <div class="m360-product-actions">
                <a class="m360-product-button m360-product-button--primary" href="#m360-competicoes">
                    <?php echo esc_html($hub_copy['primary_cta']); ?>
                </a>
                <a class="m360-product-button m360-product-button--ghost" href="#m360-como-funciona">
                    <?php echo esc_html($hub_copy['secondary_cta']); ?>
                </a>
            </div>
        </div>
        <div class="m360-product-scoreboard" aria-label="<?php echo esc_attr($hub_copy['catalog_label']); ?>">
            <div>
                <strong><?php echo esc_html((string) $hub_open_count); ?></strong>
                <span><?php echo esc_html($hub_copy['active_label']); ?></span>
            </div>
            <div>
                <strong><?php echo esc_html((string) count($hub_items)); ?></strong>
                <span><?php echo esc_html($hub_copy['catalog_label']); ?></span>
            </div>
            <div class="m360-product-scoreboard__wide">
                <strong>360&deg;</strong>
                <span><?php echo esc_html($hub_copy['source_label']); ?></span>
            </div>
        </div>
    </header>

    <nav class="m360-product-nav" aria-label="Mega Bol&atilde;o 360">
        <a href="#m360-inicio"><?php echo esc_html($hub_copy['menu'][0]); ?></a>
        <a href="#m360-competicoes"><?php echo esc_html($hub_copy['menu'][1]); ?></a>
        <a href="#m360-vantagens"><?php echo esc_html($hub_copy['menu'][2]); ?></a>
        <a href="#m360-como-funciona"><?php echo esc_html($hub_copy['menu'][3]); ?></a>
        <a href="#m360-faq"><?php echo esc_html($hub_copy['menu'][4]); ?></a>
    </nav>

    <section class="m360-product-section m360-product-overview" aria-labelledby="m360-overview-title">
        <div class="m360-product-heading">
            <span class="m360-product-kicker">MEGA BOL&Atilde;O 360</span>
            <h2 id="m360-overview-title"><?php echo esc_html($hub_copy['overview_title']); ?></h2>
            <p><?php echo esc_html($hub_copy['overview_lead']); ?></p>
        </div>
        <div class="m360-product-widgets">
            <article class="m360-product-widget m360-product-widget--accent">
                <strong><?php echo esc_html((string) $hub_open_count); ?></strong>
                <span><?php echo esc_html($hub_copy['active_label']); ?></span>
            </article>
            <article class="m360-product-widget">
                <strong><?php echo esc_html((string) count($hub_items)); ?></strong>
                <span><?php echo esc_html($hub_copy['catalog_label']); ?></span>
            </article>
            <article class="m360-product-widget">
                <strong><?php echo esc_html((string) $hub_total_games); ?></strong>
                <span><?php echo esc_html($hub_copy['games_label']); ?></span>
            </article>
            <article class="m360-product-widget">
                <strong><?php echo esc_html((string) $hub_future_games); ?></strong>
                <span><?php echo esc_html($hub_copy['future_label']); ?></span>
            </article>
            <?php if ($hub_next_item !== null): ?>
                <article class="m360-product-widget m360-product-widget--wide">
                    <span><?php echo esc_html($hub_copy['next_competition']); ?></span>
                    <strong><?php echo esc_html($hub_next_item['title']); ?></strong>
                    <small><?php echo esc_html(date_i18n('d/m/Y - H:i', strtotime($hub_next_item['next_game']))); ?></small>
                </article>
            <?php endif; ?>
        </div>
    </section>

    <section id="m360-competicoes" class="m360-product-section">
        <div class="m360-product-heading">
            <span class="m360-product-kicker">DW ESPORTIVO</span>
            <h2><?php echo esc_html($hub_copy['competitions_title']); ?></h2>
            <p><?php echo esc_html($hub_copy['competitions_lead']); ?></p>
        </div>

        <?php if (empty($hub_items)): ?>
            <div class="m360-product-empty"><?php echo esc_html($hub_copy['empty']); ?></div>
        <?php else: ?>
            <div class="m360-product-grid">
                <?php foreach ($hub_items as $item): ?>
                    <?php
                    $state_class = strtolower(str_replace('_', '-', $item['state']));
                    $next_label = '';
                    if (!empty($item['next_game'])) {
                        $timestamp = strtotime($item['next_game']);
                        if ($timestamp) {
                            $next_label = date_i18n('d/m/Y - H:i', $timestamp);
                        }
                    }
                    ?>
                    <article class="m360-product-card m360-product-card--<?php echo esc_attr($state_class); ?>">
                        <div class="m360-product-card__top">
                            <span class="m360-product-status">
                                <?php echo esc_html($hub_copy['status'][$item['state']]); ?>
                            </span>
                            <span class="m360-product-model"><?php echo esc_html($item['model']); ?></span>
                        </div>
                        <h3><?php echo esc_html($item['title']); ?></h3>
                        <p><?php echo esc_html($item['description']); ?></p>
                        <dl class="m360-product-meta">
                            <div>
                                <dt><?php echo esc_html($hub_copy['games']); ?></dt>
                                <dd><?php echo esc_html((string) $item['total_games']); ?></dd>
                            </div>
                            <div>
                                <dt><?php echo esc_html($hub_copy['future']); ?></dt>
                                <dd><?php echo esc_html((string) $item['future_games']); ?></dd>
                            </div>
                        </dl>
                        <?php if ($next_label !== ''): ?>
                            <div class="m360-product-next">
                                <span><?php echo esc_html($hub_copy['next']); ?></span>
                                <strong><?php echo esc_html($next_label); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($item['url'] !== ''): ?>
                            <a class="m360-product-card__cta" href="<?php echo esc_url($item['url']); ?>">
                                <?php echo esc_html($hub_copy['cta'][$item['state']]); ?>
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        <?php else: ?>
                            <span class="m360-product-card__cta m360-product-card__cta--disabled">
                                <?php echo esc_html($hub_copy['cta'][$item['state']]); ?>
                            </span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section id="m360-vantagens" class="m360-product-section m360-product-benefits">
        <div class="m360-product-heading">
            <span class="m360-product-kicker">360&deg;</span>
            <h2><?php echo esc_html($hub_copy['benefits_title']); ?></h2>
            <p><?php echo esc_html($hub_copy['benefits_lead']); ?></p>
        </div>
        <div class="m360-product-blocks">
            <?php foreach ($hub_copy['benefits'] as $index => $benefit): ?>
                <article>
                    <span><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                    <h3><?php echo esc_html($benefit[0]); ?></h3>
                    <p><?php echo esc_html($benefit[1]); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="m360-como-funciona" class="m360-product-section m360-product-section--soft">
        <div class="m360-product-heading">
            <span class="m360-product-kicker">MEGA BOL&Atilde;O 360</span>
            <h2><?php echo esc_html($hub_copy['how_title']); ?></h2>
        </div>
        <ol class="m360-product-steps">
            <?php foreach ($hub_copy['steps'] as $index => $step): ?>
                <li>
                    <span><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                    <div>
                        <h3><?php echo esc_html($step[0]); ?></h3>
                        <p><?php echo esc_html($step[1]); ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section id="m360-dados" class="m360-product-trust">
        <div class="m360-product-trust__mark" aria-hidden="true">DW</div>
        <div>
            <h2><?php echo esc_html($hub_copy['trust_title']); ?></h2>
            <p><?php echo esc_html($hub_copy['trust_text']); ?></p>
        </div>
    </section>

    <section id="m360-faq" class="m360-product-section m360-product-faq">
        <div class="m360-product-heading">
            <h2><?php echo esc_html($hub_copy['faq_title']); ?></h2>
        </div>
        <?php foreach ($hub_copy['faq'] as $item): ?>
            <details>
                <summary><?php echo esc_html($item[0]); ?></summary>
                <p><?php echo esc_html($item[1]); ?></p>
            </details>
        <?php endforeach; ?>
    </section>
</section>
