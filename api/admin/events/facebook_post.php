<?php
/**
 * Publication manuelle d'un événement sur la Page Facebook (admin).
 * Body JSON : { "event_id": 123 }
 * Retourne le fb_post_id en cas de succès. Non bloquant côté UI.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../logs/error.log.php';
requireLogin();

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$eventId = isset($data['event_id']) ? filter_var($data['event_id'], FILTER_VALIDATE_INT) : false;

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID d\'événement invalide']);
    exit();
}

try {
    require_once __DIR__ . '/../../../config/database.php';

    if (!defined('FB_PAGE_TOKEN') || FB_PAGE_TOKEN === '' || !defined('FB_PAGE_ID') || FB_PAGE_ID === '') {
        echo json_encode(['success' => false, 'message' => 'Configuration Facebook absente (FB_PAGE_TOKEN/FB_PAGE_ID dans database.php)']);
        exit();
    }

    $db = getConnection();

    $stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND status = 'approved'");
    $stmt->execute([$eventId]);
    $eventData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$eventData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Événement non trouvé ou non approuvé']);
        exit();
    }

    $fbPostId = postEventToFacebook($eventData, $eventId, $db);

    if ($fbPostId) {
        echo json_encode(['success' => true, 'message' => 'Publié sur Facebook', 'fb_post_id' => $fbPostId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Échec de la publication (voir error.log)']);
    }
} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError('api/admin/events/facebook_post.php', 'Exception post Facebook', [
            'event_id' => $eventId,
            'exception' => $e->getMessage()
        ]);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
