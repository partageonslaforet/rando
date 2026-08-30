<?php
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

try {
    // Récupérer les catégories depuis la table categories
    $stmt = $pdo->prepare("SELECT id, name FROM event_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retourner les données au format JSON
    echo json_encode([
        'success' => true,
        'data' => $categories
    ]);

} catch (PDOException $e) {
    // En cas d'erreur, retourner un message d'erreur
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des catégories',
        'debug' => DEBUG ? $e->getMessage() : null
    ]);
}