<?php
/**
 * Suppression d'un fichier GPX temporaire.
 * Contrôle que le fichier est bien dans le répertoire GPX autorisé.
 *
 * Utilisé par : public/assets/js/events/event-validation.js
 */

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../src/Services/Storage.php';
require_once __DIR__ . '/../../logs/error.log.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['gpx_path'])) {
        throw new Exception('gpx_path manquant');
    }

    $gpxPath = trim($input['gpx_path']);
    if ($gpxPath === '') {
        throw new Exception('gpx_path vide');
    }

    // Extraire le chemin relatif (hors du domaine éventuel)
    $parsed = parse_url($gpxPath, PHP_URL_PATH);
    $path = $parsed !== false && $parsed !== null ? $parsed : $gpxPath;

    // Vérifier qu'on reste dans /uploads/gpx/
    $expectedPrefix = '/uploads/gpx/';
    if (strpos($path, $expectedPrefix) !== 0) {
        throw new Exception('Chemin non autorisé');
    }

    $relative = substr($path, strlen($expectedPrefix));
    if (strpos($relative, '..') !== false || strpos($relative, './') !== false) {
        throw new Exception('Chemin invalide');
    }

    // Résoudre le chemin physique
    $gpxBase = dirname(Storage::getStoragePath('gpx', ''));
    $gpxBaseReal = realpath($gpxBase);
    if ($gpxBaseReal === false) {
        throw new Exception('Répertoire GPX introuvable');
    }

    $target = Storage::getStoragePath('gpx', $relative);
    $targetDirReal = realpath(dirname($target));
    if ($targetDirReal === false || strpos($targetDirReal, $gpxBaseReal) !== 0) {
        throw new Exception('Fichier hors du répertoire autorisé');
    }

    if (!file_exists($target) || !is_file($target)) {
        throw new Exception('Fichier introuvable');
    }

    if (!unlink($target)) {
        throw new Exception('Impossible de supprimer le fichier');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    logError('delete-gpx.php', $e->getMessage(), ['gpx_path' => $gpxPath ?? null]);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
