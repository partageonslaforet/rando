<?php
/**
 * Renvoi d'e-mail de confirmation.
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

if (!checkRateLimit('resend_verification', 3, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => rateLimitMessage('resend_verification')]);
    exit;
}

try {
    if (empty($input['email'])) {
        throw new Exception('Adresse e-mail requise');
    }

    $db = getConnection();
    $stmt = $db->prepare('SELECT id, name, email_verified FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$input['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['email_verified']) {
        // Réponse neutre
        echo json_encode([
            'success' => true,
            'message' => 'Si cette adresse correspond à un compte non confirmé, un nouvel e-mail a été envoyé.'
        ]);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Invalider les anciens jetons non utilisés de cet utilisateur
    $stmt = $db->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
    $stmt->execute([$user['id']]);

    $stmt = $db->prepare('INSERT INTO email_verification_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], $tokenHash, $expiresAt]);

    require_once '../../includes/mailer.php';
    $mailer = new Mailer();
    $mailer->sendVerificationEmail($input['email'], $user['name'], $token);

    echo json_encode([
        'success' => true,
        'message' => 'Si cette adresse correspond à un compte non confirmé, un nouvel e-mail a été envoyé.'
    ]);

} catch (Exception $e) {
    logError('api/auth/resend-verification.php', 'Erreur resend', ['exception' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
}
