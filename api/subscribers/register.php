<?php
/**
 * Endpoint: POST /api/subscribers/register.php
 * Rôle: Enregistre un nouvel abonné aux notifications d'événements
 * Paramètres: email, categories[], notification_frequency
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    $rootPath = realpath(__DIR__ . '/../..');
    $subscribersPath = $rootPath . '/src/Services/Subscribers.php';
    
    // Logs de diagnostic
    $diagnostics = [
        '__DIR__' => __DIR__,
        'rootPath' => $rootPath,
        'subscribersPath' => $subscribersPath,
        'file_exists' => file_exists($subscribersPath),
        'is_readable' => is_readable($subscribersPath),
        'is_file' => is_file($subscribersPath),
        'realpath_result' => realpath(__DIR__ . '/../..'),
        'scandir_api' => @scandir(__DIR__),
        'scandir_root' => @scandir($rootPath)
    ];
    
    // Envoyer les logs si fichier manquant
    if (!file_exists($subscribersPath)) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fichier manquant', 'diagnostics' => $diagnostics]);
        exit;
    }
    
    require_once $rootPath . '/config/database.php';
    require_once $subscribersPath;
    require_once $rootPath . '/includes/mailer.php';
    require_once $rootPath . '/logs/error.log.php';
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    // Récupérer les données
    $email = trim($_POST['email'] ?? '');
    $categoryIds = isset($_POST['categories']) && is_array($_POST['categories']) 
        ? array_map('intval', $_POST['categories']) 
        : [];
    $frequency = $_POST['notification_frequency'] ?? 'immediate';

    // Validation
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email requis']);
        exit;
    }

    if (empty($categoryIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Sélectionnez au moins une catégorie']);
        exit;
    }

    if (!in_array($frequency, ['immediate', 'weekly'])) {
        $frequency = 'immediate';
    }

    // Enregistrer l'abonné
    $subscribers = new Subscribers();
    $result = $subscribers->register($email, $categoryIds, $frequency);

    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }

    // Envoyer email de vérification
    $subscriberId = $result['subscriber_id'];
    $token = $result['token'];
    $verificationUrl = (defined('APP_URL') ? rtrim(APP_URL, '/') : 'https://' . $_SERVER['HTTP_HOST']) 
        . '/api/subscribers/verify.php?token=' . urlencode($token);

    // Charger le template d'email
    ob_start();
    include __DIR__ . '/../../templates/emails/subscriber-verification.php';
    $emailBody = ob_get_clean();

    // Envoyer via PHPMailer
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;

        // Encodage correct des accents
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email);
        $mail->Subject = 'Confirmez votre abonnement - Partageons La Forêt';
        $mail->isHTML(true);
        $mail->msgHTML($emailBody);
        // Version texte simple (fallback)
        $mail->AltBody = "Bonjour,\n\nConfirmez votre abonnement en ouvrant ce lien :\n" . $verificationUrl . "\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet email.";

        if (!$mail->send()) {
            logError('subscribers/register.php', 'Erreur envoi email', ['error' => $mail->ErrorInfo]);
            // Continuer même si l'email échoue
        }
    } catch (Exception $e) {
        logError('subscribers/register.php', 'Exception PHPMailer', ['error' => $e->getMessage()]);
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Inscription réussie. Vérifiez votre email pour confirmer.',
        'subscriber_id' => $subscriberId
    ]);

} catch (Exception $e) {
    ob_clean();
    logError('subscribers/register.php', 'Erreur générale', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
ob_end_flush();
?>
