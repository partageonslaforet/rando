<?php
require_once '../../config/database.php';
require_once '../../includes/flash_messages.php';
require_once '../../logs/error.log.php';

try {
    if (empty($_GET['token']) || empty($_GET['email'])) {
        addFlashMessage('error', 'Lien de vérification incomplet.');
        header('Location: /?login=required');
        exit();
    }

    $db = getConnection();
    $tokenHash = hash('sha256', $_GET['token']);

    $stmt = $db->prepare('
        SELECT u.id, u.email
        FROM users u
        JOIN email_verification_tokens t ON u.id = t.user_id
        WHERE u.email = ?
          AND t.token_hash = ?
          AND t.expires_at > NOW()
          AND t.used_at IS NULL
          AND u.email_verified = 0
        ORDER BY t.created_at DESC
        LIMIT 1
    ');
    $stmt->execute([$_GET['email'], $tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        addFlashMessage('error', 'Lien de vérification invalide, expiré ou déjà utilisé.');
        header('Location: /?login=required');
        exit();
    }

    $db->beginTransaction();

    // Activer le compte
    $stmt = $db->prepare('UPDATE users SET email_verified = 1, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$row['id']]);

    // Invalider le jeton utilisé
    $stmt = $db->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE token_hash = ?');
    $stmt->execute([$tokenHash]);

    // Invalider les anciens jetons non utilisés de cet utilisateur
    $stmt = $db->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
    $stmt->execute([$row['id']]);

    $db->commit();

    addFlashMessage('success', 'Votre compte a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');
    header('Location: /?login=required');
    exit();

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logError('templates/auth/verify.php', 'Erreur de vérification', ['exception' => $e->getMessage()]);
    addFlashMessage('error', 'Une erreur est survenue lors de la vérification de votre compte.');
    header('Location: /?login=required');
    exit();
}
