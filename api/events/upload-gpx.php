<?php
/**
 * Upload et validation d'un fichier GPX pour un parcours.
 * Contrôle le type MIME, enregistre le fichier et retourne son chemin.
 *
 * Utilisé par : public/assets/js/event-edit.js, public/assets/js/event-gpx.js, public/assets/js/event-validation.js
 */

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../src/Services/Storage.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    if (!isset($_FILES['gpx_file'])) {
        throw new Exception('Aucun fichier GPX fourni');
    }

    $file = $_FILES['gpx_file'];
    // route_index ne doit contenir que des caractères sûrs (index numérique côté JS)
    $route_index = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['route_index'] ?? 'default'));
    if ($route_index === '') {
        $route_index = 'default';
    }

    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erreur lors de l\'upload: ' . $file['error']);
    }

    // Taille maximale : 5 Mo (parité avec config/storage.php)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Fichier trop volumineux');
    }

    // Extension : whitelist stricte (jamais reprise du nom client sans contrôle)
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['gpx', 'xml'], true)) {
        throw new Exception('Extension de fichier non autorisée');
    }

    // Vérifier le type de fichier
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_types = ['application/xml', 'text/xml', 'text/plain'];
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Type de fichier non autorisé');
    }

    // Générer un nom de fichier unique
    $filename = 'temp/' . uniqid('gpx_') . '_' . $route_index . '.' . $extension;
    $filepath = Storage::getStoragePath('gpx', $filename);

    // Créer le dossier de destination s'il n'existe pas
    Storage::ensureDirectoryExists(dirname($filepath));

    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Erreur lors du déplacement du fichier');
    }

    // Construire l'URL complète
    $relativePath = Storage::getPublicUrl('gpx', $filename);
    $fullUrl = getFullUrl($relativePath);

    echo json_encode([
        'success' => true,
        'gpx_path' => $relativePath,
        'gpx_url' => $fullUrl
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
