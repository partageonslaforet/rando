<?php
/**
 * Bannière héro de la page d'accueil.
 */
function render_hero_section() {
    ?>
    <section class="hero" style="background-image: url('/assets/images/main-hero.jpg');">
        <div class="hero-content">
            <h1 class="display-2 fw-bold mb-4">Découvrez des événements sportifs près de chez vous</h1>
            <p class="slogan">Trouvez et rejoignez des activités sportives organisées par des passionnés dans votre région.</p>
            <div class="hero-search-container">
                <div class="input-group">
                    <input type="text" 
                        id="searchInput"
                        class="form-control" 
                        placeholder="Rechercher un événement ou une organisation..." 
                        name="search"
                        value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <button class="btn btn-outline-secondary" type="button" id="searchButton">
                        <i class="bi bi-search"></i>
                    </button>
                    <button class="btn btn-outline-secondary reset-button" type="button" title="Réinitialiser la recherche">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/?create=1" class="btn btn-success">
                        Créer un Événement
                    </a>
                <?php else: ?>
                    <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#loginModal">
                        Créer un Événement
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
}