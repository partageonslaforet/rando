<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/config/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = getConnection();
    $eventId = 106;
    
    // Requête détaillée pour l'événement
    $query = "
        SELECT 
            e.*,
            u.name as user_name,
            u.email as user_email,
            c.name as category_name
        FROM events e
        LEFT JOIN users u ON e.user_id = u.id
        LEFT JOIN event_categories c ON e.category_id = c.id
        WHERE e.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($event);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
