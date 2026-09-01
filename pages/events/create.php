<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: /auth/login.php?redirect=' . urlencode('/events/create.php'));
    exit;
}

// Inclure le template
require_once __DIR__ . '/../../templates/events/create-event.php';
