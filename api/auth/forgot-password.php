<?php
/**
 * Demande de réinitialisation de mot de passe.
 * Réponse neutre quelle que soit l'existence du compte.
 */

require_once '../../config/database.php';
require_once '../../includes/csrf.php';
require_once '../../includes/rate_limit.php';
require_once '../../logs/error.log.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verifyCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

if (!checkRateLimit('forgot_password', 5, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => rateLimitMessage('forgot_password')]);
    exit;
}

try {
    $email = $input['email'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Adresse e-mail invalide');
    }

    $db = getConnection();
    $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Invalider les anciens jetons non utilisés
        $stmt = $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$user['id']]);

        $stmt = $db->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $tokenHash, $expiresAt]);

        require_once '../../includes/mailer.php';
        $mailer = new Mailer();
        $mailer->sendPasswordResetEmail($email, $user['name'], $token);
    }

    // Réponse neutre
    echo json_encode([
        'success' => true,
        'message' => 'Si cette adresse email correspond à un compte, vous recevrez un email avec les instructions.'
    ]);

} catch (Exception $e) {
    logError('api/auth/forgot-password.php', 'Erreur forgot password', ['exception' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
}
