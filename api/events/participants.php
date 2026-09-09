<?php
/**
 * Gestion des inscriptions aux événements (join/leave) et liste des participants.
 *
 * GET  : /api/events/participants.php?event_id=... renvoie la liste des participants.
 * POST : inscrit (action=join) ou désinscrit (action=leave) l'utilisateur connecté.
 *        Accepte à la fois application/x-www-form-urlencoded et JSON.
 *        Répond en JSON en AJAX, redirige en POST classique.
 *
 * Utilisé par : templates/events/event.php
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/Models/Event.php';

$isAjax = isAjaxRequest()
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

try {
    $pdo = getConnection();
    $eventManager = new Event($pdo);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // GET : liste des participants
    if ($method === 'GET') {
        if (!isset($_GET['event_id'])) {
            throw new Exception('ID de l\'événement manquant', 400);
        }
        $eventId = filter_var($_GET['event_id'], FILTER_VALIDATE_INT);
        if (!$eventId) {
            throw new Exception('ID de l\'événement invalide', 400);
        }

        $participants = $eventManager->getParticipants($eventId);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'participants' => $participants]);
        exit;
    }

    // POST : inscription / désinscription
    if ($method === 'POST') {
        if (!isLoggedIn()) {
            throw new Exception('Vous devez être connecté', 401);
        }

        $input = $_POST;
        if (empty($input)) {
            $json = json_decode(file_get_contents('php://input'), true);
            if (is_array($json)) {
                $input = $json;
            }
        }

        if (!isset($input['event_id']) || !isset($input['action'])) {
            throw new Exception('Paramètres manquants', 400);
        }

        $eventId = filter_var($input['event_id'], FILTER_VALIDATE_INT);
        if (!$eventId) {
            throw new Exception('ID de l\'événement invalide', 400);
        }

        $action = $input['action'];
        $userId = $_SESSION['user_id'];

        $event = $eventManager->getById($eventId);
        if (!$event) {
            throw new Exception('Événement non trouvé', 404);
        }

        if (!in_array($event['status'], ['published', 'approved'], true)) {
            throw new Exception('Les inscriptions sont fermées pour cet événement', 400);
        }

        if ($action === 'join') {
            if (!empty($event['max_participants'])) {
                $countStmt = $pdo->prepare('SELECT COUNT(*) FROM event_participants WHERE event_id = ?');
                $countStmt->execute([$eventId]);
                $count = (int) $countStmt->fetchColumn();
                if ($count >= $event['max_participants']) {
                    throw new Exception('L\'événement est complet', 400);
                }
            }

            if ($eventManager->isUserParticipating($eventId, $userId)) {
                throw new Exception('Vous êtes déjà inscrit à cet événement', 400);
            }

            $eventManager->addParticipant($eventId, $userId);
            $message = 'Inscription réussie';
        } elseif ($action === 'leave') {
            if (!$eventManager->isUserParticipating($eventId, $userId)) {
                throw new Exception('Vous n\'êtes pas inscrit à cet événement', 400);
            }

            $eventManager->removeParticipant($eventId, $userId);
            $message = 'Désinscription réussie';
        } else {
            throw new Exception('Action invalide', 400);
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => $message]);
            exit;
        }

        header('Location: /events/event-detail.php?id=' . $eventId);
        exit;
    }

    throw new Exception('Méthode non autorisée', 405);

} catch (Exception $e) {
    $code = is_int($e->getCode()) && $e->getCode() >= 100 ? $e->getCode() : 500;
    if (!in_array($code, [400, 401, 404, 405], true)) {
        $code = 500;
    }

    $eventId = $_POST['event_id'] ?? $_GET['event_id'] ?? null;

    if ($isAjax) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }

    $redirect = $eventId ? '/events/event-detail.php?id=' . $eventId : '/';
    header('Location: ' . $redirect . '&error=' . urlencode($e->getMessage()));
    exit;
}
