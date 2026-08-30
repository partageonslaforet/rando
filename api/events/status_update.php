<?php
header('Content-Type: application/json');
require_once '/home/cool5792/rando.partageonslaforet.be/includes/config.php';

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

try {
    // Connexion à la base de données
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Vérifier si l'utilisateur est admin
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
        exit();
    }

    // Récupérer l'ID et le statut
    $eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $data = json_decode(file_get_contents('php://input'), true);
    $status = $data['status'] ?? '';

    if (!$eventId || !in_array($status, ['pending', 'approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
        exit();
    }

    // Mettre à jour le statut
    $stmt = $pdo->prepare('UPDATE events SET status = ?, updated_at = NOW() WHERE id = ?');
    $success = $stmt->execute([$status, $eventId]);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
