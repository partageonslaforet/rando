<?php
/**
 * Envoi du formulaire de contact.
 * Valide les champs, construit l'e-mail HTML à destination de l'administrateur,
 * envoie une copie à l'expéditeur si demandé, avec fallback mail().
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../logs/error.log.php';

header('Content-Type: application/json');

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['success' => false, 'error' => 'Méthode non autorisée'], 405);
    }

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $copy    = isset($_POST['copy']) && in_array($_POST['copy'], ['1','on','true'], true);

    if ($name === '' || $subject === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'error' => 'Veuillez renseigner tous les champs valides.'], 400);
    }

    $toEmail = defined('CONTACT_TO_EMAIL') ? CONTACT_TO_EMAIL : 'rando@partageonslaforet.be';
    $toName  = defined('CONTACT_TO_NAME')  ? CONTACT_TO_NAME  : 'Partageons la Forêt';

    $cssPath = __DIR__ . '/../../public/assets/css/email-styles.css';
    $css = is_readable($cssPath) ? (file_get_contents($cssPath) ?: '') : '';
    $brand = 'Partageons la Forêt';
    $h = static function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
    $vars = [
        'css' => $css,
        'brand' => $brand,
        'name' => $name,
        'email' => $email,
        'subjectTxt' => $subject,
        'messageTxt' => $message,
        'isCopy' => false,
    ];
    ob_start();
    extract($vars, EXTR_SKIP);
    include __DIR__ . '/../../templates/emails/contact.php';
    $body = ob_get_clean();

    $mailer = new Mailer();

    // Récupérer transport/params PHPMailer et définir Reply-To
    $transport = 'unknown';
    $smtpHost = null; $smtpPort = null; $smtpSecure = null; $smtpAuth = null;
    try {
        $ref = new ReflectionClass($mailer);
        if ($ref->hasProperty('mailer')) {
            $prop = $ref->getProperty('mailer');
            $prop->setAccessible(true);
            $pm = $prop->getValue($mailer);
            if (isset($pm->Mailer)) { $transport = (string) $pm->Mailer; }
            if ($transport === 'smtp') {
                $smtpHost = $pm->Host ?? null;
                $smtpPort = $pm->Port ?? null;
                $smtpSecure = $pm->SMTPSecure ?? null;
                $smtpAuth = isset($pm->SMTPAuth) && $pm->SMTPAuth ? 'yes' : 'no';
            }
            if (method_exists($pm, 'addReplyTo')) {
                $pm->addReplyTo($email, $name);
            }
        }
    } catch (Throwable $e) {
        logError('api/contact/send.php', 'Inspection PHPMailer/Reply-To', ['exception' => $e->getMessage()]);
    }

    $subjectLine = '[Contact] ' . $subject;
    $sent = $mailer->sendHtml($toEmail, $subjectLine, $body);
    $method = $transport; // 'smtp' | 'mail' | 'unknown'

    if (!$sent) {
        // Fallback mail() pour mutualisé
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Partageons la Forêt';
        $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'noreply@partageonslaforet.be';
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
        // Enveloppe Return-Path (meilleure délivrabilité sur mutualisé)
        $additional = filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? ('-f ' . $fromEmail) : '';
        if ($additional) {
            $sent = @mail($toName . ' <' . $toEmail . '>', $subjectLine, $body, implode("\r\n", $headers), $additional);
        } else {
            $sent = @mail($toName . ' <' . $toEmail . '>', $subjectLine, $body, implode("\r\n", $headers));
        }
        $method = 'mail-fallback';
    }

    if (!$sent) {
        logError('api/contact/send.php', 'Échec envoi message de contact', [
            'name' => $name,
            'email' => $email,
            'subject' => $subject
        ]);
        respond(['success' => false, 'error' => 'Impossible d’envoyer votre message pour le moment.'], 500);
    }

    // Log de succès avec contexte de transport (sanitisé)
    $ctx = [
        'to' => $toEmail,
        'from' => $email,
        'subject' => $subject,
        'method' => $method,
    ];
    if ($transport === 'smtp') {
        $ctx['smtp'] = [
            'host' => $smtpHost,
            'port' => $smtpPort,
            'secure' => $smtpSecure ?: 'none',
            'auth' => $smtpAuth ?: 'no',
        ];
    }
    logError('api/contact/send.php', 'Message de contact envoyé', $ctx);

    $copyResult = null;
    if ($copy) {
        $copySubject = '[Copie] ' . $subjectLine;
        $copyVars = $vars;
        $copyVars['isCopy'] = true;
        ob_start();
        extract($copyVars, EXTR_SKIP);
        include __DIR__ . '/../../templates/emails/contact.php';
        $copyBody = ob_get_clean();

        try {
            $copyMailer = new Mailer();
            $copyResult = $copyMailer->sendHtml($email, $copySubject, $copyBody);
        } catch (Throwable $e) {
            $copyResult = false;
        }

        if (!$copyResult) {
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=UTF-8';
            $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Partageons la Forêt';
            $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'noreply@partageonslaforet.be';
            $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
            $additional = filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? ('-f ' . $fromEmail) : '';
            if ($additional) {
                $copyResult = @mail($name . ' <' . $email . '>', $copySubject, $copyBody, implode("\r\n", $headers), $additional);
            } else {
                $copyResult = @mail($name . ' <' . $email . '>', $copySubject, $copyBody, implode("\r\n", $headers));
            }
        }

        if ($copyResult) {
            logError('api/contact/send.php', 'Copie message de contact envoyée', [
                'to' => $email,
                'subject' => $subject
            ]);
        } else {
            logError('api/contact/send.php', 'Échec envoi copie message de contact', [
                'to' => $email,
                'subject' => $subject
            ]);
        }
    }

    respond(['success' => true, 'message' => 'Message envoyé. Merci !', 'copy' => $copy ? ($copyResult ? 'sent' : 'failed') : 'skipped']);

} catch (Throwable $e) {
    logError('api/contact/send.php', 'Exception générale', ['exception' => $e->getMessage()]);
    respond(['success' => false, 'error' => 'Erreur serveur.'], 500);
}
