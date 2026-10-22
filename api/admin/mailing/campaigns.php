<?php
/**
 * localisation: api/admin/mailing/campaigns.php
 * Role: CRUD des campagnes de publipostage enregistrées (mailing_campaigns).
 * POST JSON :
 *   { action: 'save',   id?, subject, body }  → crée ou met à jour
 *   { action: 'delete', id }                  → supprime
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once __DIR__ . '/_helpers.php';

requireAdminApi();

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $action  = (string) ($payload['action'] ?? '');
    $id      = (int) ($payload['id'] ?? 0);

    $db = getConnection();
    if (!$db) {
        throw new Exception('Impossible de se connecter à la base de données');
    }

    if ($action === 'save') {
        $subject = trim((string) ($payload['subject'] ?? ''));
        $body    = trim((string) ($payload['body'] ?? ''));
        if ($subject === '' || $body === '') {
            sendJsonResponse(false, 'Objet et message requis');
        }
        if ($id > 0) {
            $db->prepare('UPDATE mailing_campaigns SET subject = ?, body = ?, updated_at = NOW() WHERE id = ?')
               ->execute([$subject, $body, $id]);
            sendJsonResponse(true, 'Campagne mise à jour', ['id' => $id]);
        }
        $db->prepare('INSERT INTO mailing_campaigns (subject, body) VALUES (?, ?)')
           ->execute([$subject, $body]);
        sendJsonResponse(true, 'Campagne enregistrée', ['id' => (int) $db->lastInsertId()]);
    }

    if ($action === 'delete') {
        if ($id <= 0) {
            sendJsonResponse(false, 'Campagne invalide');
        }
        $db->prepare('DELETE FROM mailing_campaigns WHERE id = ?')->execute([$id]);
        sendJsonResponse(true, 'Campagne supprimée');
    }

    sendJsonResponse(false, 'Action inconnue');

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur campagne mailing', ['error' => $e->getMessage()]);
    }
    sendJsonResponse(false, $e->getMessage());
}
