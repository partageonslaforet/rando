<?php

require_once __DIR__ . '/../Services/Storage.php';

/**
 * Gère l'upload d'un fichier GPX
 * 
 * @param array $file Tableau contenant les informations du fichier ($_FILES)
 * @return string|false Le chemin du fichier GPX ou false en cas d'erreur
 */
function handleGpxUpload($file) {
    // Vérifier s'il y a une erreur
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Erreur lors de l'upload du fichier GPX: " . $file['error']);
        return false;
    }

    // Vérifier le type de fichier
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Accepter les fichiers GPX (application/xml, text/xml)
    if (!in_array($mimeType, ['application/xml', 'text/xml'])) {
        error_log("Type de fichier GPX non valide: " . $mimeType);
        return false;
    }

    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;

    $targetPath = Storage::getStoragePath('gpx', $filename);
    $uploadDir = dirname($targetPath);

    // Créer le dossier de destination s'il n'existe pas
    try {
        Storage::ensureDirectoryExists($uploadDir);
    } catch (Exception $e) {
        error_log("Erreur lors de la création du dossier GPX: " . $uploadDir);
        return false;
    }

    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        error_log("Erreur lors du déplacement du fichier GPX");
        return false;
    }

    // Retourner le chemin public relatif
    return Storage::getPublicUrl('gpx', $filename);
}
