<?php
/**
 * Mailer centralisé basé sur PHPMailer.
 * Charge config/mail.php pour les constantes e-mail (variables d'environnement).
 */

// Charger d'abord une éventuelle surcharge locale, puis la config globale (définit les valeurs manquantes)
$__localCfgLoaded = null;
$__candidates = [__DIR__ . '/../config/mail.local.php'];
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $__candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/config/mail.local.php';
}
foreach ($__candidates as $__cfg) {
    if ($__cfg && file_exists($__cfg)) {
        require_once $__cfg;
        $__localCfgLoaded = $__cfg;
        break;
    }
}
require_once __DIR__ . '/../config/mail.php';

$__logFile = __DIR__ . '/../logs/error.log.php';
if (file_exists($__logFile)) {
    require_once $__logFile;
    if (function_exists('logError')) {
        logError('includes/mailer.php', 'Config mail chargée', [
            'local_loaded' => (bool) $__localCfgLoaded,
            'local_path' => $__localCfgLoaded,
            'host' => defined('MAIL_HOST') ? MAIL_HOST : null,
            'from' => defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : null
        ]);
    }
}

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
    throw new Exception("Erreur de configuration du serveur. Veuillez contacter l'administrateur.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private PHPMailer $mailer;

    public function __construct() {
        try {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                throw new \Exception("La classe PHPMailer n'est pas disponible.");
            }

            $this->mailer = new PHPMailer(true);

            // Limite le timeout de connexion fsockopen pour eviter un blocage de 60s
            ini_set('default_socket_timeout', '10');

            // Choisir dynamiquement le transport
            $useSMTP = false;
            if (!empty(MAIL_HOST) && strtolower(MAIL_HOST) !== 'mail()' && (!empty(MAIL_USERNAME) && !empty(MAIL_PASSWORD))) {
                $useSMTP = true;
            }

            $this->mailer->Timeout = 10;

            if ($useSMTP) {
                $this->mailer->isSMTP();
                $this->mailer->Host = MAIL_HOST;
                $this->mailer->Port = MAIL_PORT;
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = MAIL_USERNAME;
                $this->mailer->Password = MAIL_PASSWORD;

                if (strtolower(MAIL_ENCRYPTION) === 'tls') {
                    $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif (strtolower(MAIL_ENCRYPTION) === 'ssl') {
                    $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $this->mailer->SMTPSecure = '';
                    $this->mailer->SMTPAutoTLS = false;
                }
            } else {
                // En prod sans credentials SMTP, mail() natif est souvent bloque/plante sur l'hebergement mutualise
                $isProd = (($_ENV['APP_ENV'] ?? '') === 'production') || (strpos($_SERVER['HTTP_HOST'] ?? '', 'rando.partageonslaforet.be') !== false);
                $isMailFunction = function_exists('mail') && !in_array('mail', array_map('trim', explode(',', ini_get('disable_functions'))), true);
                if ($isProd && !$isMailFunction) {
                    throw new \Exception('Aucun transport e-mail configure (SMTP credentials manquants et mail() desactive).');
                } elseif ($isProd) {
                    throw new \Exception('Aucun compte SMTP configure. mail() natif est instable en production et a ete desactive.');
                } else {
                    // Dev : utiliser mail() natif (MailHog, sendmail local)
                    $this->mailer->isMail();
                }
            }

            if (function_exists('logError')) {
                logError('includes/mailer.php', 'Init transport', [
                    'useSMTP' => $useSMTP,
                    'transport' => $useSMTP ? 'smtp' : 'mail',
                    'host' => defined('MAIL_HOST') ? MAIL_HOST : null,
                    'port' => defined('MAIL_PORT') ? MAIL_PORT : null,
                    'secure' => defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : null,
                    'hasUser' => defined('MAIL_USERNAME') ? (MAIL_USERNAME !== '') : null,
                    'hasPass' => defined('MAIL_PASSWORD') ? (MAIL_PASSWORD !== '') : null,
                    'from' => defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : null
                ]);
            }

            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            // Définir l'enveloppe Return-Path pour améliorer la délivrabilité
            $this->mailer->Sender = MAIL_FROM_EMAIL;
            $this->mailer->isHTML(true);

            if (defined('DEBUG') && DEBUG) {
                // Router le debug SMTP vers notre logger applicatif
                require_once __DIR__ . '/../logs/error.log.php';
                $this->mailer->SMTPDebug = 2;
                $this->mailer->Debugoutput = function ($str) {
                    if (stripos($str, 'pass') !== false) {
                        $msg = '[REDACTED]';
                    } else {
                        $msg = $str;
                    }
                    if (function_exists('logError')) {
                        logError('includes/mailer.php', 'SMTP debug', ['msg' => $msg]);
                    } else {
                        error_log('PHPMailer: ' . $msg);
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

            // Log de tentative (sanitisé)
            if (defined('DEBUG') && DEBUG) {
                require_once __DIR__ . '/../logs/error.log.php';
                $transport = $this->mailer->Mailer; // 'smtp' ou 'mail'
                $ctx = [
                    'to' => $to,
                    'subject' => $subject,
                    'transport' => $transport,
                ];
                if ($transport === 'smtp') {
                    $ctx['host'] = $this->mailer->Host;
                    $ctx['port'] = $this->mailer->Port;
                    $ctx['secure'] = $this->mailer->SMTPSecure ?: 'none';
                    $ctx['auth'] = $this->mailer->SMTPAuth ? 'yes' : 'no';
                }
                if (function_exists('logError')) {
                    logError('includes/mailer.php', 'Tentative envoi e-mail', $ctx);
                }
            }

            $result = $this->mailer->send();
            if (!$result) {
                if (function_exists('logError')) {
                    logError('includes/mailer.php', 'Envoi e-mail a retourne false', [
                        'errorInfo' => $this->mailer->ErrorInfo,
                        'to' => $to,
                        'subject' => $subject,
                        'transport' => $this->mailer->Mailer,
                        'host' => $this->mailer->Host ?? null,
                        'port' => $this->mailer->Port ?? null,
                    ]);
                }
            } elseif (defined('DEBUG') && DEBUG && function_exists('logError')) {
                logError('includes/mailer.php', 'Envoi e-mail reussi', [
                    'to' => $to,
                    'subject' => $subject,
                    'transport' => $this->mailer->Mailer,
                ]);
            }
            return $result;
        } catch (\Exception $e) {
            require_once __DIR__ . '/../logs/error.log.php';
            logError('includes/mailer.php', 'Envoi e-mail échoué', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function sendVerificationEmail(string $to, string $name, string $token): bool {
        $link = rtrim(APP_URL, '/') . '/templates/auth/verify.php?token=' . urlencode($token) . '&email=' . urlencode($to);
        $subject = 'Vérification de votre compte - ' . APP_NAME;

        $vars = [
            'brand' => defined('APP_NAME') ? APP_NAME : 'Partageons la Forêt',
            'name' => $name,
            'link' => $link,
        ];
        ob_start();
        extract($vars, EXTR_SKIP);
        include __DIR__ . '/../templates/emails/verification.php';
        $body = ob_get_clean();

        return $this->sendHtml($to, $subject, $body);
    }

    public function sendPasswordResetEmail(string $to, string $name, string $token): bool {
        $link = rtrim(APP_URL, '/') . '/?reset=1&token=' . urlencode($token) . '&email=' . urlencode($to);
        $subject = 'Réinitialisation de votre mot de passe - ' . APP_NAME;

        $vars = [
            'brand' => defined('APP_NAME') ? APP_NAME : 'Partageons la Forêt',
            'name' => $name,
            'link' => $link,
        ];
        ob_start();
        extract($vars, EXTR_SKIP);
        include __DIR__ . '/../templates/emails/reset.php';
        $body = ob_get_clean();

        return $this->sendHtml($to, $subject, $body);
    }

    public function sendEventPublishedEmail(string $to, array $eventData): bool {
        $eventTitle = $eventData['title'] ?? 'Votre événement';
        $eventId = (int)($eventData['id'] ?? $eventData['eventId'] ?? 0);
        $eventDateStr = !empty($eventData['date']) ? date('d/m/Y', strtotime($eventData['date'])) : 'date non précisée';

        $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $link = $eventId ? ($base . '/event/' . $eventId) : $base;
        $subject = 'Votre événement est en attente de validation - ' . APP_NAME;

        $vars = [
            'brand' => defined('APP_NAME') ? APP_NAME : 'Partageons la Forêt',
            'title' => $eventTitle,
            'eventDate' => $eventDateStr,
            'link' => $link,
        ];
        ob_start();
        extract($vars, EXTR_SKIP);
        include __DIR__ . '/../templates/emails/event_published_user.php';
        $body = ob_get_clean();

        return $this->sendHtml($to, $subject, $body);
    }

    public function sendAdminEventPendingEmail(string $to, array $event): bool {
        $eventId = (int)($event['id'] ?? $event['eventId'] ?? 0);
        $title = $event['title'] ?? 'Événement';
        $eventDateStr = !empty($event['date']) ? date('d/m/Y', strtotime($event['date'])) : 'date non précisée';
        $publishedAtStr = !empty($event['published_at']) ? date('d/m/Y H:i', strtotime($event['published_at'])) : date('d/m/Y H:i');
        $organisation = $event['organisation'] ?? '';
        if ($organisation === '' && !empty($event['organizer_id'])) {
            // Tenter de récupérer le nom de l'organisateur depuis la base
            try {
                require_once __DIR__ . '/../config/database.php';
                if (function_exists('getConnection')) {
                    $pdo = getConnection();
                    $st = $pdo->prepare('SELECT name FROM organizer_profiles WHERE id = ?');
                    $st->execute([(int)$event['organizer_id']]);
                    $row = $st->fetch();
                    if ($row && !empty($row['name'])) {
                        $organisation = $row['name'];
                    }
                }
            } catch (\Throwable $e) {
                // Ignorer en silence et utiliser la valeur par défaut
            }
        }
        if ($organisation === '') {
            $organisation = 'Non spécifiée';
        }

        $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $adminLink = $eventId ? ($base . '/pages/admin/view_event.php?id=' . $eventId) : ($base . '/pages/admin/events.php');

        $vars = [
            'brand' => defined('APP_NAME') ? APP_NAME : 'Partageons la Forêt',
            'title' => $title,
            'eventDate' => $eventDateStr,
            'publishedAt' => $publishedAtStr,
            'organisation' => $organisation,
            'adminLink' => $adminLink,
        ];
        ob_start();
        extract($vars, EXTR_SKIP);
        include __DIR__ . '/../templates/emails/admin_event_pending.php';
        $body = ob_get_clean();

        $subject = 'Nouvel Evenement à valider';
        return $this->sendHtml($to, $subject, $body);
    }

    public function sendEventApprovedEmail(string $to, array $event): bool {
        $title = $event['title'] ?? 'Votre événement';
        $eventId = (int)($event['id'] ?? 0);
        $eventDateStr = !empty($event['date']) ? date('d/m/Y', strtotime($event['date'])) : 'date non précisée';

        $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $publicLink = $eventId ? ($base . '/event/' . $eventId) : $base;

        $vars = [
            'brand' => defined('APP_NAME') ? APP_NAME : 'Partageons la Forêt',
            'title' => $title,
            'eventDate' => $eventDateStr,
            'link' => $publicLink,
        ];
        ob_start();
        extract($vars, EXTR_SKIP);
        include __DIR__ . '/../templates/emails/event_status_approved.php';
        $body = ob_get_clean();

        $subject = 'Votre événement a été approuvé - ' . APP_NAME;
        return $this->sendHtml($to, $subject, $body);
    }

    public function sendEventRejectedEmail(string $to, array $event): bool {
        $title = $event['title'] ?? 'Votre événement';
        $eventDateStr = !empty($event['date']) ? date('d/m/Y', strtotime($event['date'])) : 'date non précisée';
        $reason = trim((string)($event['rejection_reason'] ?? ''));

        $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $myEvents = $base . '/pages/user/my-events.php?filter=rejected';

        $vars = [
            'brand' => defined('APP_NAME') ? APP_NAME : 'Partageons la Forêt',
            'title' => $title,
            'eventDate' => $eventDateStr,
            'reason' => $reason,
            'myEventsLink' => $myEvents,
        ];
        ob_start();
        extract($vars, EXTR_SKIP);
        include __DIR__ . '/../templates/emails/event_status_rejected.php';
        $body = ob_get_clean();

        $subject = 'Votre événement a été refusé - ' . APP_NAME;
        return $this->sendHtml($to, $subject, $body);
    }

    /**
     * Notifie un abonné de la publication, mise à jour ou annulation d'un événement.
     * @param string $to    Email de l'abonné
     * @param array  $event Données de l'événement (id, title, date, start_time, location, description, cancellation_reason)
     * @param string   $type          'new' | 'update' | 'cancelled'
     * @param string[] $changedFields Libellés des éléments modifiés (affichés si 'update')
     */
    public function sendEventNotificationEmail(string $to, array $event, string $type = 'new', array $changedFields = []): bool {
        $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $eventId = (int)($event['id'] ?? 0);

        $vars = [
            'event' => $event,
            'eventUrl' => $eventId ? ($base . '/event/' . $eventId) : $base,
            'unsubscribeUrl' => $base !== '' ? $base : '/',
            'type' => $type,
            'changedFields' => $changedFields,
        ];

        ob_start();
        extract($vars, EXTR_SKIP);
        include __DIR__ . '/../templates/emails/event-notification.php';
        $body = ob_get_clean();

        $title = $event['title'] ?? 'Événement';
        $subjects = [
            'new' => 'Nouvel événement : ',
            'update' => 'Événement mis à jour : ',
            'cancelled' => 'Événement annulé : ',
        ];
        $subject = ($subjects[$type] ?? $subjects['new']) . $title . ' - ' . APP_NAME;
        return $this->sendHtml($to, $subject, $body);
    }

    /**
     * Envoie l'email de confirmation d'un changement de mot de passe
     * demandé depuis la page profil.
     * @param string $to   Email du compte
     * @param string $link Lien de confirmation (tokenisé, expire 1h)
     */
    public function sendPasswordChangeConfirmEmail(string $to, string $link): bool {
        $subject = 'Confirmation de changement de mot de passe - ' . (defined('APP_NAME') ? APP_NAME : 'Partageons la Forêt');

        $vars = ['link' => $link];
        ob_start();
        extract($vars, EXTR_SKIP);
        include __DIR__ . '/../templates/emails/password-change-confirm.php';
        $body = ob_get_clean();

        return $this->sendHtml($to, $subject, $body);
    }
}
