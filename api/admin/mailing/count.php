<?php
/**
 * localisation: api/admin/mailing/count.php
 * Role: Renvoie le nombre réel d'abonnés (status=1) en base — utilisé par la
 *       modale de confirmation d'envoi campagne pour afficher le vrai nombre
 *       de destinataires (et pas l'état DOM, qui peut être désynchronisé).
 * GET → { success, message, data: { count } }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once __DIR__ . '/_helpers.php';

requireAdminApi();

try {
    $db = getConnection();
    $count = (int) $db->query('SELECT COUNT(*) FROM mailing_contacts WHERE status = 1')->fetchColumn();
    sendJsonResponse(true, 'ok', ['count' => $count]);
} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur comptage abonnés mailing', ['error' => $e->getMessage()]);
    }
    sendJsonResponse(false, $e->getMessage());
}
