<?php
/**
 * localisation: templates/layouts/scripts.php
 * Role: Layout : Scripts
 * Usage: Inclusion des scripts JS communs en fin de page
 * Dépendances: Aucune
 */
// Scripts communs à toutes les pages
?>

<!-- Initialisation des données (si on est sur la page home) -->
<?php if (basename($_SERVER['PHP_SELF']) === 'index.php'): ?>
<script>
    <?php if (isset($events)): ?>
    window.allEvents = <?= json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    <?php endif; ?>
    <?php if (isset($eventsByPeriod)): ?>
    window.eventsByPeriod = <?= json_encode($eventsByPeriod, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    <?php endif; ?>
    <?php if (isset($eventCounts)): ?>
    window.eventCounts = <?= json_encode($eventCounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    <?php endif; ?>
</script>
<?php endif; ?>

<!-- Scripts de base -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!defined('PLF_LEAFLET_CDN_EMITTED')) { define('PLF_LEAFLET_CDN_EMITTED', true); ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<?php } ?>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

<!-- Scripts personnalisés (dans l'ordre des dépendances) -->
<script src="/assets/js/core/events-api.js"></script>
<?php if (!defined('PLF_MAP_JS_EMITTED')) { define('PLF_MAP_JS_EMITTED', true); ?>
<script src="/assets/js/home/map.js?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/home/map.js') ?: 1 ?>"></script>
<?php } ?>
<script src="/assets/js/home/calendar.js"></script>
<script src="/assets/js/home/filters.js"></script>
<script src="/assets/js/home/distance.js?v=<?= @filemtime(__DIR__ . '/../../public/assets/js/home/distance.js') ?: 1 ?>"></script>
<script src="/assets/js/core/modals.js?v=2"></script>
<script src="/assets/js/core/auth.js?v=6"></script>
<script src="/assets/js/core/header.js?v=2"></script>
<script src="/assets/js/core/contact.js?v=1"></script>
<script src="/assets/js/core/subscribers.js"></script>
<script src="/assets/js/core/manage-link.js"></script>
<script src="/assets/js/core/init.js"></script>
