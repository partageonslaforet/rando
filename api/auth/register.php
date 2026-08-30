<?php
/**
 * Inscription sécurisée.
 * - validation côté serveur
 * - hash du mot de passe
 * - token de vérification stocké uniquement via son empreinte
 * - envoi PHPMailer (MailHog en dev)
 */

require_once '../../config/database.php';
require_once '../../includes/csrf.php';
require_once '../../includes/rate_limit.php';
require_once '../../logs/error.log.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// CSRF
$csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verifyCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

// Rate limiting
if (!checkRateLimit('register', 5, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => rateLimitMessage('register')]);
    exit;
}

try {
    // Validation
    if (empty($input['email']) || empty($input['password']) || empty($input['name']) || empty($input['password_confirm'])) {
        throw new Exception('Tous les champs sont obligatoires');
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email invalide');
    }

    if (strlen($input['password']) < 8) {
        throw new Exception('Le mot de passe doit contenir au moins 8 caractères');
    }

    if ($input['password'] !== $input['password_confirm']) {
        throw new Exception('Les mots de passe ne correspondent pas');
    }

    $db = getConnection();

    try {
        $db->beginTransaction();

        // Email déjà utilisé ?
        $stmt = $db->prepare('SELECT id, email_verified FROM users WHERE email = ?');
        $stmt->execute([$input['email']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Réponse neutre : ne pas révéler l'existence du compte
            $db->rollBack();
            echo json_encode([
                'success' => true,
                'message' => 'Si cette adresse email est disponible, un e-mail de confirmation a été envoyé.'
            ]);
            exit;
        }

        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

        // Insertion du compte non confirmé
        $stmt = $db->prepare('
            INSERT INTO users (email, password, name, email_verified, is_active, role, created_at, updated_at)
            VALUES (?, ?, ?, 0, 1, "user", NOW(), NOW())
        ');
        $stmt->execute([$input['email'], $hashedPassword, $input['name']]);
        $userId = (int) $db->lastInsertId();

        // Jeton brut (connu uniquement du mail)
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $db->prepare('
            INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$userId, $tokenHash, $expiresAt]);

        $db->commit();

        // Envoi de l'e-mail de confirmation
        require_once '../../includes/mailer.php';
        $mailer = new Mailer();
        $mailer->sendVerificationEmail($input['email'], $input['name'], $token);

    } catch (PDOException $e) {
        $db->rollBack();
        logError('api/auth/register.php', 'Erreur DB inscription', ['exception' => $e->getMessage()]);
        throw new Exception('Une erreur est survenue lors de l\'inscription');
    }

    // Réponse non violante
    echo json_encode([
        'success' => true,
        'message' => 'Si cette adresse email est valide, un e-mail de confirmation a été envoyé.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
