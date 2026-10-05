<?php
/**
 * localisation: templates/components/footer/footer.php
 * Role: Composant : Footer
 * Usage: Pied de page du site
 * Dépendances: getActiveSponsors() (includes/functions.php) pour le bandeau partenaires
 */
function render_footer(bool $showSponsors = false) {
    $footerSponsors = [];
    if ($showSponsors && function_exists('getActiveSponsors') && function_exists('getConnection')) {
        try {
            $footerSponsors = getActiveSponsors(getConnection());
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('templates/components/footer/footer.php', 'footer sponsors failed', ['error' => $e->getMessage()]);
            }
            $footerSponsors = [];
        }
    }
    ?>
    <!-- Footer -->
    <footer class="footer mt-auto footer-top py-5">
        <div class="container">
            <div class="row g-4 align-items-start<?= empty($footerSponsors) ? ' row--two-cols' : '' ?>">
                <!-- Col 1: À propos -->
                <div class="col-12 col-lg-4">
                    <div class="footer-brand d-flex align-items-center gap-2">
                        <img src="/assets/images/logoplfrond.png" alt="Partageons la Forêt" class="footer-logo">
                        <a href="https://partageonslaforet.be" target="_blank" rel="noopener noreferrer" class="footer-brand-name text-decoration-none">Partageons la Forêt</a>
                    </div>
                    <p class="footer-tagline mt-3 mb-3">
                        Partageons La Forêt est une initiative privée qui encourage le partage respectueux des espaces naturels et de celles et ceux qui les fréquentent.
                    </p>
                    <a href="https://partageonslaforet.be" class="hunting-dates mb-3" target="_blank" rel="noopener noreferrer" title="Dates de chasse 2026-2027">
                        <i class="bi bi-calendar3"></i>
                        <span><strong>Saison 2026&ndash;2027</strong> — Consultez les dates de chasse pour préparer vos sorties en toute sérénité.</span>
                    </a>
                    <p class="footer-tagline mb-0">
                        Nous rassemblons près de chez vous des événements autour de la nature et des activités de plein air — marche, cyclisme, VTT, trail et bien d'autres — afin de vous aider à découvrir la nature et les territoires qui vous entourent.
                    </p>
                </div>

                <!-- Col 2: Partenaires (masquée si aucun actif) -->
                <?php if (!empty($footerSponsors)): ?>
                <div class="col-12 col-lg-4">
                    <h6 class="footer-title mb-3">Nos partenaires</h6>
                    <div class="footer-sponsors-row">
                        <?php foreach ($footerSponsors as $ad): ?>
                            <?php if (!empty($ad['link_url'])): ?>
                                <a href="<?= htmlspecialchars($ad['link_url']) ?>" target="_blank" rel="noopener sponsored" class="footer-sponsor-item" title="<?= htmlspecialchars($ad['name']) ?>">
                                    <img src="<?= htmlspecialchars($ad['image_path']) ?>" alt="<?= htmlspecialchars($ad['alt_text'] ?: $ad['name']) ?>" loading="lazy">
                                </a>
                            <?php else: ?>
                                <span class="footer-sponsor-item">
                                    <img src="<?= htmlspecialchars($ad['image_path']) ?>" alt="<?= htmlspecialchars($ad['alt_text'] ?: $ad['name']) ?>" loading="lazy">
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Col 3: Contact & notifications -->
                <div class="col-12 col-lg-4">
                    <h6 class="footer-title mb-3">Notifications</h6>
                    <p class="mb-3">Restez informé des nouveaux événements.</p>
                    <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#subscribersModal" aria-controls="subscribersModal" aria-haspopup="dialog">S'abonner aux événements</a>
                    <a href="#" class="btn btn-outline-success btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#manageSubscribersModal" aria-controls="manageSubscribersModal" aria-haspopup="dialog">Gérer mes abonnements</a>

                    <hr class="my-4">

                    <h6 class="footer-title mb-3">Contact</h6>
                    <p class="mb-3">Une question, une suggestion, un partenariat ?</p>
                    <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#contactModal" aria-controls="contactModal" aria-haspopup="dialog">Nous contacter</a>
                </div>
            </div>

            <!-- Copyright intégré au footer -->
            <div class="footer-bottom">
                <small>&copy; <?= date('Y') ?> Partageons La Forêt. Tous droits réservés.</small>
            </div>
        </div>
    </footer>
    <?php
}