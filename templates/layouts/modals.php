<?php
/**
 * localisation: templates/layouts/modals.php
 * Role: Layout : Modals
 * Usage: Chargement centralisé des modales du site
 * Dépendances: templates/modals/*.php
 */
// Inclusion des modales
$modalFiles = [
    'contact' => __DIR__ . '/../modals/contact.php',
    'forgot-password' => __DIR__ . '/../modals/forgot-password.php',
    'login' => __DIR__ . '/../modals/login.php',
    'register' => __DIR__ . '/../modals/register.php'
];

foreach ($modalFiles as $name => $file) {
    if (file_exists($file)) {
        include $file;
    }
}
?>
