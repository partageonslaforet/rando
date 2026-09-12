<?php
// Cron: Marquer comme "expired" les événements approuvés dont la date est passée (J+1)
// Et réactiver "approved" si la date a été repoussée au présent/futur
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../logs/error.log.php';

    // Vérification de la clé de sécurité (HTTP ?key=... ou 1er argument CLI)
    $expectedKey = $_ENV['CRON_KEY'] ?? $_ENV['MIGRATE_KEY'] ?? '';
    $providedKey = $_GET['key'] ?? ($_SERVER['argv'][1] ?? '');
    if ($expectedKey === '' || !hash_equals($expectedKey, (string)$providedKey)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Clé invalide ou manquante'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $db = getConnection();

    $db->beginTransaction();

    // Expirer les approuvés dont la date est strictement inférieure à aujourd'hui
    $stmt1 = $db->prepare("UPDATE events SET status='expired' WHERE status='approved' AND date < CURDATE()");
    $stmt1->execute();
    $expiredCount = $stmt1->rowCount();

    // Réapprouver si la date est revenue à aujourd'hui ou plus tard (optionnel mais utile)
    $stmt2 = $db->prepare("UPDATE events SET status='approved' WHERE status='expired' AND date >= CURDATE()");
    $stmt2->execute();
    $reapprovedCount = $stmt2->rowCount();

    $db->commit();

    if (function_exists('logError')) {
        logError('cron/expire_events.php', 'Cron exécuté', [
            'expired' => $expiredCount,
            'reapproved' => $reapprovedCount,
        ]);
    }

    echo json_encode([
        'success' => true,
        'expired' => $expiredCount,
        'reapproved' => $reapprovedCount,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if (function_exists('logError')) {
        logError('cron/expire_events.php', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
