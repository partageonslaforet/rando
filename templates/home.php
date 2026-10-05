<?php
/**
 * templates/home.php
 * Role: Template de la page d'accueil. Charge les composants filtres, calendrier, carte et liste d'événements.
 * Usage: Inclus par index.php pour afficher la home.
 * Dépendances: vendor/autoload.php, includes/init.php, src/Models/EventManager.php, composants dans templates/components/
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../src/Models/EventManager.php';

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
?>
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