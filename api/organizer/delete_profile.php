<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/organizer_profile.php';

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// S'assurer qu'aucune sortie n'a été envoyée avant
ob_start();

// Définir les en-têtes
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');

// Gérer les requêtes OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    ob_clean();
    echo json_encode($data);
    exit();
}

// Log pour le débogage
error_log('DELETE PROFILE - Début de la requête');
error_log('Session user_id: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'non défini'));

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    error_log('DELETE PROFILE - Utilisateur non connecté');
    sendJsonResponse(['success' => false, 'message' => 'Non autorisé'], 401);
}

// Vérifier si la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('DELETE PROFILE - Méthode non autorisée: ' . $_SERVER['REQUEST_METHOD']);
    sendJsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
}

try {
    // Récupérer et logger les données brutes
    $rawData = file_get_contents('php://input');
    error_log('DELETE PROFILE - Données brutes reçues : ' . $rawData);
    
    // Décoder les données JSON
    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('DELETE PROFILE - Erreur de décodage JSON : ' . json_last_error_msg());
        throw new Exception('Erreur de décodage JSON : ' . json_last_error_msg());
    }
    
    // Logger les données décodées
    error_log('DELETE PROFILE - Données décodées : ' . print_r($data, true));
    
    // Valider les données requises
    if (empty($data['profile_id'])) {
        error_log('DELETE PROFILE - ID du profil manquant');
        throw new Exception('ID du profil requis');
    }

    error_log('DELETE PROFILE - Tentative de connexion à la base de données');
    $db = getConnection();
    error_log('DELETE PROFILE - Connexion à la base de données établie');

    // Supprimer le profil
    error_log('DELETE PROFILE - Création de l\'instance OrganizerProfile');
    $organizerProfile = new OrganizerProfile($db, $_SESSION['user_id']);
    
    error_log('DELETE PROFILE - Tentative de suppression du profil ' . $data['profile_id']);
    $result = $organizerProfile->delete($data['profile_id']);

    if ($result) {
        $response = [
            'success' => true,
            'message' => 'Profil supprimé avec succès'
        ];
        error_log('DELETE PROFILE - Succès : ' . print_r($response, true));
        sendJsonResponse($response);
    } else {
        error_log('DELETE PROFILE - Échec de la suppression sans erreur');
        throw new Exception('Erreur lors de la suppression du profil');
    }

} catch (Exception $e) {
    error_log('DELETE PROFILE - Erreur : ' . $e->getMessage());
    error_log('DELETE PROFILE - Fichier : ' . $e->getFile() . ' Ligne : ' . $e->getLine());
    error_log('DELETE PROFILE - Trace : ' . $e->getTraceAsString());
    
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ];
    sendJsonResponse($response, 500);
}
