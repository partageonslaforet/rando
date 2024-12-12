<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../config/paths.php';

// Fonction de logging si elle n'existe pas déjà
if (!function_exists('debug_log')) {
    function debug_log($message, $data = null) {
        $log = "[" . date('Y-m-d H:i:s') . "] " . $message;
        if ($data !== null) {
            $log .= " - Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $log .= "\n";

        // Utiliser le chemin défini dans paths.php
        $logPath = LOGS_PATH . '/error.log';
        
        // Écrire le log
        error_log($log, 3, $logPath);
    }
}

function sendVerificationEmail($to, $name, $token) {
    // Vérifier les extensions requises
    $required_extensions = ['openssl', 'mbstring'];
    $missing_extensions = array_filter($required_extensions, function($ext) {
        return !extension_loaded($ext);
    });
    
    if (!empty($missing_extensions)) {
        debug_log("Extensions PHP manquantes", ['missing' => $missing_extensions]);
        return false;
    }

    // Valider l'email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        debug_log("Adresse email invalide", ['email' => $to]);
        return false;
    }

    debug_log("Tentative d'envoi d'email", [
        'to' => $to,
        'name' => $name,
        'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? 'non défini',
        'SMTP_USER' => $_ENV['SMTP_USER'] ?? 'non défini',
        'SMTP_PORT' => $_ENV['SMTP_PORT'] ?? 'non défini'
    ]);

    try {
        $mail = new PHPMailer(true);

        // Construire le lien de vérification
        $verificationLink = 'https://rando.partageonslaforet.be/verify-email?token=' . $token;

        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = intval($_ENV['SMTP_PORT']);
        $mail->CharSet = 'UTF-8';

        debug_log("Configuration SMTP initialisée", [
            'host' => $mail->Host,
            'port' => $mail->Port,
            'secure' => $mail->SMTPSecure,
            'auth' => $mail->SMTPAuth,
            'charset' => $mail->CharSet
        ]);

        // Configuration du timeout
        $mail->Timeout = 30; // 30 secondes
        $mail->SMTPKeepAlive = true;

        // Debug SMTP détaillé
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            debug_log("SMTP Debug ($level): $str");
        };

        // Destinataires
        $mail->setFrom($_ENV['SMTP_USER'], 'PartagonsLaForet');
        $mail->addAddress($to);

        debug_log("Destinataires configurés", [
            'from' => $_ENV['SMTP_USER'],
            'to' => $to
        ]);

        // Contenu
        $mail->isHTML(true);
        $mail->Subject = 'Vérification de votre compte PartagonsLaForet';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h1 style='color: #990047;'>Bienvenue sur PartagonsLaForet !</h1>
                <p>Bonjour $name,</p>
                <p>Merci de vous être inscrit. Pour activer votre compte, veuillez cliquer sur le lien ci-dessous :</p>
                <p style='margin: 20px 0;'>
                    <a href='{$verificationLink}' 
                       style='background-color: #990047; 
                              color: white; 
                              padding: 10px 20px; 
                              text-decoration: none; 
                              border-radius: 5px;
                              display: inline-block;'>
                        Vérifier mon compte
                    </a>
                </p>
                <p>Si le bouton ne fonctionne pas, vous pouvez copier et coller ce lien dans votre navigateur :</p>
                <p style='word-break: break-all;'>{$verificationLink}</p>
                <p>Ce lien est valable pendant 24 heures.</p>
                <hr style='margin: 20px 0;'>
                <p style='color: #666; font-size: 12px;'>
                    Si vous n'avez pas créé de compte sur PartagonsLaForet, vous pouvez ignorer cet email.
                </p>
            </div>
        ";

        // Version texte de l'email
        $mail->AltBody = "
            Bienvenue sur PartagonsLaForet !
            
            Bonjour $name,
            
            Merci de vous être inscrit. Pour activer votre compte, veuillez copier et coller le lien ci-dessous dans votre navigateur :
            
            $verificationLink
            
            Ce lien est valable pendant 24 heures.
            
            Si vous n'avez pas créé de compte sur PartagonsLaForet, vous pouvez ignorer cet email.
        ";

        // Tentative d'envoi
        $result = $mail->send();
        debug_log("Email envoyé avec succès", [
            'to' => $to,
            'subject' => $mail->Subject
        ]);
        return true;

    } catch (Exception $e) {
        debug_log("Erreur lors de l'envoi de l'email", [
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString(),
            'mailer_error_info' => $mail->ErrorInfo
        ]);
        return false;
    }
}