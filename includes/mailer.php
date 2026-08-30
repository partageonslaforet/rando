<?php
/**
 * Mailer centralisé basé sur PHPMailer.
 * Charge config/mail.php pour les constantes e-mail (variables d'environnement).
 */

require_once __DIR__ . '/../config/mail.php';

$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

$autoloaderLoaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaderLoaded = true;
        break;
    }
}

if (!$autoloaderLoaded) {
    error_log("ERREUR CRITIQUE: Impossible de trouver l'autoloader de Composer");
    die("Erreur de configuration du serveur. Veuillez contacter l'administrateur.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mailer;

    public function __construct() {
        try {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                throw new \Exception("La classe PHPMailer n'est pas disponible.");
            }

            $this->mailer = new PHPMailer(true);
            $this->mailer->isSMTP();
            $this->mailer->Host = MAIL_HOST;
            $this->mailer->Port = MAIL_PORT;
            $this->mailer->SMTPAuth = !empty(MAIL_USERNAME);
            if ($this->mailer->SMTPAuth) {
                $this->mailer->Username = MAIL_USERNAME;
                $this->mailer->Password = MAIL_PASSWORD;
            }

            if (strtolower(MAIL_ENCRYPTION) === 'tls') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (strtolower(MAIL_ENCRYPTION) === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mailer->SMTPSecure = '';
                $this->mailer->SMTPAutoTLS = false;
            }

            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $this->mailer->isHTML(true);

            if (defined('DEBUG') && DEBUG) {
                $this->mailer->SMTPDebug = 2;
                $this->mailer->Debugoutput = function ($str) {
                    // Ne jamais logger de mots de passe ou secrets
                    if (stripos($str, 'pass') !== false) {
                        error_log('PHPMailer: [REDACTED]');
                    } else {
                        error_log('PHPMailer: ' . $str);
                    }
                };
            }
        } catch (\Exception $e) {
            require_once __DIR__ . '/../logs/error.log.php';
            logError('includes/mailer.php', 'Initialisation du mailer impossible', ['exception' => $e->getMessage()]);
            throw new \Exception('Initialisation du mailer impossible');
        }
    }

    public function sendHtml(string $to, string $subject, string $body, string $altBody = ''): bool {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);
            return $this->mailer->send();
        } catch (\Exception $e) {
            require_once __DIR__ . '/../logs/error.log.php';
            logError('includes/mailer.php', 'Envoi e-mail échoué', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function sendVerificationEmail(string $to, string $name, string $token): bool {
        $link = rtrim(APP_URL, '/') . '/templates/auth/verify.php?token=' . urlencode($token) . '&email=' . urlencode($to);
        $subject = 'Vérification de votre compte - ' . APP_NAME;

        ob_start();
        $data = ['name' => $name, 'link' => $link, 'appName' => APP_NAME];
        extract($data);
        include __DIR__ . '/../templates/emails/verification.php';
        $body = ob_get_clean();

        return $this->sendHtml($to, $subject, $body);
    }

    public function sendPasswordResetEmail(string $to, string $name, string $token): bool {
        $link = rtrim(APP_URL, '/') . '/templates/auth/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($to);
        $subject = 'Réinitialisation de votre mot de passe - ' . APP_NAME;

        ob_start();
        $data = ['name' => $name, 'link' => $link, 'appName' => APP_NAME];
        extract($data);
        include __DIR__ . '/../templates/emails/reset.php';
        $body = ob_get_clean();

        return $this->sendHtml($to, $subject, $body);
    }
}
