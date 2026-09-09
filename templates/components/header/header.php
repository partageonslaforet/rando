<?php
/**
 * localisation: templates/components/header/header.php
 * Role: Composant : Header
 * Usage: En-tete du site
 * Dépendances: Aucune
 */
function render_header() {
    global $pageTitle, $additionalStyles, $bodyClass;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Inclure les messages flash après le démarrage de la session
    require_once __DIR__ . '/../messages/flash_messages.php';
    render_flash_messages();

    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>Partageons La Forêt</title>
        
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
        <!-- Leaflet CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        
        <!-- Roboto Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
        
        <!-- Custom CSS -->
        <link rel="stylesheet" href="/assets/css/style.css">
        <link rel="stylesheet" href="/assets/css/components/connexion.css?v=2">
        <link rel="stylesheet" href="/assets/css/components/contact-modal.css">
        <!-- <link rel="stylesheet" href="/assets/css/lq.css">
        <link rel="stylesheet" href="/assets/css/lq-dark.css"> -->
        <link rel="stylesheet" href="/assets/css/components/filtres.css">
        <link rel="stylesheet" href="/assets/css/components/calendar.css">
        <link rel="stylesheet" href="/assets/css/components/events-list.css">
        <link rel="stylesheet" href="/assets/css/components/footer.css">
        <link rel="stylesheet" href="/assets/css/header.css">
        <link rel="stylesheet" href="/assets/css/calendar.css">
        
        <!-- Additional Styles -->
        <?= isset($additionalStyles) ? $additionalStyles : '' ?>
        
    </head>
    <body class="<?= isset($bodyClass) ? htmlspecialchars($bodyClass) . ' ' : '' ?>lq-light">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container">
                <div class="navbar-brand-container">
                    <a class="navbar-brand" href="/">Partageons La Forêt</a>
                    <span class="publish-text">Publiez vos événements c'est GRATUIT !</span>
                </div>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item">
                            <a class="nav-link" href="/">Tous les Événements</a>
                        </li>
                        <li class="nav-item">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a class="nav-link" href="/?create=1">Créer un Événement</a>
                            <?php else: ?>
                                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Créer un Événement</a>
                            <?php endif; ?>
                        </li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <li class="nav-item">
                                    <a href="/pages/admin/dashboard.php" class="nav-link">Administration</a>
                                </li>
                            <?php else: ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link btn-connexion dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Mon Compte
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                        <li><a class="dropdown-item" href="/pages/user/profile.php">Mon Profil</a></li>
                                        <li><a class="dropdown-item" href="/pages/user/my-events.php">Mes Événements</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);" onclick="logout()">Déconnexion</a></li>
                                    </ul>
                                </li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li class="nav-item">
                                <a href="#" class="nav-link btn-connexion" id="loginButton" data-bs-toggle="modal" data-bs-target="#loginModal">Connexion</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#contactModal">
                                <i class="bi bi-envelope"></i>
                            </a>
                        </li>
                        <!-- <li class="nav-item">
                            <a href="#" class="nav-link" id="themeToggle">
                                <i class="bi bi-sun-fill"></i>
                            </a>
                        </li> -->
                    </ul>
                </div>
            </div>
        </nav>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const toggler = document.querySelector('.navbar-toggler');
                const navCollapse = document.getElementById('navbarNav');
                console.log('[hamburger] toggler:', toggler);
                console.log('[hamburger] navCollapse:', navCollapse);
                console.log('[hamburger] bootstrap.Collapse:', typeof bootstrap !== 'undefined' ? typeof bootstrap.Collapse : 'no bootstrap');
                if (toggler && navCollapse && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    // On retire les attributs data-bs pour ne pas avoir 2 toggles en concurrence
                    toggler.removeAttribute('data-bs-toggle');
                    toggler.removeAttribute('data-bs-target');
                    const bsCollapse = bootstrap.Collapse.getOrCreateInstance(navCollapse, { toggle: false });
                    toggler.addEventListener('click', function () {
                        console.log('[hamburger] click');
                        bsCollapse.toggle();
                        console.log('[hamburger] show after toggle:', navCollapse.classList.contains('show'));
                    });
                } else {
                    console.warn('[hamburger] élément manquant');
                }
            });
        </script>

        <?php require_once __DIR__ . '/../../../templates/layouts/modals.php';
     
}
?>