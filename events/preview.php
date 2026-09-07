<?php
/**
 * Redirection vers le contrôleur d'affichage partagé en mode prévisualisation.
 */
if (!isset($_GET['id'])) {
    header('Location: /');
    exit;
}

$id = (int) $_GET['id'];
header('Location: /event?preview=true&id=' . $id);
exit;
