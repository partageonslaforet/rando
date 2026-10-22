<?php
/**
 * localisation: api/admin/mailing/send.php
 * Role: Envoie une campagne de publipostage aux contacts mailing_contacts actifs.
 * POST JSON { subject, body, test_to? }
 *   - test_to : envoie UN seul email de test à cette adresse (placeholders factices).
 *   - sinon   : boucle sur tous les contacts status=1, email individuel (To: réel),
 *               placeholders {club} {contact} {lieu} {type}, 0,5 s entre envois.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
set_time_limit(300);

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once __DIR__ . '/../../../includes/mailer.php';
require_once __DIR__ . '/_helpers.php';

requireAdminApi();

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $subject    = trim((string) ($payload['subject'] ?? ''));
    $body       = trim((string) ($payload['body'] ?? ''));
    $testTo     = trim((string) ($payload['test_to'] ?? ''));
    $campaignId = (int) ($payload['campaign_id'] ?? 0) ?: null;
    $utmCampaign = $campaignId ? 'campaign_' . $campaignId : 'manual';

    if ($subject === '' || $body === '') {
        sendJsonResponse(false, 'Objet et message requis');
    }

    $mailer = new Mailer();

    // --- Mode envoi unitaire : un seul contact (bouton email de la liste) ---
    $contactId = (int) ($payload['contact_id'] ?? 0);
    if ($contactId > 0) {
        $db = getConnection();
        $stmt = $db->prepare('SELECT club, contact_name, email, lieu, type FROM mailing_contacts WHERE id = ?');
        $stmt->execute([$contactId]);
        $c = $stmt->fetch();
        if (!$c) {
            sendJsonResponse(false, 'Contact introuvable');
        }
        $persSubject = mailingPersonalize($subject, $c);
        $persBody    = mailingPersonalize($body, $c);
        $ok = $mailer->sendHtml(
            $c['email'],
            $persSubject,
            mailingUtmLinks(mailingBuildHtml($persSubject, $persBody), $utmCampaign),
            mailingUtmLinks($persBody, $utmCampaign),
            'mailing_single'
        );
        mailingLogSend($contactId, $c['email'], $persSubject, $ok, $ok ? null : 'sendHtml a renvoyé false', false, $campaignId);
        if (!$ok) {
            sendJsonResponse(false, "Échec de l'envoi vers {$c['email']}");
        }
        if (function_exists('logError')) {
            logError(basename(__FILE__), 'Envoi unitaire mailing', ['to' => $c['email'], 'subject' => $persSubject]);
        }
        sendJsonResponse(true, "Email envoyé à {$c['email']}");
    }

    // --- Mode test : un seul envoi avec des données factices ---
    if ($testTo !== '') {
        if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(false, 'Adresse de test invalide');
        }
        $sample = ['club' => 'Club Exemple', 'contact_name' => 'Jean Dupont', 'lieu' => 'Bellefontaine', 'type' => 'VTT Marche'];
        $ok = $mailer->sendHtml(
            $testTo,
            mailingPersonalize($subject, $sample),
            mailingUtmLinks(mailingBuildHtml(mailingPersonalize($subject, $sample), mailingPersonalize($body, $sample)), $utmCampaign),
            mailingUtmLinks(mailingPersonalize($body, $sample), $utmCampaign),
            'mailing_test'
        );
        mailingLogSend(null, $testTo, mailingPersonalize($subject, $sample), $ok, $ok ? null : 'sendHtml a renvoyé false', true, $campaignId);
        if (!$ok) {
            sendJsonResponse(false, "Échec de l'envoi de test vers $testTo");
        }
        sendJsonResponse(true, "Email de test envoyé à $testTo");
    }

    // --- Campagne réelle ---
    $db = getConnection();
    $contacts = $db->query('SELECT id, club, contact_name, email, lieu, type FROM mailing_contacts WHERE status = 1 ORDER BY id ASC')->fetchAll();
    if (empty($contacts)) {
        sendJsonResponse(false, 'Aucun contact abonné');
    }
    if (count($contacts) > 500) {
        sendJsonResponse(false, 'Campagne refusée : plus de 500 destinataires');
    }

    $results = [];
    $sent = 0;
    $failed = 0;
    $first = true;

    foreach ($contacts as $c) {
        if (!$first) {
            usleep(500000); // 0,5 s entre envois — ménage le serveur SMTP
        }
        $first = false;

        $persSubject = mailingPersonalize($subject, $c);
        $persBody    = mailingPersonalize($body, $c);
        $err = null;
        try {
            $ok = $mailer->sendHtml(
                $c['email'],
                $persSubject,
                mailingUtmLinks(mailingBuildHtml($persSubject, $persBody), $utmCampaign),
                mailingUtmLinks($persBody, $utmCampaign),
                'mailing_campaign'
            );
        } catch (Throwable $e) {
            $ok  = false;
            $err = $e->getMessage();
        }
        mailingLogSend((int) $c['id'], $c['email'], $persSubject, $ok, $err, false, $campaignId);

        if ($ok) {
            $sent++;
            $results[] = ['email' => $c['email'], 'ok' => true];
        } else {
            $failed++;
            $results[] = ['email' => $c['email'], 'ok' => false];
            if (function_exists('logError')) {
                logError(basename(__FILE__), 'Échec envoi campagne', ['to' => $c['email'], 'subject' => $persSubject]);
            }
        }
    }

    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Campagne terminée', ['sent' => $sent, 'failed' => $failed, 'subject' => $subject]);
    }
    sendJsonResponse(true, "Campagne terminée : $sent envoyé(s), $failed échec(s)", [
        'sent' => $sent, 'failed' => $failed, 'results' => $results,
    ]);

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur campagne mailing', [
            'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()
        ]);
    }
    sendJsonResponse(false, $e->getMessage());
}
