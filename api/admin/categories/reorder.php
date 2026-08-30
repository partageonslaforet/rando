<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Models/EventCategory.php';

// Vérifier les permissions d'administrateur
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    // Récupérer les données JSON
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new Exception('Données invalides');
    }

    $categoryManager = new EventCategory($db);
    $success = $categoryManager->updateOrder($data);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Ordre mis à jour avec succès' : 'Erreur lors de la mise à jour de l\'ordre'
    ]);

} catch (Exception $e) {
    error_log('Erreur lors de la mise à jour de l\'ordre: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
