<?php
/**
 * localisation: templates/components/map/leaflet-map.php
 * Role: Composant : Leaflet Map
 * Usage: Carte Leaflet affichant les evenements
 * Dépendances: Aucune
 */
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../logs/error.log.php';

function render_map() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.title,
            e.coordinates,
            e.category,
            e.date,
            e.location,
            e.main_image_path AS event_image_path,
            e.main_image,
            (SELECT ei.image_path FROM event_images ei WHERE ei.event_id = e.id ORDER BY ei.is_main DESC, ei.id ASC LIMIT 1) AS image_path,
            (SELECT ei.storage_path FROM event_images ei WHERE ei.event_id = e.id ORDER BY ei.is_main DESC, ei.id ASC LIMIT 1) AS storage_path
        FROM events e
        WHERE e.coordinates IS NOT NULL
    ");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Résoudre l'URL publique de l'image principale
    foreach ($locations as &$event) {
        // Priorité 1 : image principale dans event_images
        $eventImagesUrl = resolveImagePublicUrl($event['image_path'], $event['storage_path']);

        // Priorité 2 : colonnes legacy dans events
        $legacyUrl = null;
        if (!empty($event['event_image_path']) && $event['event_image_path'] !== '/assets/images/events/default-event.jpg') {
            $legacyUrl = $event['event_image_path'];
        } elseif (!empty($event['main_image'])) {
            $legacyUrl = $event['main_image'];
        }

        $raw = $eventImagesUrl ?: $legacyUrl;

        if (!empty($raw) && !preg_match('#^(https?://|/)#i', $raw)) {
            $raw = '/' . ltrim($raw, '/');
        }

        $event['main_image_path'] = $raw ?: '/assets/images/events/default-event.jpg';
        unset($event['event_image_path'], $event['image_path'], $event['storage_path'], $event['main_image']);

        if ($event['main_image_path'] === '/assets/images/events/default-event.jpg') {
            logError('leaflet-map', 'Image principale non résolue', [
                'event_id' => $event['id'],
                'title' => $event['title']
            ]);
        }
    }
    unset($event);
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
    window.allEvents = <?= json_encode($locations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    window.currentFilters = {};  // Filtres par défaut
    </script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Notre script de carte -->
    <script src="<?= APP_URL ?>/assets/js/home/map.js"></script>
    <?php
}

?>