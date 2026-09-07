<?php
/**
 * Connexion sécurisée.
 * N'accepte que les comptes confirmés (email_verified = 1) et actifs.
 */

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../logs/error.log.php';

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

if (!checkRateLimit('login', 10, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => rateLimitMessage('login')]);
    exit;
}

try {
    if (!$input || empty($input['email']) || empty($input['password'])) {
        throw new Exception('Données invalides');
    }

    $db = getConnection();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$input['email']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($input['password'], $user['password'])) {
        throw new Exception('Email ou mot de passe incorrect');
    }

    if (!$user['email_verified']) {
        throw new Exception('Veuillez vérifier votre compte avant de vous connecter');
    }

    // Régénération de l'ID de session pour éviter la fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user'] = [
        'id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role']
    ];

    if (isset($input['remember_me']) && $input['remember_me']) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

        $stmt = $db->prepare('UPDATE users SET remember_token = ?, remember_token_expires_at = ? WHERE id = ?');
        $stmt->execute([$token, $expires, $user['id']]);

        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('remember_token', $token, [
            'expires' => strtotime('+7 days'),
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'redirect' => $user['role'] === 'admin' ? '/admin/dashboard.php' : '/',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);

} catch (Exception $e) {
    logError('api/auth/login.php', 'Échec de connexion', ['exception' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email ou mot de passe incorrect'
    ]);
}
