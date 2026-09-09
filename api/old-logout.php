<?php
// Démarrer le buffer de sortie
ob_start();

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 1728000');
header('Content-Type: application/json; charset=UTF-8');

// Gérer la requête OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Démarrer la session
    session_start();
    
    // Détruire la session
    session_destroy();
    
    // Supprimer le cookie de session
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    $response = [
        'status' => 'success',
        'message' => 'Déconnexion réussie'
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'status' => 'error',
        'message' => 'Erreur lors de la déconnexion'
    ];
}

// Vider le buffer de sortie
ob_end_clean();

// Envoyer la réponse JSON
echo json_encode($response);
