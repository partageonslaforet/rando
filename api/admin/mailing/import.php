<?php
/**
 * localisation: api/admin/mailing/import.php
 * Role: Import CSV de contacts de publipostage (admin).
 *   ?mode=preview : parse le fichier uploadé, stocke le résultat en session,
 *                   renvoie lignes + stats (rien en DB).
 *   ?mode=commit  : insère les contacts de la session en DB
 *                   (INSERT … ON DUPLICATE KEY UPDATE — l'email unique
 *                   évite les doublons aux ré-imports).
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';
require_once __DIR__ . '/_helpers.php';

requireAdminApi();

$mode = $_GET['mode'] ?? 'preview';

try {
    if ($mode === 'preview') {
        if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            sendJsonResponse(false, 'Fichier CSV manquant ou illisible');
        }
        if ($_FILES['csv']['size'] > 5 * 1024 * 1024) {
            sendJsonResponse(false, 'Fichier trop volumineux (max 5 Mo)');
        }

        $parsed = parseMailingCsv($_FILES['csv']['tmp_name']);
        if (empty($parsed['rows'])) {
            sendJsonResponse(false, 'Aucune ligne exploitable dans ce fichier');
        }

        // Stocke les contacts validés en session pour le commit ultérieur.
        $_SESSION['mailing_import'] = $parsed['contacts'];

        // Combien d'emails existent déjà en base ?
        $existing = 0;
        $emails = array_column($parsed['contacts'], 'email');
        if ($emails) {
            $db = getConnection();
            $in = implode(',', array_fill(0, count($emails), '?'));
            $stmt = $db->prepare("SELECT COUNT(*) FROM mailing_contacts WHERE email IN ($in)");
            $stmt->execute($emails);
            $existing = (int) $stmt->fetchColumn();
        }

        sendJsonResponse(true, 'Analyse terminée', [
            'rows' => $parsed['rows'],
            'stats' => $parsed['stats'] + ['existing' => $existing, 'new' => $parsed['stats']['emails'] - $existing],
        ]);
    }

    if ($mode === 'commit') {
        $contacts = $_SESSION['mailing_import'] ?? [];
        unset($_SESSION['mailing_import']);
        if (empty($contacts)) {
            sendJsonResponse(false, 'Aucun import en attente — analysez d\'abord le fichier');
        }

        // Import partiel : le frontend envoie les emails cochés dans la preview.
        // Pas de liste (ancien appel sans body) → tout importer, comme avant.
        $payload  = json_decode(file_get_contents('php://input'), true) ?? [];
        $selected = $payload['emails'] ?? null;
        if (is_array($selected)) {
            $sel = array_flip(array_map('strtolower', array_map('strval', $selected)));
            $contacts = array_values(array_filter($contacts, function ($c) use ($sel) {
                return isset($sel[strtolower((string) $c['email'])]);
            }));
            if (empty($contacts)) {
                sendJsonResponse(false, 'Aucun contact sélectionné');
            }
        }

        $db = getConnection();
        if (!$db) {
            throw new Exception('Impossible de se connecter à la base de données');
        }

        $stmt = $db->prepare(
            'INSERT INTO mailing_contacts (club, contact_name, email, phone, lieu, type, status)
             VALUES (?, ?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                club = VALUES(club), contact_name = VALUES(contact_name),
                phone = VALUES(phone), lieu = VALUES(lieu), type = VALUES(type),
                updated_at = NOW()'
        );
        $done = 0;
        foreach ($contacts as $c) {
            $stmt->execute([$c['club'], $c['contact'] ?: null, $c['email'], $c['phone'] ?: null, $c['lieu'] ?: null, $c['type'] ?: null]);
            $done++;
        }

        sendJsonResponse(true, "$done contact(s) importé(s)", ['imported' => $done]);
    }

    sendJsonResponse(false, 'Mode inconnu');

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur import mailing', [
            'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()
        ]);
    }
    sendJsonResponse(false, $e->getMessage());
}
