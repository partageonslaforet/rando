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

<?php
error_log('[home.php] URI=' . ($_SERVER['REQUEST_URI'] ?? 'none') . ' | body class devrait etre lq-light');
?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        console.log('[home.php] body class:', document.body.className);
        const calendar = document.querySelector('.calendar-container');
        const filters  = document.querySelector('.filters');
        const navbar   = document.querySelector('.navbar');
        if (calendar) console.log('[home.php] calendar bg:', getComputedStyle(calendar).backgroundColor, 'border:', getComputedStyle(calendar).border);
        if (filters)  console.log('[home.php] filters bg:', getComputedStyle(filters).backgroundColor, 'border:', getComputedStyle(filters).border);
        if (navbar)   console.log('[home.php] navbar bg:', getComputedStyle(navbar).backgroundColor);
    });
</script>