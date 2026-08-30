<?php
// Inclusion des modales
$modalFiles = [
    'contact' => __DIR__ . '/../templates/modals/contact.php',
    'forgot-password' => __DIR__ . '/../templates/modals/forgot-password.php',
    'login' => __DIR__ . '/../templates/modals/login.php',
    'register' => __DIR__ . '/../templates/modals/register.php'
];

foreach ($modalFiles as $name => $file) {
    if (file_exists($file)) {
        include $file;
    }
}
?>
