<?php
header('Content-Type: application/json; charset=utf-8');
$rootPath = realpath(__DIR__ . '/../..');
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/logs/error.log.php';

try {
    $token = $_GET['token'] ?? '';
    if (!$token) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Token manquant']); exit; }

    $pdo = getConnection();
    $stmt = $pdo->prepare('SELECT id, email, notification_frequency FROM event_subscribers WHERE manage_token = ? AND manage_token_expires > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sub) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Token invalide ou expiré']); exit; }

    $prefsStmt = $pdo->prepare('SELECT category_id FROM subscriber_preferences WHERE subscriber_id = ?');
    $prefsStmt->execute([$sub['id']]);
    $selected = array_map('intval', array_column($prefsStmt->fetchAll(PDO::FETCH_ASSOC), 'category_id'));

    // Charger la liste des catégories depuis la même source que l'API événements
    // Table attendue: event_categories (id, name)
    try {
        $catStmt = $pdo->query('SELECT id, name FROM event_categories ORDER BY name');
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        logError('subscribers/manage-data.php', 'Erreur chargement categories', ['error'=>$e->getMessage()]);
        $categories = [];
    }

    echo json_encode([
        'success'=>true,
        'email'=>$sub['email'],
        'frequency'=>$sub['notification_frequency'],
        'selected'=>$selected,
        'categories'=>array_map(fn($c)=>['id'=>(int)$c['id'],'name'=>$c['name']], $categories)
    ]);
} catch (Exception $e) {
    logError('subscribers/manage-data.php', 'Erreur', ['error'=>$e->getMessage()]);
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Erreur serveur']);
}
