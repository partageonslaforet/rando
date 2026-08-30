<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
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

if (!isset($data['event_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes', 'debug' => $data]);
    exit();
}

$eventId = filter_var($data['event_id'], FILTER_VALIDATE_INT);
$status = trim(htmlspecialchars($data['status'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

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

    // Mettre à jour le statut de l'événement
    $stmt = $db->prepare('UPDATE events SET status = ? WHERE id = ?');
    $success = $stmt->execute([$status, $eventId]);

    if ($success) {
        error_log('Mise à jour réussie pour l\'événement ' . $eventId . ' avec le statut ' . $status);
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
