<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/init.php';

use App\Models\EventManager;

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("PDO non initialisé");
    }
    $eventManager = new EventManager($pdo);
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    die("Une erreur est survenue lors de l'initialisation.");
}

// Inclure les composants nécessaires
include __DIR__ . '/components/hero-section/hero-section.php';
include __DIR__ . '/components/filters/temporal.php';
include __DIR__ . '/components/filters/categories.php';
include __DIR__ . '/components/calendar/calendar.php';
include __DIR__ . '/components/map/leaflet-map.php';
include __DIR__ . '/components/events/events-list.php';
include __DIR__ . '/../includes/scripts.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partageons la forêt - Randonnées</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/header.css" rel="stylesheet">
    <link href="/assets/css/components/calendar.css" rel="stylesheet">
    <link href="/assets/css/components/filtres.css" rel="stylesheet">
    <link href="/assets/css/components/home.css" rel="stylesheet">
</head>
<body class="js-loading">
    <!-- Hero Section -->
    <?php render_hero_section(); ?>
    
    <!-- Conteneur principal -->
    <div class="container mx-auto px-4 mt-4">
        <!-- Section des filtres -->
        <div class="row mb-4">
            <!-- Colonne 60% pour les filtres temporels et catégories -->
            <div class="filters col-12 col-lg-7 mb-4 mb-lg-0">
                <?php 
                render_temporal_filters($pdo);
                render_category_filters($pdo);
                ?>
            </div>
            <!-- Colonne 40% pour le calendrier -->
            <div class="col-12 col-lg-5">
             <?php render_calendar($pdo); ?>
            </div>
        </div>

        <!-- Carte sur toute la largeur -->
        <div class="row mb-4">
            <div class="col-12">
                <?php render_map($pdo); ?>
            </div>
        </div>
        
        <!-- Liste d'événements -->
        <?php render_events_list(); ?>
    </div>

    <!-- Footer -->
    <?php require_once __DIR__ . '/components/footer/footer.php'; ?>

    
</body>
</html>