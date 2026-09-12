<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getConnection();

    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $hostname = $pdo->query('SELECT @@hostname')->fetchColumn();
    $currentUser = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $hasLinkTable = in_array('event_category_links', $tables, true);

    $selectTest = null;
    if ($hasLinkTable) {
        try {
            $stmt = $pdo->query('SELECT * FROM `event_category_links` LIMIT 1');
            $selectTest = $stmt->fetch(PDO::FETCH_ASSOC) ?: 'empty';
        } catch (PDOException $e) {
            $selectTest = $e->getMessage();
        }
    }

    echo json_encode([
        'status' => 'ok',
        'db_name' => $dbName,
        'hostname' => $hostname,
        'current_user' => $currentUser,
        'tables' => $tables,
        'has_event_category_links' => $hasLinkTable,
        'select_test' => $selectTest,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
