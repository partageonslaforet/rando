<?php
/**
 * Définition d'un nouveau mot de passe via token de réinitialisation.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../logs/error.log.php';

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

if (!checkRateLimit('reset_password', 5, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => rateLimitMessage('reset_password')]);
    exit;
}

try {
    $email = $input['email'] ?? '';
    $token = $input['token'] ?? '';
    $password = $input['password'] ?? '';
    $passwordConfirm = $input['password_confirm'] ?? '';

    if (empty($email) || empty($token) || empty($password) || empty($passwordConfirm)) {
        throw new Exception('Tous les champs sont obligatoires');
    }

    if (strlen($password) < 8) {
        throw new Exception('Le mot de passe doit contenir au moins 8 caractères');
    }

    if ($password !== $passwordConfirm) {
        throw new Exception('Les mots de passe ne correspondent pas');
    }

    $db = getConnection();
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare('
        SELECT u.id, u.email
        FROM users u
        JOIN password_reset_tokens t ON u.id = t.user_id
        WHERE u.email = ?
          AND t.token_hash = ?
          AND t.expires_at > NOW()
          AND t.used_at IS NULL
        ORDER BY t.created_at DESC
        LIMIT 1
    ');
    $stmt->execute([$email, $tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Lien de réinitialisation invalide ou expiré');
    }

    $db->beginTransaction();

    // Nouveau mot de passe
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE users SET password = ?, remember_token = NULL, remember_token_expires_at = NULL, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$hashed, $row['id']]);

    // Marquer le jeton comme utilisé
    $stmt = $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?');
    $stmt->execute([$tokenHash]);

    // Invalider les autres jetons de reset de cet utilisateur
    $stmt = $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
    $stmt->execute([$row['id']]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Votre mot de passe a été réinitialisé avec succès.'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logError('api/auth/reset-password.php', 'Erreur reset password', ['exception' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
