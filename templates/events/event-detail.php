<?php
/**
 * localisation: templates/events/event-detail.php
 * Role: Template evenement : Event Detail
 * Usage: Controleur / page d affichage d un evenement (publie ou en previsualisation)
 * Dépendances: includes/init.php, src/Services/EventDisplayBuilder.php
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../logs/error.log.php';
require_once __DIR__ . '/../../src/Services/EventDisplayBuilder.php';
require_once __DIR__ . '/../components/header/header.php';

// Log de la visite : une seule ligne par affichage réel de la page détail
if (function_exists('trackVisit')) {
    trackVisit($_SERVER['REQUEST_URI'] ?? '/');
}
require_once __DIR__ . '/../components/footer/footer.php';

$isPreview = (isset($_GET['preview']) && $_GET['preview'] === 'true');
$eventId = (int) ($_GET['id'] ?? 0);

if (!$eventId) {
    header('Location: /');
    exit;
}

// Redirection 301 si accès direct au template
if (!$isPreview && strpos($_SERVER['REQUEST_URI'] ?? '', 'templates/events/event-detail.php') !== false) {
    header('Location: /event/' . $eventId, true, 301);
    exit;
}

// Trace d'entrée du template
logError('templates/events/event-detail.php', 'template enter', [
    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
    'event_id' => $eventId,
    'preview' => $isPreview ? true : false
]);

try {
    $builder = new EventDisplayBuilder($db);
    $mode = $isPreview ? 'draft' : 'published';
    $userId = $isPreview ? ($_SESSION['user_id'] ?? null) : null;
    $event = $builder->build($mode, $eventId, $userId);
    logError('templates/events/event-detail.php', 'render enter', ['event_id' => $eventId, 'mode' => $mode]);
    $event['views_total'] = getEventTotalViews($db, $eventId);
    logError('templates/events/event-detail.php', 'render views', ['event_id' => $eventId, 'views_total' => (int)($event['views_total'] ?? -1)]);

    if (!$event) {
        throw new Exception('Événement non trouvé');
    }

    // Vérifications d'accès pour les événements publiés
    if ($mode === 'published') {
        $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
        $isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['user_id'];

        // Seuls les événements "approved" sont publics (passés ou à venir)
        // Les autres statuts : visible uniquement admin ou owner
        if ($event['status'] !== 'approved' && !$isAdmin && !$isOwner) {
            header('Location: /');
            exit;
        }
        // Incrément persistant (historique) du compteur events.view_count
        try {
            $incStmt = $db->prepare('UPDATE events SET view_count = view_count + 1 WHERE id = :id');
            $incStmt->execute(['id' => $eventId]);
            $event['view_count'] = (int)($event['view_count'] ?? 0) + 1;
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('templates/events/event-detail.php', 'increment view_count failed', [
                    'event_id' => $eventId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    $pageTitle = !empty($event['title']) ? $event['title'] : 'Détail de l\'événement';
    // Description nettoyée et coupée proprement
    $rawDescription = strip_tags($event['description'] ?? '');
    $rawDescription = preg_replace('/\s+/', ' ', $rawDescription);
    $rawDescription = trim($rawDescription);
    if (function_exists('mb_substr')) {
        $cut = mb_substr($rawDescription, 0, 157);
        $lastSpace = mb_strrpos($cut, ' ');
    } else {
        $cut = substr($rawDescription, 0, 157);
        $lastSpace = strrpos($cut, ' ');
    }
    if (strlen($rawDescription) > 160 && $lastSpace !== false) {
        $pageDescription = (function_exists('mb_substr') ? mb_substr($cut, 0, $lastSpace) : substr($cut, 0, $lastSpace)) . '...';
    } elseif (strlen($rawDescription) > 160) {
        $pageDescription = $cut . '...';
    } else {
        $pageDescription = $rawDescription;
    }
    $pageDescription = !empty($pageDescription) ? $pageDescription : 'Randonnée et activité en pleine nature proposée sur Partageons La Forêt.';

    // Image normalisée pour les OG
    $rawImage = $event['main_image'] ?? '/assets/images/events/default-event.jpg';
    if (strpos($rawImage, 'http') === 0) {
        $pageImage = $rawImage;
    } else {
        $rawImage = str_replace('public/', '', $rawImage);
        $rawImage = preg_replace('#^config/\.\./#', '', $rawImage);
        $rawImage = ltrim($rawImage, '/');
        $pageImage = '/' . $rawImage;
    }
    $pageCanonical = APP_URL . '/event/' . $eventId;
    $pageType = 'article';

    // Données structurées JSON-LD (Event schema.org)
    $imageUrl = (strpos($pageImage, 'http') === 0) ? $pageImage : APP_URL . $pageImage;
    $startDate = !empty($event['start_time']) ? $event['date'] . 'T' . substr($event['start_time'], 0, 5) : $event['date'];

    $location = [
        '@type' => 'Place',
        'name' => $event['venue'] ?? $event['location'] ?? 'Lieu non communiqué',
    ];
    if (!empty($event['meeting_address']) || !empty($event['meeting_city']) || !empty($event['location'])) {
        $address = [];
        if (!empty($event['meeting_address'])) {
            $address['streetAddress'] = $event['meeting_address'];
        }
        if (!empty($event['meeting_city'])) {
            $address['addressLocality'] = $event['meeting_city'];
        }
        if (empty($address)) {
            $address['streetAddress'] = $event['location'];
        }
        $location['address'] = array_merge(['@type' => 'PostalAddress'], $address);
    }
    if (!empty($event['coordinates'])) {
        $coords = array_map('floatval', array_map('trim', explode(',', $event['coordinates'])));
        if (count($coords) >= 2) {
            $location['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $coords[0],
                'longitude' => $coords[1],
            ];
        }
    }

    $organizerName = !empty($event['organizer']['name']) ? $event['organizer']['name'] : '';
    if (empty($organizerName) || is_numeric($organizerName)) {
        $organizerName = 'Partageons La Forêt';
    }

    $pageJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $pageTitle,
        'description' => $pageDescription,
        'startDate' => $startDate,
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => $location,
        'organizer' => [
            '@type' => 'Organization',
            'name' => $organizerName,
        ],
        'image' => $imageUrl,
        'url' => $pageCanonical,
    ];
    if (!empty($event['end_time'])) {
        $pageJsonLd['endDate'] = $event['date'] . 'T' . substr($event['end_time'], 0, 5);
    }

    $edCssV = @filemtime((defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/public/assets/css/pages/events/event-display.css') ?: time();
    $additionalStyles = '<link rel="stylesheet" href="/assets/css/pages/events/event-display.css?v=' . $edCssV . '">';
    $bodyClass = 'event-detail-page';

    render_header();
    include __DIR__ . '/event-display.php';
    render_footer();

    // Leaflet-gpx et script d'affichage partagé
    echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>' . "\n";
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>' . "\n";
    echo '<script src="/assets/js/events/event-display.js?v=' . (@filemtime((defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/public/assets/js/events/event-display.js') ?: 1) . '"></script>' . "\n";
    require_once __DIR__ . '/../../templates/layouts/footer.php';

} catch (Exception $e) {
    error_log('[event-detail] ' . $e->getMessage());
    header('Location: /');
    exit;
}
