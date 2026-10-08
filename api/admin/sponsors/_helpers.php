<?php
/**
 * Helpers partagés des endpoints sponsors (admin).
 * Réponse JSON + logging centralisé + validation URL + upload image.
 */

require_once __DIR__ . '/../../../src/Services/Storage.php';

const SPONSOR_UPLOAD_DIR = '/assets/images/sponsors/'; // legacy : anciens visuels
const SPONSOR_STORAGE_PREFIX = '/uploads/sponsors/';   // nouveau : via Storage
const SPONSOR_MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 Mo
const SPONSOR_ALLOWED_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

/** Envoie une réponse JSON et termine la requête (avec log centralisé). */
function sendJsonResponse(bool $success, string $message, ?array $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if (function_exists('logError')) {
        logError(basename($_SERVER['SCRIPT_NAME'] ?? __FILE__), $message, ['success' => $success, 'payload' => $data]);
    }
    echo json_encode($response);
    exit;
}

/** Vérifie que l'utilisateur connecté est admin, sinon réponse 403 JSON. */
function requireAdminApi() {
    $role = $_SESSION['user_role'] ?? ($_SESSION['user']['role'] ?? null);
    if ($role !== 'admin') {
        if (function_exists('logError')) {
            logError(basename($_SERVER['SCRIPT_NAME'] ?? __FILE__), "Tentative d'accès non autorisé", ['role' => $role]);
        }
        sendJsonResponse(false, 'Accès non autorisé');
    }
}

/** Normalise/valide une URL externe http(s). Retourne l'URL ou ''. */
function validHttpUrl(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

/** Valide une date optionnelle au format Y-m-d. Retourne la date normalisée ou null. */
function validSponsorDate(?string $value, string $field): ?string {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) {
        throw new RuntimeException("Date $field invalide (format attendu AAAA-MM-JJ).");
    }
    return $value;
}

/** Traite l'upload du champ 'image' via le service Storage. Retourne l'URL publique ou ''. */
function handleSponsorImageUpload(): string {
    if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    $f = $_FILES['image'];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > SPONSOR_MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Image invalide ou trop lourde (max 2 Mo).');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(SPONSOR_ALLOWED_MIME[$mime])) {
        throw new RuntimeException('Format non supporté (jpg, png, webp, gif).');
    }
    $name = 'sponsor_' . bin2hex(random_bytes(8)) . '.' . SPONSOR_ALLOWED_MIME[$mime];
    $result = Storage::saveUploadedFile($f, 'sponsors', $name);
    return $result['public_url'] ?? '';
}

/** Supprime le fichier local d'un sponsor (ignore les URL externes). */
function deleteSponsorImageFile(string $imagePath): void {
    if ($imagePath === '') {
        return;
    }
    // Nouveau stockage via Storage (/uploads/sponsors/<fichier>)
    if (strpos($imagePath, SPONSOR_STORAGE_PREFIX) === 0) {
        Storage::deleteFile('sponsors', basename($imagePath));
        return;
    }
    // Ancien stockage (/assets/images/sponsors/<fichier>)
    if (strpos($imagePath, SPONSOR_UPLOAD_DIR) === 0) {
        $file = dirname(__DIR__, 3) . '/public' . $imagePath;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
