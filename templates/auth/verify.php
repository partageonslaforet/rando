<?php
require_once '../../config/database.php';
require_once '../../includes/flash_messages.php';

try {
    if (!isset($_GET['token'])) {
        addFlashMessage('error', 'Token de vérification manquant.');
        header('Location: /?login=required');
        exit();
    }

    if (!isset($_GET['email'])) {
        addFlashMessage('error', 'Email manquant.');
        header('Location: /?login=required');
        exit();
    }

    $db = getConnection();
    
    // Vérifier si le token est valide
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND verification_token = ? AND is_verified = 0');
    $stmt->execute([$_GET['email'], $_GET['token']]);
    $user = $stmt->fetch();

    if (!$user) {
        addFlashMessage('error', 'Token de vérification invalide ou compte déjà vérifié.');
        header('Location: /?login=required');
        exit();
    }

    // Marquer le compte comme vérifié
    $stmt = $db->prepare('UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?');
    $stmt->execute([$user['id']]);

    addFlashMessage('success', 'Votre compte a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');
    header('Location: /?login=required');
    exit();

} catch (Exception $e) {
    error_log('Erreur de vérification: ' . $e->getMessage());
    addFlashMessage('error', 'Une erreur est survenue lors de la vérification de votre compte.');
    header('Location: /?login=required');
    exit();
}
