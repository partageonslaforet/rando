<?php

// Vérifier si l'autoloader de Composer existe
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',             // Chemin relatif local
    __DIR__ . '/../../vendor/autoload.php',          // Un niveau au-dessus
    '/home/cool5792/rando.partageonslaforet.be/vendor/autoload.php'  // Chemin absolu en prod
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
    error_log(" ERREUR CRITIQUE: Impossible de trouver l'autoloader de Composer");
    die("Erreur de configuration du serveur. Veuillez contacter l'administrateur.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class Mailer {
    private $mailer;

    public function __construct() {
        error_log(" Initialisation du mailer...");
        
        try {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                throw new \Exception("La classe PHPMailer n'est pas disponible. Veuillez vérifier l'installation de Composer.");
            }

            $this->mailer = new PHPMailer(true);
            
            // Configuration du serveur SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = SMTP_PORT;
            
            // Configuration de base
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // Configuration du debug
            $this->mailer->SMTPDebug = 3;
            $this->mailer->Debugoutput = function($str, $level) {
                error_log(" PHPMailer [$level]: $str");
            };

            error_log(" Configuration SMTP : " . json_encode([
                'host' => SMTP_HOST,
                'port' => SMTP_PORT,
                'username' => SMTP_USERNAME,
                'from_email' => SMTP_FROM_EMAIL,
                'from_name' => SMTP_FROM_NAME
            ], JSON_PRETTY_PRINT));
            
            error_log(" Mailer initialisé avec succès");
        } catch (\Exception $e) {
            $error = " Erreur lors de l'initialisation du mailer: " . $e->getMessage();
            error_log($error);
            error_log("Stack trace: " . $e->getTraceAsString());
            throw new \Exception($error);
        }
    }

    public function sendEventCreationNotification($eventData, $contactEmail) {
        try {
            error_log(" Préparation de l'email pour: $contactEmail");
            error_log(" Données de l'événement: " . json_encode($eventData, JSON_PRETTY_PRINT));

            // Configuration des destinataires
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($contactEmail);
            $this->mailer->addCC(SMTP_FROM_EMAIL);

            // Configuration du message
            $this->mailer->isHTML(true);
            $this->mailer->Subject = "Confirmation de création d'événement - " . $eventData['title'];
            
            // Corps du message
            $message = $this->buildEventCreationEmail($eventData);
            $this->mailer->Body = $message;
            $this->mailer->AltBody = strip_tags($message);

            // Envoi de l'email
            error_log(" Tentative d'envoi de l'email...");
            $this->mailer->send();
            error_log(" Email envoyé avec succès à: $contactEmail");
            
            return true;
        } catch (\Exception $e) {
            $error = " Erreur lors de l'envoi de l'email: " . $e->getMessage();
            error_log($error);
            error_log("Stack trace: " . $e->getTraceAsString());
            throw new \Exception($error);
        }
    }

    public function sendEventConfirmationEmail($eventId, $userId) {
        try {
            global $pdo;
            
            // Récupérer les informations de l'événement
            $stmt = $pdo->prepare("
                SELECT e.*, u.email, u.name as user_name
                FROM events e
                JOIN users u ON e.user_id = u.id
                WHERE e.id = ?
            ");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                throw new Exception("Événement non trouvé");
            }
            
            // Réinitialiser les destinataires
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($event['email'], $event['user_name']);
            
            // Configuration du message
            $this->mailer->Subject = 'Votre événement a été créé avec succès';
            
            // Corps de l'email
            $body = "
                <h2>Votre événement a été créé avec succès !</h2>
                <p>Bonjour {$event['user_name']},</p>
                <p>Votre événement \"{$event['title']}\" a été créé avec succès.</p>
                <p>Détails de l'événement :</p>
                <ul>
                    <li>Date : {$event['date']}</li>
                    <li>Heure de début : {$event['start_time']}</li>
                    <li>Lieu : {$event['location']}</li>
                </ul>
                <p>Vous pouvez voir votre événement en cliquant sur ce lien :</p>
                <p><a href='https://rando.partageonslaforet.be/event/{$event['id']}'>Voir l'événement</a></p>
                <p>Merci d'utiliser notre plateforme !</p>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);
            
            // Envoyer l'email
            $this->mailer->send();
            error_log(" Email de confirmation envoyé avec succès pour l'événement $eventId");
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi de l'email de confirmation : " . $e->getMessage());
            return false;
        }
    }

    public function sendEventPublishedEmail($to, $data) {
        try {
            // Configuration de base
            $this->mailer->clearAddresses();
            $this->mailer->setFrom(SMTP_FROM_EMAIL, APP_NAME);
            $this->mailer->addAddress($to);
            
            // Sujet et corps du message
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Votre événement a été soumis pour validation';
            
            // Charger le template HTML
            ob_start();
            include $_SERVER['DOCUMENT_ROOT'] . '/templates/emails/event-published.php';
            $body = ob_get_clean();
            
            $this->mailer->Body = $body;
            
            // Version texte simple
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $body));
            
            // Envoyer l'email
            $success = $this->mailer->send();
            error_log(" ✅ Email de publication envoyé à " . $to);
            return $success;
            
        } catch (Exception $e) {
            error_log(" ❌ Erreur lors de l'envoi de l'email de publication: " . $e->getMessage());
            throw $e;
        }
    }

    private function buildEventCreationEmail($eventData) {
        return "
            <h2>Votre événement a été créé avec succès!</h2>
            <p>Nous avons bien reçu votre demande de création d'événement. Voici un récapitulatif :</p>
            
            <h3>Détails de l'événement :</h3>
            <ul>
                <li><strong>Titre :</strong> {$eventData['title']}</li>
                <li><strong>Date :</strong> {$eventData['date']}</li>
                <li><strong>Heure de début :</strong> {$eventData['startTime']}</li>
                " . (!empty($eventData['endTime']) ? "<li><strong>Heure de fin :</strong> {$eventData['endTime']}</li>" : "") . "
                <li><strong>Lieu :</strong> {$eventData['location']}</li>
            </ul>

            <p>Votre événement est actuellement en attente de validation par notre équipe. 
            Nous vous informerons dès qu'il sera approuvé et visible sur le site.</p>

            <p>Si vous avez des questions ou besoin de modifier votre événement, 
            n'hésitez pas à nous contacter à cette adresse : " . SMTP_FROM_EMAIL . "</p>

            <p>Merci de votre confiance !</p>
            <p>L'équipe de Partageons la Forêt</p>
        ";
    }
}
