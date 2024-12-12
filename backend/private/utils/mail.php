<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $name, $verificationToken) {
    try {
        $mail = new PHPMailer(true);

        // Configuration du serveur
        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MAIL_USERNAME'];
        $mail->Password = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $mail->Port = $_ENV['MAIL_PORT'];
        $mail->CharSet = 'UTF-8';

        // Destinataires
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress($email, $name);

        // Contenu
        $mail->isHTML(true);
        $mail->Subject = 'Vérification de votre compte - Partageons la Forêt';
        
        $verificationUrl = 'https://rando.partageonslaforet.be/verify-email?token=' . $verificationToken;
        
        $mail->Body = "
            <h2>Bienvenue sur Partageons la Forêt !</h2>
            <p>Bonjour $name,</p>
            <p>Merci de vous être inscrit sur notre site. Pour activer votre compte, veuillez cliquer sur le lien ci-dessous :</p>
            <p><a href='$verificationUrl'>Vérifier mon adresse email</a></p>
            <p>Si le lien ne fonctionne pas, copiez et collez cette adresse dans votre navigateur :</p>
            <p>$verificationUrl</p>
            <p>Ce lien est valable pendant 24 heures.</p>
            <p>À bientôt sur Partageons la Forêt !</p>
        ";

        $mail->AltBody = "
            Bienvenue sur Partageons la Forêt !
            
            Bonjour $name,
            
            Merci de vous être inscrit sur notre site. Pour activer votre compte, veuillez copier et coller le lien ci-dessous dans votre navigateur :
            
            $verificationUrl
            
            Ce lien est valable pendant 24 heures.
            
            À bientôt sur Partageons la Forêt !
        ";

        $mail->send();
        debug_log("Email de vérification envoyé avec succès à $email");
        return true;
    } catch (Exception $e) {
        debug_log("Erreur lors de l'envoi de l'email", [
            'error' => $e->getMessage(),
            'email' => $email
        ]);
        return false;
    }
}
