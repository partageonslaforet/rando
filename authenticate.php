<?php
// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inclure les fonctions
require_once '/home/cool5792/rando.partageonslaforet.be/includes/functions.php';

// Inclure init.php
require_once '/home/cool5792/rando.partageonslaforet.be/includes/init.php';

// En-têtes CORS et JSON
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Lire les données JSON
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || !isset($input['email']) || !isset($input['password'])) {
        throw new Exception('Données invalides : email et mot de passe requis.');
    }

    // Test de connexion à la base de données
    require_once '/home/cool5792/rando.partageonslaforet.be/config/database.php';
    $db = getConnection();

    // Rechercher l'utilisateur
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$input['email']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($input['password'], $user['password'])) {
        throw new Exception('Email ou mot de passe incorrect.');
    }

    if (!$user['is_verified']) {
        throw new Exception('Veuillez vérifier votre compte avant de vous connecter.');
    }

    // Créer la session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    // Gérer "Se souvenir de moi"
    if (isset($input['remember_me']) && $input['remember_me']) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $db->prepare('UPDATE users SET remember_token = ?, remember_token_expires_at = ? WHERE id = ?');
        $stmt->execute([$token, $expires, $user['id']]);
        
        // Vérifier si HTTPS est utilisé avant de définir le cookie
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            setcookie('remember_token', $token, [
                'expires' => strtotime('+7 days'),
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        } else {
            throw new Exception('La connexion sécurisée est requise pour activer le cookie "Se souvenir de moi".');
        }
    }

    // Mettre à jour la dernière connexion
    $stmt = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $stmt->execute([$user['id']]);

    // Retourner la réponse avec les informations de l'utilisateur
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
