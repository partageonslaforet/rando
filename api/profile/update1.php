<?php
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

if (!isset($data['name']) || !isset($data['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

require_once '/home/cool5792/rando.partageonslaforet.be/config/database.php';

try {
    $pdo->beginTransaction();

    // Vérifier si l'email est déjà utilisé (sauf par l'utilisateur actuel)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$data['email'], $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        throw new Exception('Cet email est déjà utilisé');
    }

    // Mise à jour du profil
    $sql = 'UPDATE users SET name = ?, email = ?';
    $params = [$data['name'], $data['email']];

    // Ajouter le mot de passe s'il est fourni
    if (!empty($data['newPassword'])) {
        $sql .= ', password = ?';
        $params[] = password_hash($data['newPassword'], PASSWORD_DEFAULT);
    }

    $sql .= ' WHERE id = ?';
    $params[] = $_SESSION['user_id'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Profil mis à jour avec succès'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
