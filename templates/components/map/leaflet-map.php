<?php
function render_map() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            coordinates,
            category
        FROM events 
        WHERE coordinates IS NOT NULL
    ");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <style>
    .map-container {
        height: 600px;
        width: 100%;
    }
    </style>
    <div id="map" class="map-container mb-4"></div>
    
    <script>
    // Définir les variables globales nécessaires
    window.allEvents = <?= json_encode($locations) ?>;
    window.currentFilters = {};  // Filtres par défaut
    </script>
    <!-- Leaflet overrides -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/components/leaflet-overrides.css">
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Notre script de carte -->
    <script src="<?= APP_URL ?>/assets/js/map.js"></script>

    <script>
// Définir les variables globales nécessaires
window.allEvents = <?= json_encode($locations) ?>;
window.currentFilters = {};  // Filtres par défaut
</script>
    <?php
}

?>