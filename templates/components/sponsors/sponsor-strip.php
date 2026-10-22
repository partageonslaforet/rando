<?php
/**
 * localisation: templates/components/sponsors/sponsor-strip.php
 * Role: Composant : bandeau sponsor discret (logo unique, rotation JS par fondu)
 * Usage: Affiché sur la home entre le hero et les filtres — masqué si aucun sponsor actif
 * Dépendances: getActiveSponsors() (includes/functions.php), sponsor-rotate.js,
 *              styles .sponsor-strip* dans public/assets/css/style.css
 */
function render_sponsor_strip() {
    $sponsors = [];
    if (function_exists('getActiveSponsors') && function_exists('getConnection')) {
        try {
            $sponsors = getActiveSponsors(getConnection());
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('templates/components/sponsors/sponsor-strip.php', 'sponsor strip failed', ['error' => $e->getMessage()]);
            }
            $sponsors = [];
        }
    }
    if (empty($sponsors)) {
        return;
    }
    ?>
    <!-- Bandeau partenaire discret : un logo à la fois, rotation par fondu -->
    <div class="sponsor-strip" aria-label="Partenaire">
        <span class="sponsor-strip-label"> Nos Partenaires</span>
        <div class="sponsor-strip-slides">
            <?php foreach ($sponsors as $i => $ad): ?>
                <?php $active = $i === 0 ? ' is-active' : ''; ?>
                <?php if (!empty($ad['link_url'])): ?>
                    <a class="sponsor-strip-slide<?= $active ?>" href="/api/sponsors/click.php?id=<?= (int) $ad['id'] ?>&ctx=home" target="_blank" rel="noopener sponsored" title="<?= htmlspecialchars($ad['name']) ?>">
                        <img src="<?= htmlspecialchars($ad['image_path']) ?>" alt="<?= htmlspecialchars($ad['alt_text'] ?: $ad['name']) ?>" loading="lazy">
                    </a>
                <?php else: ?>
                    <span class="sponsor-strip-slide<?= $active ?>" title="<?= htmlspecialchars($ad['name']) ?>">
                        <img src="<?= htmlspecialchars($ad['image_path']) ?>" alt="<?= htmlspecialchars($ad['alt_text'] ?: $ad['name']) ?>" loading="lazy">
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
