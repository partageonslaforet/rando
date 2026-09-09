<?php
/**
 * Pied de page du site.
 */
function render_footer() {
    ?>
    <!-- Footer Top (ajouté) -->
    <div class="footer-top py-5">
        <div class="container">
            <div class="row g-4 align-items-start">
                <!-- Col 1: Marque -->
                <div class="col-12 col-lg-4">
                    <div class="footer-brand d-flex align-items-center gap-2">
                        <img src="/assets/images/logoplfrond.png" alt="Partageons la Forêt" class="footer-logo">
                        <span class="footer-brand-name">Partageons la Forêt</span>
                    </div>
                    <p class="footer-tagline mt-3 mb-0">
                        Événements nature, marche, cyclo, VTT, trail… près de chez vous. </br> Partageons La Forêt est une initiative pour promouvoir le partage respectueux des espaces naturels.
                    </p>
                </div>

                <!-- Col 2: À propos (pas de lien) -->
                <div class="col-12 col-lg-4">
                    <h6 class="footer-title mb-3">À propos</h6>
                    <p class="mb-0">
                        Nous rassemblons les événements outdoor pour faciliter la découverte de la nature et des territoires.
                    </p>
                </div>

                <!-- Col 3: Contact -->
                <div class="col-12 col-lg-4">
                    <h6 class="footer-title mb-3">Contact</h6>
                    <p class="mb-3">Une question, une suggestion, un partenariat ?</p>
                    <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#contactModal" aria-controls="contactModal" aria-haspopup="dialog">Nous contacter</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer bottom (inchangé) -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            
            <hr>
            <div class="text-center">
                <small>&copy; <?= date('Y') ?> Partageons La Forêt. Tous droits réservés.</small>
            </div>
        </div>
    </footer>
    <?php
}