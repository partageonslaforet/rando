<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Headers CORS et JSON
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Démarrer la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Détruire la session
    session_destroy();
    
    // Réponse de succès
    echo json_encode([
        'status' => 'success',
        'message' => 'Déconnexion réussie'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur de déconnexion: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Une erreur est survenue lors de la déconnexion'
    ]);
}
