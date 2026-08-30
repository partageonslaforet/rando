<?php
// Démarrer la session
session_start();

// Log de déconnexion
error_log('🚪 Déconnexion de l\'utilisateur: ' . ($_SESSION['user_email'] ?? 'inconnu'));

// Supprimer le cookie "Se souvenir de moi" s'il existe
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Détruire la session
session_destroy();

// Répondre en JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Déconnexion réussie'
]);
