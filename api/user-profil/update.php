<?php
/**
 * Mise à jour du profil utilisateur connecté (nom, email, etc.).
 * Peut également modifier le mot de passe si les champs correspondants sont fournis.
 */

session_start();
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer les données JSON
$data = json_decode(file_get_contents('php://input'), true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $pdo->beginTransaction();

    // Si c'est une demande de changement de mot de passe
    if (isset($data['currentPassword']) && isset($data['newPassword'])) {
        // Vérifier le mot de passe actuel
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!password_verify($data['currentPassword'], $user['password'])) {
            throw new Exception('Mot de passe actuel incorrect');
        }

        // Générer un token de réinitialisation
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Sauvegarder le token et le nouveau mot de passe hashé temporairement
        $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ?, temp_password = ? WHERE id = ?');
        $stmt->execute([
            $resetToken,
            $resetExpires,
            password_hash($data['newPassword'], PASSWORD_DEFAULT),
            $_SESSION['user_id']
        ]);

        // Envoyer l'email de confirmation via le Mailer (template harmonisé)
        $baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : 'https://rando.partageonslaforet.be';
        $resetLink = $baseUrl . "/reset-password.php?token=" . $resetToken;
        $to = $_SESSION['user_email'];

        require_once __DIR__ . '/../../includes/mailer.php';
        $mailer = new Mailer();
        if (!$mailer->sendPasswordChangeConfirmEmail($to, $resetLink)) {
            if (function_exists('logError')) {
                logError('api/user-profil/update.php', 'Échec envoi email confirmation changement mdp', ['to' => $to]);
            }
            throw new Exception("Erreur lors de l'envoi de l'email");
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Un email de confirmation a été envoyé. Veuillez vérifier votre boîte de réception.'
        ]);
        exit;
    }

    // Sinon, c'est une mise à jour du profil
    if (!isset($data['name']) || !isset($data['email'])) {
        throw new Exception('Données manquantes');
    }

    // Vérifier si l'email est déjà utilisé (sauf par l'utilisateur actuel)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$data['email'], $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        throw new Exception('Cet email est déjà utilisé');
    }

    // Mise à jour du profil
    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
    $stmt->execute([$data['name'], $data['email'], $_SESSION['user_id']]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Profil mis à jour avec succès'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
