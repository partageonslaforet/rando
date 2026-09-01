<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: /auth/login.php?redirect=' . urlencode('/?create=1'));
    exit;
}

// La création se fait dans une modale sur la home page
header('Location: /?create=1');
exit;
