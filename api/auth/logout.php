<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    session_start();

    // Détruire la session
    $_SESSION = array();
    session_destroy();

    // Supprimer le cookie de session
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Supprimer le cookie remember_token s'il existe
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Déconnexion réussie'
    ]);
}
