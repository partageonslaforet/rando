<?php
/**
 * localisation: templates/layouts/header-solid.php
 * Role: Layout : Header Solid
 * Usage: Variante d en-tête avec fond opaque
 * Dépendances: includes/flash_messages.php
 */
// La session est déjà démarrée dans les fichiers qui incluent header-solid.php

// Inclure les messages flash
require_once __DIR__ . '/../../includes/flash_messages.php';
displayFlashMessages();
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
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/header-solid.css">
    
    <!-- Additional Styles -->
    <?= isset($additionalStyles) ? $additionalStyles : '' ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

    <!-- Custom JS -->
    <script>
        console.log('=== DÉBUT INITIALISATION DROPDOWN HEADER-SOLID ===');
        
        $(document).ready(function() {
            console.log('jQuery prêt dans header-solid.php');
            
            // Écouter les événements sur le menu lui-même
            $('.dropdown-menu').on('show.bs.dropdown', function () {
                console.log('Menu en cours d\'ouverture');
            }).on('shown.bs.dropdown', function () {
                console.log('Menu ouvert');
            }).on('hide.bs.dropdown', function () {
                console.log('Menu en cours de fermeture');
            }).on('hidden.bs.dropdown', function () {
                console.log('Menu fermé');
            });

            // Écouter le clic sur le bouton
            $('#navbarDropdown').on('click', function(e) {
                console.log('Clic sur le bouton dropdown');
                console.log('État aria-expanded:', $(this).attr('aria-expanded'));
                // Forcer l'ouverture/fermeture
                $(this).dropdown('toggle');
            });
        });

        console.log('=== FIN INITIALISATION DROPDOWN HEADER-SOLID ===');
    </script>
</head>
<body> 
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
                            <li class="nav-item">
                                <a class="nav-link btn-connexion" href="javascript:void(0);" onclick="logout()">Déconnexion</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link btn-connexion dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Mon Compte
                                </a>
                                <ul class="dropdown-menu" id="accountDropdown" aria-labelledby="navbarDropdown">
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
                    <li class="nav-item">
                        <a href="#" class="nav-link" id="themeToggle">
                            <i class="bi bi-sun-fill"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

</body>
