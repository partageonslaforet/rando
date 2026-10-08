<?php
/**
 * Suppression d'un sponsor (admin).
 * Supprime la ligne + le fichier image local le cas échéant.
 * Pattern identique à /api/admin/categories/delete.php.
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

    // Accepte JSON ou POST classique
    $id = 0;
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input) && isset($input['id'])) {
        $id = (int) $input['id'];
    } elseif (isset($_POST['id'])) {
        $id = (int) $_POST['id'];
    }

    if ($id <= 0) {
        sendJsonResponse(false, 'Identifiant invalide');
    }

    $row = $db->prepare('SELECT image_path FROM sponsors WHERE id = ?');
    $row->execute([$id]);
    $sponsor = $row->fetch(PDO::FETCH_ASSOC);
    if (!$sponsor) {
        sendJsonResponse(false, 'Sponsor introuvable');
    }

    $db->prepare('DELETE FROM sponsors WHERE id = ?')->execute([$id]);
    deleteSponsorImageFile($sponsor['image_path']);

    sendJsonResponse(true, 'Sponsor supprimé');

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur suppression sponsor', [
            'id' => $id ?? null, 'error' => $e->getMessage()
        ]);
    }
    sendJsonResponse(false, $e->getMessage());
}
