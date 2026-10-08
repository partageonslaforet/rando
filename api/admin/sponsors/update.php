<?php
/**
 * Mise à jour d'un sponsor (admin).
 * Champs éditables : name, link_url, alt_text, position.
 * Image optionnelle : nouveau fichier OU nouvelle URL externe remplace l'existante.
 * Pattern identique à /api/admin/categories/update.php.
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

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        sendJsonResponse(false, 'Identifiant invalide');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        sendJsonResponse(false, 'Le nom du partenaire est requis');
    }

    // Image optionnelle : remplacement si fichier ou URL fourni
    $newImage = handleSponsorImageUpload();
    if ($newImage === '') {
        $newImage = validHttpUrl((string) ($_POST['image_url'] ?? ''));
    }

    $linkUrl  = validHttpUrl((string) ($_POST['link_url'] ?? ''));
    $alt      = trim((string) ($_POST['alt_text'] ?? ''));
    $position = (int) ($_POST['position'] ?? 0);
    $start    = validSponsorDate($_POST['start_date'] ?? null, 'de début');
    $end      = validSponsorDate($_POST['end_date'] ?? null, 'de fin');
    if ($start !== null && $end !== null && $end < $start) {
        sendJsonResponse(false, 'La date de fin doit être postérieure à la date de début');
    }

    if ($newImage !== '') {
        // Récupère l'ancienne image pour nettoyage du fichier local
        $row = $db->prepare('SELECT image_path FROM sponsors WHERE id = ?');
        $row->execute([$id]);
        $old = $row->fetch(PDO::FETCH_ASSOC);

        $db->prepare('UPDATE sponsors SET name = ?, image_path = ?, link_url = ?, alt_text = ?, position = ?, start_date = ?, end_date = ? WHERE id = ?')
           ->execute([$name, $newImage, $linkUrl !== '' ? $linkUrl : null, $alt !== '' ? $alt : null, $position, $start, $end, $id]);

        if ($old) {
            deleteSponsorImageFile($old['image_path']);
        }
    } else {
        $db->prepare('UPDATE sponsors SET name = ?, link_url = ?, alt_text = ?, position = ?, start_date = ?, end_date = ? WHERE id = ?')
           ->execute([$name, $linkUrl !== '' ? $linkUrl : null, $alt !== '' ? $alt : null, $position, $start, $end, $id]);
    }

    sendJsonResponse(true, 'Sponsor mis à jour');

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError(basename(__FILE__), 'Erreur mise à jour sponsor', [
            'id' => $_POST['id'] ?? null, 'error' => $e->getMessage()
        ]);
    }
    sendJsonResponse(false, $e->getMessage());
}
