<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../../private/includes/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailService {
    private $mailer;
    private $logger;
    private $logFile;
    private $logDir;
    private $debug_log;
    private $lastError;

    public function __construct() {
        global $debugLogs;
        if (!isset($debugLogs)) {
            $debugLogs = [];
        }
        
        $this->debug_log = function($message) use (&$debugLogs) {
            error_log("[EmailService] " . $message);
            $debugLogs[] = "[EmailService] " . $message;
        };
        
        error_log("🚀 [EMAIL] Initialisation du service email");
        try {
            // Vérifier si le dossier de logs existe
            $logPath = $_ENV['LOG_PATH'] ?? '/home/cool5792/api.rando.partageons.laforet.be/logs';
            ($this->debug_log)("[Config] Log path: " . $logPath);
            
            if (!file_exists($logPath)) {
                ($this->debug_log)("[Warning] Log directory does not exist: " . $logPath);
                if (!mkdir($logPath, 0775, true)) {
                    ($this->debug_log)("[Error] Failed to create log directory");
                }
            }
            
            $this->setupLogger();
            ($this->debug_log)("[EmailService] Logger setup completed");
            
            $this->setupMailer();
            error_log("✅ [EMAIL] Configuration du mailer terminée");
            ($this->debug_log)("[EmailService] Mailer setup completed");
        } catch (Exception $e) {
            error_log("🚨 [EMAIL] Erreur d'initialisation: " . $e->getMessage());
            ($this->debug_log)("[EmailService] Constructor error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getLastError() {
        return $this->lastError;
    }

    private function setLastError($error) {
        $this->lastError = $error;
        ($this->debug_log)("[ERROR] " . $error);
        return false;
    }

    private function setupLogger() {
        global $debugLogs;
        if (!isset($debugLogs)) {
            $debugLogs = [];
        }
        
        $logFile = $this->logFile;
        $this->logger = function($message) use ($logFile) {
            try {
                $timestamp = date('Y-m-d H:i:s');
                $formattedMessage = "[$timestamp] $message" . PHP_EOL;
                
                // Toujours logger dans error_log et debug pour le débogage
                ($this->debug_log)($message);
                
                // Vérifier si le fichier existe et est accessible
                if (!file_exists($logFile)) {
                    ($this->debug_log)("Log file doesn't exist: " . $logFile);
                    return;
                }
                
                if (!is_writable($logFile)) {
                    ($this->debug_log)("Log file is not writable: " . $logFile);
                    return;
                }
                
                $fp = fopen($logFile, 'a');
                if ($fp === false) {
                    ($this->debug_log)("Failed to open log file: " . error_get_last()['message']);
                    return;
                }
                
                if (flock($fp, LOCK_EX)) {
                    $bytesWritten = fwrite($fp, $formattedMessage);
                    if ($bytesWritten === false) {
                        ($this->debug_log)("Failed to write to log file: " . error_get_last()['message']);
                    } else {
                        ($this->debug_log)("Successfully wrote $bytesWritten bytes to log file");
                    }
                    fflush($fp);
                    flock($fp, LOCK_UN);
                } else {
                    ($this->debug_log)("Failed to acquire lock on log file");
                }
                
                fclose($fp);
            } catch (Exception $e) {
                ($this->debug_log)("Logging error: " . $e->getMessage());
                ($this->debug_log)("Stack trace: " . $e->getTraceAsString());
            }
        };
        
        // Test d'écriture
        ($this->logger)("EmailService initialized");
        ($this->debug_log)("Logger setup completed");
    }

    private function setupMailer() {
        error_log("⚙️ [EMAIL] Configuration du mailer SMTP");
        try {
            error_log("📨 [EMAIL] Configuration SMTP:");
            error_log("   - Host: " . $_ENV['SMTP_HOST']);
            error_log("   - Port: " . $_ENV['SMTP_PORT']);
            error_log("   - User: " . $_ENV['SMTP_USER']);
            error_log("   - Secure: " . $_ENV['SMTP_SECURE']);
            
            $this->mailer = new PHPMailer(true);
            ($this->debug_log)("[EmailService] PHPMailer instance created");
            
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['SMTP_HOST'];
            $this->mailer->Port = intval($_ENV['SMTP_PORT']);
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['SMTP_USER'];
            $this->mailer->Password = $_ENV['SMTP_PASSWORD'];
            $this->mailer->SMTPSecure = $_ENV['SMTP_SECURE'];
            
            // Activer le debugging SMTP détaillé
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            $this->mailer->Debugoutput = function($str, $level) {
                error_log("🔍 [SMTP] [$level] $str");
                ($this->debug_log)("[SMTP DEBUG] [$level] $str");
            };

            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            $this->mailer->setFrom($_ENV['SMTP_USER'], 'Partageons la Forêt');
            
            error_log("🔄 [EMAIL] Test de la connexion SMTP...");
            if ($this->mailer->smtpConnect()) {
                error_log("✅ [EMAIL] Test de connexion SMTP réussi");
                $this->mailer->smtpClose();
            } else {
                error_log("❌ [EMAIL] Échec du test de connexion SMTP");
                throw new Exception("Échec de la connexion SMTP: " . $this->mailer->ErrorInfo);
            }
            
        } catch (Exception $e) {
            error_log("🚨 [EMAIL] Erreur de configuration SMTP: " . $e->getMessage());
            if (isset($this->mailer->ErrorInfo)) {
                error_log("📝 [EMAIL] Détails SMTP: " . $this->mailer->ErrorInfo);
            }
            ($this->debug_log)("[SMTP] Setup error: " . $e->getMessage());
            if (isset($this->mailer->ErrorInfo)) {
                ($this->debug_log)("[SMTP] Additional error info: " . $this->mailer->ErrorInfo);
            }
            throw $e;
        }
    }

    private function log($message) {
        global $debugLogs;
        if (!isset($debugLogs)) {
            $debugLogs = [];
        }
        
        if ($this->logger) {
            try {
                ($this->logger)($message);
            } catch (Exception $e) {
                ($this->debug_log)("Logging failed: " . $e->getMessage());
            }
        }
    }

    public function sendConfirmationEmail(string $email, string $name, string $token): bool {
        error_log("📧 [EMAIL] Préparation de l'email de confirmation pour: $email");
        try {
            // Vérifier que le mailer est initialisé
            if (!$this->mailer) {
                error_log("❌ [EMAIL] Mailer non initialisé");
                return $this->setLastError("Mailer non initialisé");
            }

            ($this->debug_log)("[INFO] Preparing confirmation email for: $email");
            
            // Réinitialiser les destinataires et les erreurs
            $this->lastError = null;
            $this->mailer->clearAddresses();
            $this->mailer->clearAllRecipients();
            
            // Configuration de base de l'email
            $this->mailer->addAddress($email, $name);
            $this->mailer->Subject = 'Vérification de votre compte Partageons la Forêt';

            // Construire le lien de vérification
            $verificationLink = $_ENV['APP_URL'] . "/verify-email?token=" . $token;
            ($this->debug_log)("[INFO] Verification link generated: $verificationLink");
            error_log("🔗 [EMAIL] Lien de vérification généré: $verificationLink");

            // Corps de l'email en HTML
            error_log("📝 [EMAIL] Création du contenu de l'email");
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

            ($this->debug_log)("[INFO] Attempting to send confirmation email...");
            error_log("📤 [EMAIL] Tentative d'envoi...");
            
            // Test de connexion SMTP avant l'envoi
            error_log("🔄 [EMAIL] Test de la connexion SMTP avant envoi...");
            if (!$this->mailer->smtpConnect()) {
                error_log("❌ [EMAIL] Échec de la connexion SMTP avant envoi");
                return $this->setLastError("Échec de la connexion SMTP: " . $this->mailer->ErrorInfo);
            }
            
            // Tentative d'envoi
            $sent = $this->mailer->send();
            $this->mailer->smtpClose();
            
            if (!$sent) {
                error_log("❌ [EMAIL] Échec de l'envoi de l'email: " . $this->mailer->ErrorInfo);
                return $this->setLastError("Échec de l'envoi: " . $this->mailer->ErrorInfo);
            }

            error_log("✅ [EMAIL] Email envoyé avec succès à: $email");
            ($this->debug_log)("[SUCCESS] Email envoyé avec succès à: $email");
            return true;

        } catch (Exception $e) {
            error_log("🚨 [EMAIL] Erreur lors de l'envoi: " . $e->getMessage());
            if (isset($this->mailer->ErrorInfo)) {
                error_log("📝 [EMAIL] Détails SMTP: " . $this->mailer->ErrorInfo);
            }
            $error = "Exception lors de l'envoi: " . $e->getMessage();
            if (isset($this->mailer->ErrorInfo)) {
                $error .= " (SMTP: " . $this->mailer->ErrorInfo . ")";
            }
            return $this->setLastError($error);
        }
    }
}
