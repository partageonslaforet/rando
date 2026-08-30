<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Méthode non autorisée']));
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email']) || !isset($input['password'])) {
        throw new Exception('Données invalides');
    }

    $db = getConnection();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$input['email']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($input['password'], $user['password'])) {
        throw new Exception('Email ou mot de passe incorrect');
    }

    if (!$user['is_verified']) {
        throw new Exception('Veuillez vérifier votre compte avant de vous connecter');
    }

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
        
        setcookie('remember_token', $token, [
            'expires' => strtotime('+7 days'),
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
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
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
