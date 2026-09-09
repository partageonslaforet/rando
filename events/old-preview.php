<?php
/**
 * Redirection vers le contrôleur d'affichage partagé en mode prévisualisation.
 *
 * Utilisé par : public/assets/js/event-validation.js (appels AJAX de prévisualisation)
 */
if (!isset($_GET['id'])) {
    header('Location: /');
    exit;
}

$id = (int) $_GET['id'];
header('Location: /event?preview=true&id=' . $id);
exit;
