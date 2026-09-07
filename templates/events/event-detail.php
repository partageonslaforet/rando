<?php
/**
 * Contrôleur / page d'affichage d'un événement (publié ou en prévisualisation).
 * Inclut le header/footer complets et réutilise event-display.php.
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/EventDisplayBuilder.php';
require_once __DIR__ . '/../components/header/header.php';
require_once __DIR__ . '/../components/footer/footer.php';

$isPreview = (isset($_GET['preview']) && $_GET['preview'] === 'true');
$eventId = (int) ($_GET['id'] ?? 0);

if (!$eventId) {
    header('Location: /');
    exit;
}

try {
    $builder = new EventDisplayBuilder($db);
    $mode = $isPreview ? 'draft' : 'published';
    $userId = $isPreview ? ($_SESSION['user_id'] ?? null) : null;
    $event = $builder->build($mode, $eventId, $userId);

    if (!$event) {
        throw new Exception('Événement non trouvé');
    }

    // Vérifications d'accès pour les événements publiés
    if ($mode === 'published') {
        $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
        $isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['user_id'];

        if ($event['status'] !== 'approved' && !$isAdmin && !$isOwner) {
            header('Location: /');
            exit;
        }

        if (!empty($event['date']) && strtotime($event['date']) < time() && !$isAdmin && !$isOwner) {
            header('Location: /');
            exit;
        }
    }

    $pageTitle = !empty($event['title']) ? $event['title'] : 'Détail de l\'événement';
    $additionalStyles = '<link rel="stylesheet" href="/assets/css/event-display.css">';
    $bodyClass = 'event-detail-page';

    render_header();
    include __DIR__ . '/event-display.php';
    render_footer();

    // Leaflet-gpx et script d'affichage partagé
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>' . "\n";
    echo '<script src="/assets/js/event-display.js"></script>' . "\n";
    require_once __DIR__ . '/../../includes/footer.php';

} catch (Exception $e) {
    error_log('[event-detail] ' . $e->getMessage());
    header('Location: /');
    exit;
}
