<?php
// Inclure les fonctions
require_once '/home/cool5792/rando.partageonslaforet.be/includes/functions.php';

// En-têtes
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Vérifier si l'utilisateur est connecté
$isAuthenticated = isLoggedIn();

// Log pour le débogage
error_log(' Vérification auth - Utilisateur connecté: ' . ($isAuthenticated ? 'Oui' : 'Non'));

// Renvoyer le statut
echo json_encode([
    'authenticated' => $isAuthenticated,
    'timestamp' => time()
]);
