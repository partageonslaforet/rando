<?php
/**
 * Création d'un sponsor (admin).
 * Accepte un fichier image (upload) OU une URL externe.
 * Pattern identique à /api/admin/categories/create.php.
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

    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        sendJsonResponse(false, 'Le nom du partenaire est requis');
    }

    $imagePath = handleSponsorImageUpload();
    if ($imagePath === '') {
        $imagePath = validHttpUrl((string) ($_POST['image_url'] ?? ''));
    }
    if ($imagePath === '') {
        sendJsonResponse(false, 'Fournissez un fichier image ou une URL d\'image valide');
    }

    $linkUrl  = validHttpUrl((string) ($_POST['link_url'] ?? ''));
    $alt      = trim((string) ($_POST['alt_text'] ?? ''));
    $position = (int) ($_POST['position'] ?? 0);
    $start    = validSponsorDate($_POST['start_date'] ?? null, 'de début');
    $end      = validSponsorDate($_POST['end_date'] ?? null, 'de fin');
    if ($start !== null && $end !== null && $end < $start) {
        sendJsonResponse(false, 'La date de fin doit être postérieure à la date de début');
    }

    $db->prepare(
        'INSERT INTO sponsors (name, image_path, link_url, alt_text, position, active, start_date, end_date)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
    )->execute([$name, $imagePath, $linkUrl !== '' ? $linkUrl : null, $alt !== '' ? $alt : null, $position, $start, $end]);

    sendJsonResponse(true, 'Sponsor ajouté', ['id' => (int) $db->lastInsertId()]);

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur création sponsor', [
            'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()
        ]);
    }
    sendJsonResponse(false, $e->getMessage());
}
