<?php
/**
 * Endpoint: POST /api/subscribers/preferences.php
 * Rôle: Met à jour les préférences d'un abonné
 * Paramètres: email, categories[], notification_frequency
 */

header('Content-Type: application/json; charset=utf-8');

$rootPath = realpath(__DIR__ . '/../..');
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/src/Services/Subscribers.php';
require_once $rootPath . '/logs/error.log.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    $token = trim($_POST['token'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $categoryIds = isset($_POST['categories']) && is_array($_POST['categories']) 
        ? array_map('intval', $_POST['categories']) 
        : [];
    $frequency = $_POST['notification_frequency'] ?? 'immediate';

    $pdo = getConnection();
    $subscribers = new Subscribers();

    // Résoudre l'abonné soit via token valide, soit via email
    $subscriber = null;
    if (!empty($token)) {
        $stmt = $pdo->prepare('SELECT id, email, verified_at FROM event_subscribers WHERE manage_token = ? AND manage_token_expires > NOW() LIMIT 1');
        $stmt->execute([$token]);
        $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$subscriber) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Lien expiré ou invalide']);
            exit;
        }
        // Aligner l'email pour logs/cohérence
        $email = $subscriber['email'];
    } else {
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email requis']);
            exit;
        }
    }

    if (empty($categoryIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Sélectionnez au moins une catégorie']);
        exit;
    }

    if (!$subscriber) {
        $subscriber = $subscribers->getByEmail($email);
    }

    if (!$subscriber) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Abonné non trouvé']);
        exit;
    }

    if (!$subscriber['verified_at']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Email non vérifié']);
        exit;
    }

    // Mettre à jour les préférences
    $subscribers->setPreferences($subscriber['id'], $categoryIds);

    // Mettre à jour la fréquence si valide
    if (in_array($frequency, ['immediate', 'weekly'])) {
        $stmt = $pdo->prepare('UPDATE event_subscribers SET notification_frequency = ? WHERE id = ?');
        $stmt->execute([$frequency, $subscriber['id']]);
    }

    logError('subscribers/preferences.php', 'Préférences mises à jour', [
        'subscriber_id' => $subscriber['id'],
        'categories' => count($categoryIds)
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Préférences mises à jour avec succès'
    ]);

} catch (Exception $e) {
    logError('subscribers/preferences.php', 'Erreur générale', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
