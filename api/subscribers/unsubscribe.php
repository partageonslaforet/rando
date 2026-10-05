<?php
/**
 * Endpoint: POST /api/subscribers/unsubscribe.php
 * Rôle: Désabonne un abonné
 * Paramètres: email
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

    $pdo = getConnection();
    $subscriber = null;
    if (!empty($token)) {
        $stmt = $pdo->prepare('SELECT id, email FROM event_subscribers WHERE manage_token = ? AND manage_token_expires > NOW() LIMIT 1');
        $stmt->execute([$token]);
        $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$subscriber) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Lien expiré ou invalide']);
            exit;
        }
        $email = $subscriber['email'];
    } else {
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email requis']);
            exit;
        }
    }

    $subscribers = new Subscribers();
    if (!$subscriber) {
        $subscriber = $subscribers->getByEmail($email);
    }

    if (!$subscriber) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Abonné non trouvé']);
        exit;
    }

    $subscribers->unsubscribe($subscriber['id']);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Vous avez été désabonné avec succès'
    ]);

} catch (Exception $e) {
    logError('subscribers/unsubscribe.php', 'Erreur générale', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
