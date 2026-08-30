<?php
session_start();

// Détruire la session
session_destroy();

// Supprimer le cookie de session
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Rediriger vers la page d'accueil
header('Location: /');
exit();
