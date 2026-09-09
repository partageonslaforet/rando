<?php
// Activer les logs d'erreur
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Démarrer la session et définir les en-têtes
session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Logger les informations de la requête
error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('QUERY_STRING: ' . $_SERVER['QUERY_STRING']);
error_log('ACTION: ' . ($_GET['action'] ?? 'none'));

// Gérer les requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier si une action est spécifiée
if (!isset($_GET['action'])) {
    error_log('Aucune action spécifiée');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action non spécifiée']);
    exit;
}

try {
    error_log('Action demandée : ' . $_GET['action']);
    
    switch ($_GET['action']) {
        case 'login':
            error_log('Traitement de la requête de connexion');
            require_once __DIR__ . '/auth/login.php';
            break;
            
        case 'register':
            require_once __DIR__ . '/auth/register.php';
            break;
            
        case 'logout':
            require_once __DIR__ . '/auth/logout.php';
            break;
            
        default:
            error_log('Action inconnue : ' . $_GET['action']);
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint non trouvé']);
            exit;
    }
} catch (Exception $e) {
    error_log('Erreur API : ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur', 'debug' => $e->getMessage()]);
}
