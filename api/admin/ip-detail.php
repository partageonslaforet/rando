<?php
/**
 * API détail d'une adresse IP pour le dashboard admin.
 * Interroge ip-api.com (champs étendus) et met à jour le cache ip_geo_cache.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json');

session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$ip = trim((string) ($_GET['ip'] ?? ''));
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IP invalide']);
    exit;
}

// IP privées/réservées : pas d'appel externe
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    echo json_encode(['success' => true, 'data' => [
        'ip' => $ip,
        'country' => 'Local',
        'city' => null,
        'local' => true,
    ]]);
    exit;
}

try {
    $context = stream_context_create(['http' => ['timeout' => 3]]);
    $response = @file_get_contents(
        'http://ip-api.com/json/' . urlencode($ip)
        . '?fields=status,message,country,regionName,city,zip,lat,lon,timezone,isp,org,as,query',
        false,
        $context
    );
    $data = $response ? json_decode($response, true) : null;

    if (!$data || ($data['status'] ?? '') !== 'success') {
        // Fallback sur le cache local si l'API externe échoue
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT country, city FROM ip_geo_cache WHERE ip_address = ?');
        $stmt->execute([$ip]);
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cached) {
            echo json_encode(['success' => true, 'data' => [
                'ip' => $ip,
                'country' => $cached['country'],
                'city' => $cached['city'],
                'cached' => true,
            ]]);
            exit;
        }
        throw new RuntimeException('ip-api.com : ' . ($data['message'] ?? 'service indisponible'));
    }

    // Mise à jour du cache (mêmes colonnes que resolveIpLocation)
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO ip_geo_cache (ip_address, country, city, resolved_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE country = VALUES(country), city = VALUES(city), resolved_at = NOW()'
        );
        $stmt->execute([$ip, $data['country'] ?? null, $data['city'] ?? null]);
    } catch (Throwable $e) {
        error_log('ip-detail cache: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'data' => [
        'ip' => $data['query'] ?? $ip,
        'country' => $data['country'] ?? null,
        'region' => $data['regionName'] ?? null,
        'city' => $data['city'] ?? null,
        'zip' => $data['zip'] ?? null,
        'lat' => $data['lat'] ?? null,
        'lon' => $data['lon'] ?? null,
        'timezone' => $data['timezone'] ?? null,
        'isp' => $data['isp'] ?? null,
        'org' => $data['org'] ?? null,
        'as' => $data['as'] ?? null,
    ]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
