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
    if (!isset($data['id'])) {
        throw new Exception('ID de catégorie manquant');
    }

    $categoryManager = new EventCategory($db);
    $success = $categoryManager->delete($data['id']);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Catégorie supprimée avec succès' : 'Erreur lors de la suppression'
    ]);

} catch (Exception $e) {
    error_log('Erreur lors de la suppression de la catégorie: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
