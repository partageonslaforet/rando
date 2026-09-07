<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../logs/error.log.php';

// Charger la connexion si elle n'est pas encore disponible
if (!function_exists('getConnection')) {
    require_once __DIR__ . '/../config/database.php';
}

// Fonction pour vérifier si l'utilisateur est connecté via un cookie "Se souvenir de moi"
function checkRememberMeCookie() {
    // Récupérer la connexion disponible ($db, $pdo ou nouvelle)
    $db = $GLOBALS['db'] ?? $GLOBALS['pdo'] ?? null;
    if (!$db && function_exists('getConnection')) {
        $db = getConnection();
    }
    if (!$db) {
        logError('auth_check.php', 'Connexion DB indisponible dans checkRememberMeCookie', [
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'db_global' => isset($GLOBALS['db']),
            'pdo_global' => isset($GLOBALS['pdo']),
            'getConnection_exists' => function_exists('getConnection')
        ]);
        return;
    }
    
    // Si l'utilisateur est déjà connecté, pas besoin de vérifier le cookie
    if (isset($_SESSION['user_id'])) {
        return;
    }
    
    // Vérifier si le cookie remember_token existe
    if (isset($_COOKIE['remember_token'])) {
        try {
            $token = $_COOKIE['remember_token'];
            
            // Rechercher l'utilisateur avec ce token et vérifier qu'il n'est pas expiré
            $stmt = $db->prepare("
                SELECT * 
                FROM users 
                WHERE remember_token = ? AND remember_token_expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Mettre à jour la session avec les informations de l'utilisateur
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];
                
                // Générer un nouveau token pour la prochaine fois
                $newToken = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                // Mettre à jour le token dans la base de données
                $stmt = $db->prepare("
                    UPDATE users 
                    SET remember_token = ?, remember_token_expires_at = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$newToken, $expiresAt, $user['id']]);
                
                // Mettre à jour le cookie
                $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                setcookie(
                    'remember_token',
                    $newToken,
                    [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'secure' => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]
                );
            }
        } catch (PDOException $e) {
            logError('auth_check.php', 'Erreur PDO lors de la vérification du cookie remember_me', [
                'message' => $e->getMessage()
            ]);
        }
    }
}

// Vérifier le cookie à chaque chargement de page
checkRememberMeCookie();
