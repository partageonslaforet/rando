<?php
/**
 * Déconnexion de l'utilisateur.
 * Détruit la session, supprime le cookie de session et invalide le remember_token côté serveur.
 */

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    session_start();
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../logs/error.log.php';

    // Détruire la session
    $_SESSION = array();
    session_destroy();

    // Supprimer le cookie de session
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Supprimer le token côté base et le cookie remember_token
    if (isset($_COOKIE['remember_token'])) {
        try {
            $db = getConnection();
            $stmt = $db->prepare('UPDATE users SET remember_token = NULL, remember_token_expires_at = NULL WHERE remember_token = ?');
            $stmt->execute([$_COOKIE['remember_token']]);
        } catch (Exception $e) {
            logError('api/auth/logout.php', 'Erreur suppression remember_token', ['exception' => $e->getMessage()]);
        }

        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Déconnexion réussie'
    ]);
}
