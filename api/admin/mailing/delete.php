<?php
/**
 * localisation: api/admin/mailing/delete.php
 * Role: Supprime un contact de publipostage.
 * POST JSON { id }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once __DIR__ . '/_helpers.php';

requireAdminApi();

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($payload['id'] ?? 0);
    if ($id <= 0) {
        sendJsonResponse(false, 'Contact invalide');
    }

    $db = getConnection();
    $db->prepare('DELETE FROM mailing_contacts WHERE id = ?')->execute([$id]);

    sendJsonResponse(true, 'Contact supprimé');

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur suppression contact mailing', ['error' => $e->getMessage()]);
    }
    sendJsonResponse(false, $e->getMessage());
}
