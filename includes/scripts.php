<?php
// Scripts communs à toutes les pages
?>

<!-- Initialisation des données (si on est sur la page home) -->
<?php if (basename($_SERVER['PHP_SELF']) === 'index.php'): ?>
<script>
    window.allEvents = <?php echo json_encode($events); ?>;
    window.eventsByPeriod = <?php echo json_encode($eventsByPeriod); ?>;
    window.eventCounts = <?php echo json_encode($eventCounts); ?>;
</script>
<?php endif; ?>

<!-- Scripts de base -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

<!-- Scripts personnalisés (dans l'ordre des dépendances) -->
<script src="/assets/js/events-api.js"></script>
<script src="/assets/js/map.js"></script>
<script src="/assets/js/calendar.js"></script>
<script src="/assets/js/filters.js"></script>
<script src="/assets/js/modals.js"></script>
<script src="/assets/js/auth.js"></script>
<script src="/assets/js/header.js?v=2"></script>
<script src="/assets/js/init.js"></script>