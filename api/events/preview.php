<?php
/**
 * Retourne le HTML de la prévisualisation d'un événement/brouillon pour injection AJAX.
 *
 * Utilisé par : public/assets/js/event-validation.js
 */

// Définir le chemin racine et charger l'initialisation si nécessaire
$rootDir = dirname(dirname(__DIR__));

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(500);
        $json = json_encode(['success' => false, 'message' => 'Fatal: ' . $err['message']], JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json !== false ? $json : '{"success":false,"message":"Fatal"}';
    }
});

require_once $rootDir . '/includes/init.php';
require_once $rootDir . '/src/Services/EventDisplayBuilder.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $draftId = isset($input['draftId']) ? (int) $input['draftId'] : 0;
    $eventId = isset($input['eventId']) ? (int) $input['eventId'] : 0;

    /** @var PDO $db */
    $builder = new EventDisplayBuilder($db);

    if ($draftId) {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new Exception('Utilisateur non authentifié');
        }
        $event = $builder->build('draft', $draftId, $userId);
        $mode = 'draft';
    } elseif ($eventId) {
        $event = $builder->build('published', $eventId, null);
        $mode = 'published';
    } else {
        throw new Exception('Aucun identifiant fourni');
    }

    if (!$event) {
        throw new Exception('Événement introuvable');
    }

    ob_start();
    include $rootDir . '/templates/events/event-display.php';
    $html = ob_get_clean();

    $json = json_encode([
        'success' => true,
        'data' => [
            'html' => $html
        ]
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = json_encode(['success' => false, 'message' => 'Erreur d\'encodage JSON'], JSON_INVALID_UTF8_SUBSTITUTE);
    }
    echo $json;

} catch (Throwable $e) {
    // Vider tout tampon qui contiendrait du HTML parasite
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    $json = json_encode(['success' => false, 'message' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"success":false,"message":"Erreur d\'encodage"}';
    }
    echo $json;
}
