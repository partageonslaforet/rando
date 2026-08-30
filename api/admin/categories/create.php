<?php
// Activer l'affichage des erreurs en mode debug
if (defined('DEBUG') && DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Models/EventCategory.php';

// Configurer le fichier de log
$logFile = __DIR__ . '/categories.log';

// Fonction pour logger les erreurs
function logError($message, $context = []) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? " Context: " . json_encode($context) : "";
    $logMessage = "[$timestamp] $message$contextStr\n";
    error_log($logMessage, 3, $logFile);
}

// Fonction pour envoyer une réponse JSON
function sendJsonResponse($success, $message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if (!$success && defined('DEBUG') && DEBUG) {
        $response['debug'] = $data;
    }
    
    // Logger la réponse
    $logMessage = "[$timestamp] Réponse envoyée: " . json_encode($response) . "\n";
    error_log($logMessage, 3, $logFile);
    
    echo json_encode($response);
    exit;
}

// Log du début de la requête
logError("Nouvelle requête de création de catégorie", [
    'POST' => $_POST,
    'SESSION' => isset($_SESSION['user']) ? ['id' => $_SESSION['user']['id'], 'role' => $_SESSION['user']['role']] : null
]);

// Vérifier les permissions d'administrateur
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    logError("Tentative d'accès non autorisé", ['user' => $_SESSION['user'] ?? null]);
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
        logError("Données manquantes", ['post' => $_POST]);
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
    
    logError("Tentative de création avec les données", $categoryData);
    
    $categoryId = $categoryManager->create($categoryData);

    if (!$categoryId) {
        throw new Exception('Erreur lors de la création de la catégorie');
    }

    logError("Catégorie créée avec succès", ['id' => $categoryId]);
    sendJsonResponse(true, 'Catégorie créée avec succès', ['id' => $categoryId]);

} catch (PDOException $e) {
    logError("Erreur PDO", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    sendJsonResponse(false, 'Erreur lors de la création de la catégorie', [
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("Erreur générale", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    sendJsonResponse(false, $e->getMessage(), [
        'error' => $e->getMessage()
    ]);
}
