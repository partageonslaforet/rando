<?php
/**
 * localisation: api/admin/mailing/preview.php
 * Role: Rend l'aperçu HTML d'un email mailing SANS l'envoyer.
 * POST JSON { subject, body, contact_id? }
 *   - contact_id > 0 : placeholders remplacés par ce contact (radio de la liste).
 *   - sinon          : contact factice.
 * Retour : { success, subject, html } — le HTML va dans un iframe (srcdoc).
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once __DIR__ . '/_helpers.php';

requireAdminApi();

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $subject = trim((string) ($payload['subject'] ?? ''));
    $body    = trim((string) ($payload['body'] ?? ''));

    if ($subject === '' || $body === '') {
        sendJsonResponse(false, 'Objet et message requis');
    }

    // Contact témoin : coché dans la liste, sinon 1er contact actif réel, sinon factice
    $contactId = (int) ($payload['contact_id'] ?? 0);
    $contact = null;
    $db = getConnection();
    if ($contactId > 0) {
        $stmt = $db->prepare('SELECT club, contact_name, lieu, type FROM mailing_contacts WHERE id = ?');
        $stmt->execute([$contactId]);
        $contact = $stmt->fetch() ?: null;
    }
    if ($contact === null) {
        $contact = $db->query('SELECT club, contact_name, lieu, type FROM mailing_contacts WHERE status = 1 ORDER BY id LIMIT 1')->fetch() ?: null;
    }
    if ($contact === null) {
        $contact = $db->query('SELECT club, contact_name, lieu, type FROM mailing_contacts ORDER BY id LIMIT 1')->fetch() ?: null;
    }
    if ($contact === null) {
        $contact = [
            'club'         => 'Club Exemple',
            'contact_name' => 'Jean Dupont',
            'lieu'         => 'Bellefontaine',
            'type'         => 'VTT Marche',
        ];
    }

    $persSubject = mailingPersonalize($subject, $contact);
    sendJsonResponse(true, 'ok', [
        'subject' => $persSubject,
        'html'    => mailingBuildHtml($persSubject, mailingPersonalize($body, $contact)),
    ]);
} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur aperçu mailing', ['error' => $e->getMessage()]);
    }
    sendJsonResponse(false, 'Erreur serveur');
}
