<?php
/**
 * Mise à jour d'une catégorie d'événement existante (admin).
 * Récupère les données POST, valide l'ID et modifie les champs.
 */

// Démarrer la capture de sortie immédiatement
ob_start();

// Includes robustes (depuis api/admin/categories/ -> racine)
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once ROOT_PATH . '/includes/auth_check.php';
require_once ROOT_PATH . '/src/Models/EventCategory.php';

// Nettoyer toute sortie potentielle des includes
ob_clean();

// S'assurer que la réponse sera en JSON
header('Content-Type: application/json');

function sendJsonResponse($success, $message, $debug = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'debug' => $debug
    ]);
    exit;
}

try {
    // Initialiser le tableau de debug
    $debug = [
        'post' => $_POST,
        'method' => $_SERVER['REQUEST_METHOD'],
        'document_root' => $_SERVER['DOCUMENT_ROOT'],
        'server' => [
            'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'non défini',
            'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'non défini',
            'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'non défini'
        ]
    ];

    // Obtenir la connexion à la base de données
    $db = getConnection();
    if (!$db) {
        throw new Exception('Impossible de se connecter à la base de données');
    }

    // Test de la connexion
    try {
        $testStmt = $db->prepare('SELECT 1');
        $testStmt->execute();
        $debug['db_test'] = 'Connexion à la base de données OK';
    } catch (PDOException $e) {
        $debug['db_test'] = 'Erreur de connexion à la base de données: ' . $e->getMessage();
        throw new Exception('Erreur de connexion à la base de données');
    }

    // Vérifier les permissions d'administrateur
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        $debug['auth'] = [
            'session_exists' => isset($_SESSION),
            'user_exists' => isset($_SESSION['user']),
            'user_role' => $_SESSION['user']['role'] ?? 'non défini'
        ];
        sendJsonResponse(false, 'Accès non autorisé', $debug, 403);
    }

    // Vérifier que la requête est en POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée: ' . $_SERVER['REQUEST_METHOD']);
    }

    // Récupérer et valider les données
    $rawId = $_POST['id'] ?? null;
    $rawCode = $_POST['code'] ?? null;
    $rawName = $_POST['name'] ?? null;

    $debug['validation'] = [
        'rawId' => $rawId,
        'rawCode' => $rawCode,
        'rawName' => $rawName
    ];

    if (empty($rawId) || empty($rawCode) || empty($rawName)) {
        throw new Exception(
            'Données manquantes - ID: ' . (empty($rawId) ? 'manquant' : 'présent') .
            ', Code: ' . (empty($rawCode) ? 'manquant' : 'présent') .
            ', Name: ' . (empty($rawName) ? 'manquant' : 'présent')
        );
    }

    $id = filter_var($rawId, FILTER_VALIDATE_INT);
    if ($id === false) {
        throw new Exception('ID invalide: ' . $rawId);
    }

    $categoryManager = new EventCategory($db);
    
    // Vérifier que la catégorie existe
    $existingCategory = $categoryManager->getById($id);
    if (!$existingCategory) {
        throw new Exception('Catégorie non trouvée avec l\'ID: ' . $id);
    }

    $debug['existing_category'] = $existingCategory;

    // Préparer les données pour la mise à jour
    $updateData = [
        'code' => $rawCode,
        'name' => $rawName
    ];

    // Ajouter les champs optionnels s'ils sont présents
    if (isset($_POST['icon'])) {
        $updateData['icon'] = $_POST['icon'];
    }
    if (isset($_POST['color'])) {
        $updateData['color'] = $_POST['color'];
    }

    $debug['update_data'] = $updateData;

    // Mettre à jour la catégorie
    $success = $categoryManager->update($id, $updateData);

    if (!$success) {
        throw new Exception('Échec de la mise à jour de la catégorie');
    }

    sendJsonResponse(true, 'Catégorie mise à jour avec succès', $debug);

} catch (Exception $e) {
    $debug['error'] = [
        'type' => 'Exception',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    if (function_exists('logError')) {
        logError(basename(__FILE__), $e->getMessage(), $debug);
    }
    sendJsonResponse(false, $e->getMessage(), $debug, 400);
} catch (Error $e) {
    $debug['error'] = [
        'type' => 'Error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    if (function_exists('logError')) {
        logError(basename(__FILE__), $e->getMessage(), $debug);
    }
    sendJsonResponse(false, 'Une erreur interne est survenue', $debug, 500);
}
