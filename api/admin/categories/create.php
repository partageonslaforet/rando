<?php
// Activer l'affichage des erreurs en mode debug
if (defined('DEBUG') && DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Includes robustes basés sur __DIR__
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/src/Models/EventCategory.php';

// Fonction pour envoyer une réponse JSON (avec log centralisé)
function sendJsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if (function_exists('logError')) {
        logError(basename(__FILE__), $message, ['success' => $success, 'payload' => $data]);
    }
    echo json_encode($response);
    exit;
}

// Log du début de la requête
if (function_exists('logError')) {
    logError(basename(__FILE__), 'Nouvelle requête de création de catégorie', [
        'POST' => $_POST,
        'SESSION' => isset($_SESSION['user']) ? ['id' => $_SESSION['user']['id'] ?? null, 'role' => $_SESSION['user']['role'] ?? null] : null
    ]);
}

// Vérifier les permissions d'administrateur
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    if (function_exists('logError')) {
        logError(basename(__FILE__), "Tentative d'accès non autorisé", ['user' => $_SESSION['user'] ?? null]);
    }
    sendJsonResponse(false, 'Accès non autorisé');
}

try {
    $db = getConnection();
    if (!$db) {
        throw new Exception('Impossible de se connecter à la base de données');
    }
    
    $categoryManager = new EventCategory($db);
    
    // Valider les données requises
    if (empty($_POST['code']) || empty($_POST['name'])) {
        if (function_exists('logError')) { logError(basename(__FILE__), "Données manquantes", ['post' => $_POST]); }
        sendJsonResponse(false, 'Le code et le nom sont requis');
    }

    // Créer la catégorie
    $categoryData = [
        'code' => $_POST['code'],
        'name' => $_POST['name'],
        'icon' => $_POST['icon'] ?? null,
        'color' => $_POST['color'] ?? null,
        'active' => true,
        'sort_order' => 9999
    ];
    
    if (function_exists('logError')) { logError(basename(__FILE__), "Tentative de création avec les données", $categoryData); }
    
    $categoryId = $categoryManager->create($categoryData);

    if (!$categoryId) {
        throw new Exception('Erreur lors de la création de la catégorie');
    }

    if (function_exists('logError')) { logError(basename(__FILE__), "Catégorie créée avec succès", ['id' => $categoryId]); }
    sendJsonResponse(true, 'Catégorie créée avec succès', ['id' => $categoryId]);

} catch (PDOException $e) {
    if (function_exists('logError')) { logError(basename(__FILE__), "Erreur PDO", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]); }
    sendJsonResponse(false, 'Erreur lors de la création de la catégorie', [
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    if (function_exists('logError')) { logError(basename(__FILE__), "Erreur générale", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]); }
    sendJsonResponse(false, $e->getMessage(), [
        'error' => $e->getMessage()
    ]);
}
