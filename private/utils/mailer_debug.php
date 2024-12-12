<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailService {
    private $mailer;
    private $logger;

    public function __construct() {
        $this->setupLogger();  // On initialise d'abord le logger
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }

    private function setupLogger() {
        $logFile = __DIR__ . '/../logs/email-service.log';
        $logDir = dirname($logFile);
        
        // Log directory creation with detailed error handling
        if (!file_exists($logDir)) {
            try {
                if (!mkdir($logDir, 0755, true)) {
                    error_log("Failed to create log directory: $logDir");
                    throw new Exception("Failed to create log directory");
                }
            } catch (Exception $e) {
                error_log("Exception while creating log directory: " . $e->getMessage());
                throw $e;
            }
        }
        
        // Check if directory is writable
        if (!is_writable($logDir)) {
            error_log("Log directory is not writable: $logDir");
            throw new Exception("Log directory is not writable");
        }
        
        $this->logger = function($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = "[$timestamp] $message" . PHP_EOL;
            
            // Also log to error_log for backup
            error_log($formattedMessage);
            
            try {
                if (file_put_contents($logFile, $formattedMessage, FILE_APPEND) === false) {
                    error_log("Failed to write to log file: $logFile");
                    throw new Exception("Failed to write to log file");
                }
            } catch (Exception $e) {
                error_log("Exception while writing to log file: " . $e->getMessage());
                throw $e;
            }
        };
    }

    private function log($message) {
        if ($this->logger) {
            try {
                ($this->logger)($message);
            } catch (Exception $e) {
                error_log("Logging failed: " . $e->getMessage());
            }
        }
    }

    private function setupMailer() {
        try {
            $this->log("[INFO] Initializing PHPMailer with config:");
            $this->log("[CONFIG] SMTP_HOST: " . $_ENV['SMTP_HOST']);
            $this->log("[CONFIG] SMTP_PORT: " . $_ENV['SMTP_PORT']);
            $this->log("[CONFIG] SMTP_USER: " . $_ENV['SMTP_USER']);
            $this->log("[CONFIG] SMTP_SECURE: " . $_ENV['SMTP_SECURE']);
    
            // Configuration SMTP détaillée
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['SMTP_HOST'];
            $this->mailer->Port = intval($_ENV['SMTP_PORT']);
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['SMTP_USER'];
            $this->mailer->Password = $_ENV['SMTP_PASSWORD'];
            $this->mailer->SMTPSecure = $_ENV['SMTP_SECURE'];
            
            // Configuration du debug SMTP
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            $this->mailer->Debugoutput = function($str, $level) {
                $this->log("[SMTP DEBUG] [$level] $str");
            };

            // Configuration de base
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            $this->mailer->setFrom($_ENV['SMTP_USER'], 'Partageons la Forêt');
            
            // Vérification de la connexion SMTP
            try {
                $this->log("[INFO] Testing SMTP connection...");
                if ($this->mailer->smtpConnect()) {
                    $this->log("[INFO] SMTP connection successful");
                    $this->mailer->smtpClose();
                } else {
                    $this->log("[ERROR] SMTP connection failed");
                    throw new Exception("SMTP connection test failed");
                }
            } catch (Exception $e) {
                $this->log("[ERROR] SMTP connection test failed: " . $e->getMessage());
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->log("[ERROR] PHPMailer initialization failed: " . $e->getMessage());
            $this->log("[ERROR] Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function sendConfirmationEmail(string $email, string $name, string $token): bool {
        try {
            $this->log("[INFO] Preparing confirmation email for: $email");
            
            // Réinitialiser les destinataires
            $this->mailer->clearAddresses();
            
            // Configurer l'email
            $this->mailer->addAddress($email, $name);
            $this->mailer->Subject = "Confirmez votre inscription - Partageons la Forêt";

            // Construire le lien de vérification
            $verificationLink = $_ENV['APP_URL'] . "/verify-email?token=" . $token;
            $this->log("[INFO] Verification link generated: $verificationLink");

            // Corps de l'email en HTML
            $htmlBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Bienvenue sur Partageons la Forêt !</h2>
                <p>Bonjour $name,</p>
                <p>Merci de vous être inscrit(e) sur notre plateforme. Pour activer votre compte, veuillez cliquer sur le lien ci-dessous :</p>
                <p style='margin: 20px 0;'>
                    <a href='$verificationLink'
                       style='background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;'>
                        Confirmer mon email
                    </a>
                </p>
                <p>Ou copiez ce lien dans votre navigateur :</p>
                <p style='word-break: break-all;'>$verificationLink</p>
                <p><strong>Ce lien est valable pendant 24 heures.</strong></p>
                <p>Si vous n'avez pas créé de compte, vous pouvez ignorer cet email.</p>
                <hr style='margin: 20px 0;'>
                <p style='color: #666; font-size: 12px;'>
                    Ceci est un email automatique, merci de ne pas y répondre.
                </p>
            </div>";

            // Corps de l'email en texte simple
            $textBody = "
            Bienvenue sur Partageons la Forêt !
            Bonjour $name,

            Merci de vous être inscrit(e) sur notre plateforme. Pour activer votre compte, veuillez cliquer sur le lien ci-dessous :

            $verificationLink

            Ce lien est valable pendant 24 heures.

            Si vous n'avez pas créé de compte, vous pouvez ignorer cet email.

            Ceci est un email automatique, merci de ne pas y répondre.";

            // Définir le corps de l'email
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $textBody;

            $this->log("[INFO] Attempting to send confirmation email...");
            
            // Test de connexion SMTP avant l'envoi
            if (!$this->mailer->smtpConnect()) {
                $this->log("[ERROR] SMTP connection failed before sending");
                throw new Exception("SMTP connection failed before sending");
            }
            
            $sent = $this->mailer->send();
            $this->log("[INFO] Email sent successfully: " . ($sent ? 'true' : 'false'));
            
            if ($sent) {
                $this->log("[SUCCESS] Confirmation email sent to: $email");
            } else {
                $this->log("[WARNING] Mailer reported success but no confirmation");
            }

            return $sent;
        } catch (Exception $e) {
            $this->log("[ERROR] Failed to send confirmation email: " . $e->getMessage());
            $this->log("[ERROR] Stack trace: " . $e->getTraceAsString());
            if (isset($this->mailer->ErrorInfo)) {
                $this->log("[ERROR] PHPMailer error info: " . $this->mailer->ErrorInfo);
            }
            throw $e;
        }
    }
}