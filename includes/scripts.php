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
<script src="/assets/js/header.js"></script>
<script src="/assets/js/init.js"></script>

<!-- Analyse des dépendances -->
<script>
console.log('=== DÉBUT ANALYSE DÉPENDANCES ===');
console.log('Vérification de jQuery:', {
    'jQuery chargé': typeof $ !== 'undefined',
    'Version jQuery': typeof $ !== 'undefined' ? $.fn.jquery : 'non chargé'
});

console.log('Vérification de Bootstrap:', {
    'Bootstrap chargé': typeof bootstrap !== 'undefined',
    'Version Bootstrap': typeof bootstrap !== 'undefined' ? bootstrap.VERSION : 'non chargé',
    'Dropdown disponible': typeof bootstrap !== 'undefined' ? typeof bootstrap.Dropdown : 'non chargé'
});

console.log('État de la session:', {
    'user_id': <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>,
    'user_role': <?php echo isset($_SESSION['user_role']) ? "'" . $_SESSION['user_role'] . "'" : 'null' ?>
});

// Vérifier l'ordre de chargement
document.addEventListener('DOMContentLoaded', function() {
    console.log('Scripts chargés dans l\'ordre:', {
        'jQuery': typeof $ !== 'undefined',
        'Bootstrap': typeof bootstrap !== 'undefined',
        'Bootstrap Dropdown': typeof bootstrap !== 'undefined' ? typeof bootstrap.Dropdown : 'non chargé'
    });
});

console.log('=== FIN ANALYSE DÉPENDANCES ===');
</script>