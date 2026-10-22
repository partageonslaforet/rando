<?php
/**
 * localisation: api/admin/mailing/toggle.php
 * Role: Active/désactive un contact de publipostage (abonné ↔ désinscrit).
 * POST JSON { id, active }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once __DIR__ . '/_helpers.php';

requireAdminApi();

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $active = !empty($payload['active']) ? 1 : 0;

    // Batch {ids:[...]} ou unitaire {id}
    $ids = [];
    if (!empty($payload['ids']) && is_array($payload['ids'])) {
        $ids = array_values(array_filter(array_map('intval', $payload['ids']), fn($i) => $i > 0));
    } elseif (!empty($payload['id'])) {
        $ids = [(int) $payload['id']];
    }
    if (empty($ids)) {
        sendJsonResponse(false, 'Contact invalide');
    }

    $db = getConnection();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("UPDATE mailing_contacts SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)")
       ->execute(array_merge([$active], $ids));

    $n = count($ids);
    sendJsonResponse(true, $active
        ? ($n > 1 ? "$n contacts réactivés" : 'Contact réactivé')
        : ($n > 1 ? "$n contacts désinscrits" : 'Contact désinscrit'));

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur toggle contact mailing', ['error' => $e->getMessage()]);
    }
    sendJsonResponse(false, $e->getMessage());
}
