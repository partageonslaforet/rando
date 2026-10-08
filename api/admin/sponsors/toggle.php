<?php
/**
 * Bascule actif/inactif d'un sponsor (admin).
 * Pattern identique à /api/admin/categories/toggle.php.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once __DIR__ . '/_helpers.php';

requireAdminApi();

try {
    $db = getConnection();
    if (!$db) {
        throw new Exception('Impossible de se connecter à la base de données');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($input['id'] ?? $_POST['id'] ?? 0);

    if ($id <= 0) {
        sendJsonResponse(false, 'Identifiant invalide');
    }

    $db->prepare('UPDATE sponsors SET active = 1 - active WHERE id = ?')->execute([$id]);

    sendJsonResponse(true, 'Statut mis à jour');

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur toggle sponsor', [
            'id' => $id ?? null, 'error' => $e->getMessage()
        ]);
    }
    sendJsonResponse(false, $e->getMessage());
}
