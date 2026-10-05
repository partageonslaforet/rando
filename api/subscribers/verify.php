<?php
/**
 * Endpoint: GET /api/subscribers/verify.php?token=...
 * Rôle: Vérifie le token et active l'abonné
 */

$rootPath = realpath(__DIR__ . '/../..');
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/src/Services/Subscribers.php';
require_once $rootPath . '/logs/error.log.php';

try {
    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        $param = 'missing';
    } else {
        $subscribers = new Subscribers();
        $result = $subscribers->verify($token);
        $param = !empty($result['success']) ? 'success' : 'invalid';
    }
} catch (Exception $e) {
    logError('subscribers/verify.php', 'Erreur générale', ['error' => $e->getMessage()]);
    $param = 'error';
}

// Redirection vers l'accueil : le résultat est affiché dans #subVerifyModal
// (auto-ouvert par subscribers.js sur le paramètre sub_verify).
header('Location: /?sub_verify=' . $param);
exit;
?>
