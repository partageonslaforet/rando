<?php
require_once __DIR__ . '/functions.php';

// Fonction pour vérifier si l'utilisateur est connecté via un cookie "Se souvenir de moi"
function checkRememberMeCookie() {
    global $db;
    
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
                SELECT u.* 
                FROM users u
                JOIN remember_tokens rt ON u.id = rt.user_id
                WHERE rt.token = ? AND rt.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Mettre à jour la session avec les informations de l'utilisateur
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                
                // Générer un nouveau token pour la prochaine fois
                $newToken = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                // Mettre à jour le token dans la base de données
                $stmt = $db->prepare("
                    UPDATE remember_tokens 
                    SET token = ?, expires_at = ? 
                    WHERE user_id = ? AND token = ?
                ");
                $stmt->execute([$newToken, $expiresAt, $user['id'], $token]);
                
                // Mettre à jour le cookie
                setcookie(
                    'remember_token',
                    $newToken,
                    [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]
                );
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du cookie remember_me: " . $e->getMessage());
        }
    }
}

// Vérifier le cookie à chaque chargement de page
checkRememberMeCookie();
