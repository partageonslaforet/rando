<?php
function render_footer() {
    ?>
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>À propos</h5>
                    <p>Partageons La Forêt est une initiative pour promouvoir le partage respectueux des espaces naturels.</p>
                </div>
                <div class="col-md-4">
                    <h5>Liens utiles</h5>
                    <ul class="list-unstyled">
                        <li><a href="/about.php">À propos</a></li>
                        <li><a href="/contact.php">Contact</a></li>
                        <li><a href="/mentions-legales.php">Mentions légales</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Suivez-nous</h5>
                    <div class="social-links">
                        <a href="#" class="me-2"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="me-2"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="me-2"><i class="bi bi-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <small>&copy; <?= date('Y') ?> Partageons La Forêt. Tous droits réservés.</small>
            </div>
        </div>
    </footer>
    <?php
}