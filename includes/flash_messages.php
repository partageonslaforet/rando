<?php
// Démarrer la session si elle n'est pas déjà active
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

if (!function_exists('addFlashMessage')) {
    // Fonction pour ajouter un message flash
    function addFlashMessage($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    if (!isset($_SESSION['flash_messages'][$type])) {
        $_SESSION['flash_messages'][$type] = [];
    }
    $_SESSION['flash_messages'][$type][] = $message;
    }
}

if (!function_exists('getFlashMessages')) {
    // Fonction pour récupérer et effacer les messages
    function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
        return $messages;
    }
}

if (!function_exists('displayFlashMessages')) {
    // Fonction pour afficher les messages
    function displayFlashMessages() {
    if (!isset($_SESSION['flash_messages'])) {
        return;
    }

    echo "<div class='flash-messages' role='region' aria-label='Messages flash'>";
    foreach ($_SESSION['flash_messages'] as $type => $messages) {
        foreach ($messages as $message) {
            echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars($message);
            echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
            echo "</div>";
        }
    }
    echo "</div>";

        // Clear flash messages after displaying them
        unset($_SESSION['flash_messages']);
    }
}
