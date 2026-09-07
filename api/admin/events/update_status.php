<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../logs/error.log.php';
requireLogin();

// Vérifier que l'utilisateur est admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Récupérer et valider les données
$data = json_decode(file_get_contents('php://input'), true);

// Log des données reçues
error_log('Données reçues: ' . print_r($data, true));
if (function_exists('logError')) {
    logError('api/admin/events/update_status.php', 'Payload reçu', ['payload' => $data]);
}

if (!isset($data['event_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes', 'debug' => $data]);
    exit();
}

$eventId = filter_var($data['event_id'], FILTER_VALIDATE_INT);
$status = trim(htmlspecialchars($data['status'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
$rejectionReason = isset($data['rejection_reason']) ? trim((string)$data['rejection_reason']) : '';

error_log('Après filtrage - eventId: ' . var_export($eventId, true) . ', status: ' . var_export($status, true));

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID d\'événement invalide', 'debug' => ['event_id' => $data['event_id']]]);
    exit();
}

if (!in_array($status, ['pending', 'approved', 'rejected'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Statut invalide', 'debug' => ['status' => $status, 'accepted_values' => ['pending', 'approved', 'rejected']]]);
    exit();
}

try {
    require_once __DIR__ . '/../../../config/database.php';
    $db = getConnection();

    // Vérifier si l'événement existe
    $checkStmt = $db->prepare('SELECT id, status FROM events WHERE id = ?');
    $checkStmt->execute([$eventId]);
    $event = $checkStmt->fetch();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Événement non trouvé']);
        exit();
    }

    error_log('Événement trouvé: ' . print_r($event, true));

    // Mettre à jour le statut de l'événement (+ raison si rejet)
    if ($status === 'rejected') {
        $stmt = $db->prepare('UPDATE events SET status = ?, rejection_reason = ? WHERE id = ?');
        $success = $stmt->execute([$status, ($rejectionReason !== '' ? $rejectionReason : null), $eventId]);
    } else {
        $stmt = $db->prepare('UPDATE events SET status = ?, rejection_reason = NULL WHERE id = ?');
        $success = $stmt->execute([$status, $eventId]);
    }

    if ($success) {
        error_log('Mise à jour réussie pour l\'événement ' . $eventId . ' avec le statut ' . $status);
        if (function_exists('logError')) {
            logError('api/admin/events/update_status.php', 'Statut mis à jour', [
                'event_id' => $eventId,
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
            ]);
        }

        // Récupérer les infos e-mail (utilisateur + profil organisateur)
        $infoStmt = $db->prepare(
            'SELECT e.*, u.email AS user_email, u.name AS user_name, op.email AS organizer_email, op.name AS organizer_name
             FROM events e
             LEFT JOIN users u ON e.user_id = u.id
             LEFT JOIN organizer_profiles op ON e.organizer_id = op.id
             WHERE e.id = ?'
        );
        $infoStmt->execute([$eventId]);
        $eventData = $infoStmt->fetch(PDO::FETCH_ASSOC);

        if ($eventData) {
            if ($status === 'rejected') {
                $eventData['rejection_reason'] = $rejectionReason;
            }
            $recipient = $eventData['organizer_email'] ?: $eventData['user_email'] ?: '';

            if ($recipient !== '') {
                try {
                    require_once __DIR__ . '/../../../includes/mailer.php';
                    $mailer = new Mailer();
                    $sent = false;
                    if ($status === 'approved') {
                        $sent = $mailer->sendEventApprovedEmail($recipient, $eventData);
                    } elseif ($status === 'rejected') {
                        $sent = $mailer->sendEventRejectedEmail($recipient, $eventData);
                    }
                    if (function_exists('logError')) {
                        logError('api/admin/events/update_status.php', 'Envoi e-mail statut', [
                            'event_id' => $eventId,
                            'status' => $status,
                            'to' => $recipient,
                            'result' => $sent ? 'ok' : 'fail'
                        ]);
                    }
                } catch (\Throwable $e) {
                    if (function_exists('logError')) {
                        logError('api/admin/events/update_status.php', 'Erreur envoi e-mail statut', [
                            'event_id' => $eventId,
                            'status' => $status,
                            'exception' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès']);
    } else {
        throw new Exception('Erreur lors de la mise à jour');
    }

} catch (Exception $e) {
    error_log('Erreur update_status.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Une erreur est survenue',
        'debug' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
