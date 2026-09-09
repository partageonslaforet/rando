<?php
/**
 * Suppression du compte utilisateur connecté.
 * Non branché actuellement : endpoint à appeler depuis le client plus tard.
 */

session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo->beginTransaction();

    // Supprimer les participations aux événements
    $stmt = $pdo->prepare('DELETE FROM event_participants WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);

    // Supprimer les événements créés par l'utilisateur
    $stmt = $pdo->prepare('DELETE FROM events WHERE creator_id = ?');
    $stmt->execute([$_SESSION['user_id']]);

    // Supprimer l'utilisateur
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);

    $pdo->commit();

    // Détruire la session
    session_destroy();

    echo json_encode([
        'success' => true,
        'message' => 'Compte supprimé avec succès'
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la suppression du compte'
    ]);
}
