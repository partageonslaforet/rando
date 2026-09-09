<?php
/**
 * Route publique canonique d'accès aux événements.
 * Délègue au contrôleur partagé sous /templates/events/event-detail.php.
 *
 * Utilisé par : index.php, pages/user/my-events.php, templates/events/event-card.php,
 *               templates/js/event-validation.js, public/assets/js/event-edit.js,
 *               api/events/participants.php
 */
require_once __DIR__ . '/../templates/events/event-detail.php';
