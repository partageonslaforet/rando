<?php
header('Content-Type: application/json; charset=utf-8');
$rootPath = realpath(__DIR__ . '/../..');
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/src/Services/Subscribers.php';
require_once $rootPath . '/includes/mailer.php';
require_once $rootPath . '/logs/error.log.php';

try {
    logError('subscribers/request-manage-link', 'Request start', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    logError('subscribers/request-manage-link', 'Input parsed', [ 'email_provided' => (bool) $email ]);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Si un compte existe, un email a été envoyé']);
        exit;
    }

    $pdo = getConnection();
    $stmt = $pdo->prepare('SELECT id, verified_at FROM event_subscribers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    logError('subscribers/request-manage-link', 'Lookup subscriber', [
        'found' => (bool) $sub,
        'verified' => $sub && !empty($sub['verified_at'])
    ]);

    if ($sub && $sub['verified_at']) {
        $token = bin2hex(random_bytes(32));
        $upd = $pdo->prepare('UPDATE event_subscribers SET manage_token = ?, manage_token_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?');
        $upd->execute([$token, $sub['id']]);
        logError('subscribers/request-manage-link', 'Token generated and saved', [ 'subscriber_id' => $sub['id'] ]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $manageUrl = $scheme . $host . '/subscribers/manage.php?token=' . urlencode($token);

        ob_start();
        $manage_url = $manageUrl;
        include $rootPath . '/templates/emails/subscriber-manage-link.php';
        $emailBody = ob_get_clean();

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = MAIL_HOST; $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME; $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION; $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8'; $mail->Encoding = 'base64';
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email);
        $mail->Subject = 'Gérer mes abonnements - Partageons La Forêt';
        $mail->isHTML(true); $mail->msgHTML($emailBody);
        $mail->AltBody = "Gérez vos abonnements :\n$manageUrl\n\nCe lien expire dans 24 heures.";
        $sent = $mail->send();
        logError('subscribers/request-manage-link', 'Mailer result', [ 'sent' => $sent, 'error' => $sent ? null : $mail->ErrorInfo ]);
    } else {
        // Log le fait que soit non trouvé soit non vérifié
        logError('subscribers/request-manage-link', 'No email sent (not found or not verified)', [ 'email_hash' => sha1(strtolower($email)) ]);
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Si un compte existe, un email a été envoyé']);
} catch (Exception $e) {
    logError('subscribers/request-manage-link', 'Unhandled exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Si un compte existe, un email a été envoyé']);
}
