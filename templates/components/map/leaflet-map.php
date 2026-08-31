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

    /* Couleur des boutons zoom Leaflet */
    .leaflet-bar a.leaflet-control-zoom-in,
    .leaflet-bar a.leaflet-control-zoom-out {
        color: var(--secondary-color, #3A8A3D) !important;
        outline: 2px solid var(--secondary-color, #3A8A3D) !important;
    }
    </style>
    <div id="map" class="map-container mb-4"></div>
    
    <script>
    // Définir les variables globales nécessaires
    window.allEvents = <?= json_encode($locations) ?>;
    window.currentFilters = {};  // Filtres par défaut
    </script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Notre script de carte -->
    <script src="<?= APP_URL ?>/assets/js/map.js"></script>

    <script>
// Définir les variables globales nécessaires
window.allEvents = <?= json_encode($locations) ?>;
window.currentFilters = {};  // Filtres par défaut
</script>

    <script>
    // Diagnostic boutons de zoom Leaflet
    window.addEventListener('load', function() {
        setTimeout(function() {
            const zoomIn = document.querySelector('.leaflet-control-zoom-in');
            const zoomOut = document.querySelector('.leaflet-control-zoom-out');

            console.log('[DIAG] zoomIn trouvé ?', !!zoomIn);
            console.log('[DIAG] zoomOut trouvé ?', !!zoomOut);

            if (zoomIn) {
                console.log('[DIAG] zoomIn classList:', zoomIn.className);
                console.log('[DIAG] zoomIn innerHTML:', zoomIn.innerHTML);
                console.log('[DIAG] zoomIn computed color:', window.getComputedStyle(zoomIn).color);
                console.log('[DIAG] zoomIn computed outline:', window.getComputedStyle(zoomIn).outlineColor);
            }

            if (zoomOut) {
                console.log('[DIAG] zoomOut classList:', zoomOut.className);
                console.log('[DIAG] zoomOut innerHTML:', zoomOut.innerHTML);
                console.log('[DIAG] zoomOut computed color:', window.getComputedStyle(zoomOut).color);
                console.log('[DIAG] zoomOut computed outline:', window.getComputedStyle(zoomOut).outlineColor);
            }

            // Lister les règles CSS ciblant les boutons de zoom
            for (const sheet of document.styleSheets) {
                try {
                    for (const rule of sheet.cssRules || []) {
                        if (rule.selectorText && (
                            rule.selectorText.includes('leaflet-control-zoom-in') ||
                            rule.selectorText.includes('leaflet-control-zoom-out')
                        )) {
                            console.log('[DIAG] règle CSS:', rule.cssText);
                        }
                    }
                } catch (e) {
                    console.warn('[DIAG] Impossible de lire une feuille de style:', e);
                }
            }
        }, 1000);
    });
    </script>
    <?php
}

?>