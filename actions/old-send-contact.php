<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/flash_messages.php';
require_once __DIR__ . '/../logs/error.log.php';

// Déterminer si la requête attend du JSON (AJAX/fetch)
function expectsJson(): bool {
    $xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $acceptJson = isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    return $xhr || $acceptJson;
}

function respondJson(array $payload, int $status = 200): void {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if (expectsJson()) {
            respondJson(['success' => false, 'error' => 'Méthode non autorisée'], 405);
        }
        addFlashMessage('danger', 'Méthode non autorisée');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }

    // Lecture et validation basique des champs
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $subject === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (expectsJson()) {
            respondJson(['success' => false, 'error' => 'Veuillez renseigner tous les champs valides.'], 400);
        }
        addFlashMessage('danger', 'Veuillez renseigner tous les champs valides.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }

    // Cibles
    $toEmail = defined('CONTACT_TO_EMAIL') ? CONTACT_TO_EMAIL : 'rando@partageonslaforet.be';
    $toName  = defined('CONTACT_TO_NAME')  ? CONTACT_TO_NAME  : 'Partageons la Forêt';

    // Construire le corps HTML de l'email
    $h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
    $body = '<p><strong>Nom:</strong> ' . $h($name) . '</p>'
          . '<p><strong>Email:</strong> ' . $h($email) . '</p>'
          . '<p><strong>Sujet:</strong> ' . $h($subject) . '</p>'
          . '<p><strong>Message:</strong><br>' . nl2br($h($message)) . '</p>';

    // Envoi via PHPMailer (SMTP)
    $mailer = new Mailer();

    // Définir Reply-To sur l’expéditeur si possible
    try {
        $ref = new ReflectionClass($mailer);
        if ($ref->hasProperty('mailer')) {
            $prop = $ref->getProperty('mailer');
            $prop->setAccessible(true);
            $pm = $prop->getValue($mailer);
            if (method_exists($pm, 'addReplyTo')) {
                $pm->addReplyTo($email, $name);
            }
        }
    } catch (Throwable $e) {
        // On ignore mais on note dans les logs silencieusement
        logError('actions/send-contact.php', 'Reply-To non défini', ['exception' => $e->getMessage()]);
    }

    $subjectLine = '[Contact] ' . $subject;
    $sent = $mailer->sendHtml($toEmail, $subjectLine, $body);

    // Fallback vers mail() si SMTP indisponible (utile en mutualisé)
    if (!$sent) {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Partageons la Forêt';
        $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'noreply@partageonslaforet.be';
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
        $sent = @mail($toName . ' <' . $toEmail . '>', $subjectLine, $body, implode("\r\n", $headers));
    }

    if (!$sent) {
        logError('actions/send-contact.php', 'Échec envoi message de contact', [
            'name' => $name,
            'email' => $email,
            'subject' => $subject
        ]);
        if (expectsJson()) {
            respondJson(['success' => false, 'error' => 'Impossible d’envoyer votre message pour le moment.'], 500);
        }
        addFlashMessage('danger', 'Impossible d’envoyer votre message pour le moment.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }

    if (expectsJson()) {
        respondJson(['success' => true, 'message' => 'Message envoyé. Merci !']);
    }

    addFlashMessage('success', 'Message envoyé. Merci !');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
    exit;

} catch (Throwable $e) {
    logError('actions/send-contact.php', 'Exception générale', ['exception' => $e->getMessage()]);
    if (expectsJson()) {
        respondJson(['success' => false, 'error' => 'Erreur serveur.'], 500);
    }
    addFlashMessage('danger', 'Erreur serveur.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
    exit;
}
