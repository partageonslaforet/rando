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

        // Envoyer l'email de confirmation
        $resetLink = "https://rando.partageonslaforet.be/reset-password.php?token=" . $resetToken;
        $to = $_SESSION['user_email'];
        $subject = "Confirmation de changement de mot de passe";
        
        // Email HTML
        $messageHtml = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Confirmation de changement de mot de passe</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #3A8A3D!important;
                    color: white !important;
                    padding: 20px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }
                .content {
                    background-color: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 0 0 5px 5px;
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #3A8A3D !important;
                    color: white !important;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 12px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Partageons la Forêt</h2>
            </div>
            <div class="content">
                <h3>Bonjour,</h3>
                <p>Une demande de changement de mot de passe a été effectuée pour votre compte.</p>
                <p>Pour confirmer ce changement, veuillez cliquer sur le bouton ci-dessous :</p>
                <p style="text-align: center;">
                    <a href="' . $resetLink . '" class="button">Confirmer le changement</a>
                </p>
                <p><strong>Attention :</strong> Ce lien expirera dans 1 heure.</p>
                <p>Si vous n\'êtes pas à l\'origine de cette demande, vous pouvez ignorer cet email en toute sécurité.</p>
            </div>
            <div class="footer">
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                <p>&copy; ' . date('Y') . ' Partageons la Forêt. Tous droits réservés.</p>
            </div>
        </body>
        </html>';

        // Version texte pour les clients qui ne supportent pas l'HTML
        $messageText = "Bonjour,\n\n" .
            "Une demande de changement de mot de passe a été effectuée pour votre compte.\n" .
            "Pour confirmer ce changement, veuillez cliquer sur le lien suivant :\n\n" .
            $resetLink . "\n\n" .
            "Attention : Ce lien expirera dans 1 heure.\n\n" .
            "Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.\n\n" .
            "Cordialement,\n" .
            "L'équipe Partageons la Forêt";

        // Headers pour l'email HTML
        $headers = "From: Partageons la Forêt <no-reply@partageonslaforet.be>\r\n";
        $headers .= "Reply-To: no-reply@partageonslaforet.be\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"boundary\"\r\n";

        // Corps du message avec les deux versions
        $message = "--boundary\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $messageText . "\r\n\r\n" .
            "--boundary\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $messageHtml . "\r\n\r\n" .
            "--boundary--";

        if (!mail($to, $subject, $message, $headers)) {
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
