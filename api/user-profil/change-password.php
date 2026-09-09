<?php
/**
 * Modification du mot de passe de l'utilisateur connecté.
 * Vérifie l'ancien mot de passe, hash le nouveau et met à jour la base.
 */

session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer les données JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    // Vérifier le mot de passe actuel
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($data['currentPassword'], $user['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mot de passe actuel incorrect']);
        exit;
    }

    // Mettre à jour le mot de passe
    $hashedPassword = password_hash($data['newPassword'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hashedPassword, $_SESSION['user_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Mot de passe mis à jour avec succès'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour du mot de passe'
    ]);
}
