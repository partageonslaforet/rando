<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/functions.php';
    require_once __DIR__ . '/../../logs/error.log.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $draftId = isset($_GET['draft_id']) ? (int)$_GET['draft_id'] : 0;
    if ($draftId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'draft_id manquant']);
        exit;
    }

    $db = getConnection();

    // Vérifier ownership du brouillon
    $stmt = $db->prepare('SELECT user_id FROM draft_events WHERE id = ?');
    $stmt->execute([$draftId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Brouillon introuvable']);
        exit;
    }
    $isOwner = ((int)$row['user_id'] === (int)($_SESSION['user_id'] ?? 0));
    $isAdmin = (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin')
            || (($_SESSION['user_role'] ?? '') === 'admin');
    if (!$isOwner && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès interdit']);
        exit;
    }

    // Récupérer images
    $main = null;
    $secondary = [];

    $imgStmt = $db->prepare('SELECT id, image_path, storage_path, is_main FROM draft_images WHERE event_id = ? ORDER BY id');
    $imgStmt->execute([$draftId]);
    while ($img = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
        $path = resolveImagePublicUrl($img['image_path'], $img['storage_path']);
        if (!$path) {
            continue;
        }

        $record = [
            'id' => (int)$img['id'],
            'path' => $path,
            'storage_path' => $img['storage_path'],
            'is_main' => (int)$img['is_main'],
        ];
        if ((int)$img['is_main'] === 1) {
            $main = $record;
        } else {
            $secondary[] = $record;
        }
    }

    $json = json_encode([
        'success' => true,
        'mainImage' => $main,
        'secondaryImages' => $secondary,
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $json !== false ? $json : json_encode(['success' => false, 'message' => 'Erreur d\'encodage JSON'], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError('get_draft_images.php', $e->getMessage(), []);
    }
    http_response_code(500);
    $json = json_encode(['success' => false, 'message' => 'Erreur serveur'], JSON_INVALID_UTF8_SUBSTITUTE);
    echo $json !== false ? $json : '{"success":false,"message":"Erreur"}';
}
