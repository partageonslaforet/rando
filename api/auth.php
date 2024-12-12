<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../private/includes/config.php';
require_once __DIR__ . '/../private/includes/db.php';
require_once __DIR__ . '/../private/utils/mailer_debug.php';  // Assurez-vous que ce chemin est correct
require_once __DIR__ . '/../private/includes/auth_middleware.php';
require_once __DIR__ . '/../private/includes/Logger.php';
require_once __DIR__ . '/../private/includes/init_logs.php';

// Initialiser le dossier de logs
$logInit = initLogsDirectory();

// Utiliser error_log pour le débogage initial
error_log("Log initialization result: " . json_encode($logInit));

// Si l'initialisation des logs a échoué, utiliser error_log
if (!$logInit['success']) {
    error_log("Failed to initialize logs: " . json_encode($logInit['errors']));
    // Continuer quand même avec le logger
}

$logger = new Logger();
$logger->info('Auth endpoint initialized', [
    'logs_directory' => $logInit['directory'],
    'log_file' => $logInit['file'],
    'init_success' => $logInit['success'],
    'init_errors' => $logInit['errors']
]);

use Firebase\JWT\JWT;

$config = require __DIR__ . '/../private/includes/config.php';

$auth = new AuthMiddleware($pdo);
//$logger = new Logger();

// Gérer les CORS
$auth->handleCORS();

// Définir l'en-tête de réponse JSON
header('Content-Type: application/json');

// Récupérer les données de la requête
$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];

// Amélioration de la détection du path
$path = '';
if (isset($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
} elseif (isset($_SERVER['REQUEST_URI'])) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Enlever le préfixe /api/auth/ si présent
    $path = preg_replace('#^/api/auth/?#', '', $path);
    $path = trim($path, '/');
}

$logger->info("API Request", [
    'method' => $method,
    'path' => $path,
    'input' => $input,
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'none',
    'path_info' => $_SERVER['PATH_INFO'] ?? 'none'
]);

try {
    switch ($path) {
        case 'register':
            if ($method !== 'POST') {
                throw new Exception('Méthode non autorisée', 405);
            }

            // Validation des données
            if (!isset($input['email']) || !isset($input['name'])) {
                throw new Exception('Données manquantes', 422);
            }

            $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
            if (!$email) {
                throw new Exception('Email invalide', 422);
            }

            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $logger->warning('Registration failed - Email already exists', ['email' => $email]);
                http_response_code(409);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Cet email est déjà utilisé'
                ]);
                break;
            }

            // Générer le token de vérification
            $verificationToken = bin2hex(random_bytes(32));

            // Insérer l'utilisateur
            $stmt = $pdo->prepare('
                INSERT INTO users (name, email, verification_token, created_at) 
                VALUES (?, ?, ?, NOW())
            ');

            try {
                $stmt->execute([
                    $input['name'],
                    $email,
                    $verificationToken
                ]);

                // Envoyer l'email de vérification
                $emailService = new EmailService();
                $emailSent = $emailService->sendConfirmationEmail($email, $input['name'], $verificationToken);

                if (!$emailSent) {
                    // Rollback si l'email n'a pas pu être envoyé
                    $stmt = $pdo->prepare('DELETE FROM users WHERE email = ?');
                    $stmt->execute([$email]);
                    throw new Exception('Erreur lors de l\'envoi de l\'email de vérification');
                }

                $logger->info('User registered successfully', [
                    'email' => $email,
                    'name' => $input['name']
                ]);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Inscription réussie. Veuillez vérifier votre email.'
                ]);

            } catch (Exception $e) {
                $logger->error('Registration error', [
                    'error' => $e->getMessage(),
                    'email' => $email
                ]);
                throw new Exception('Erreur lors de l\'inscription', 500);
            }
            break;

        case 'login':
            if ($method !== 'POST') {
                throw new Exception('Méthode non autorisée', 405);
            }

            $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $password = $input['password'] ?? '';

            if (!$email || !$password) {
                $logger->warning("Login failed - Missing credentials", ['email' => $email]);
                throw new Exception('Email ou mot de passe manquant', 400);
            }

            // Récupérer l'utilisateur de la base de données
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $logger->info("User lookup result", [
                'email' => $email,
                'found' => !empty($user)
            ]);

            if (!$user || !password_verify($password, $user['password'])) {
                $logger->warning("Login failed - Invalid credentials", ['email' => $email]);
                throw new Exception('Email ou mot de passe incorrect', 401);
            }

            if (!$user['is_verified']) {
                $logger->warning("Login failed - Email not verified", ['email' => $email]);
                throw new Exception('Email non vérifié', 403);
            }

            // Générer le token
            $tokenResponse = $auth->generateTokens($user['id'], $user['role']);
            
            if (!$tokenResponse['success']) {
                throw new Exception('Erreur lors de la génération du token', 500);
            }

            // Mettre à jour la dernière connexion
            $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $stmt->execute([$user['id']]);

            $logger->info("Login successful", ['user_id' => $user['id']]);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'token' => $tokenResponse['token'],
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'name' => $user['name'],
                        'role' => $user['role'],
                        'organization' => $user['organization'],
                        'phone' => $user['phone'],
                        'address' => $user['address'],
                        'created_at' => $user['created_at'],
                        'last_login' => $user['last_login']
                    ]
                ]
            ]);
            break;

        case 'verify-email':
            if ($method !== 'POST') {
                throw new Exception('Méthode non autorisée', 405);
            }
            
            $token = $input['token'] ?? '';
            if (!$token) {
                throw new Exception('Token manquant', 400);
            }
            
            // Vérifier et activer le compte
            $stmt = $pdo->prepare('
                UPDATE users 
                SET is_verified = 1, verification_token = NULL 
                WHERE verification_token = ? AND is_verified = 0
            ');
            $stmt->execute([$token]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Token invalide ou déjà utilisé', 400);
            }
            
            $logger->info("Email verified", ['token' => $token]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Email vérifié avec succès'
            ]);
            break;

        case 'resend-verification':
            if ($method !== 'POST') {
                throw new Exception('Méthode non autorisée', 405);
            }

            $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
            if (!$email) {
                throw new Exception('Email manquant ou invalide', 400);
            }

            // Récupérer l'utilisateur
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('Utilisateur non trouvé', 404);
            }

            if ($user['is_verified']) {
                throw new Exception('Email déjà vérifié', 400);
            }

            // Générer un nouveau token de vérification
            $verificationToken = bin2hex(random_bytes(32));

            // Mettre à jour le token dans la base de données
            $stmt = $pdo->prepare('UPDATE users SET verification_token = ? WHERE id = ?');
            $stmt->execute([$verificationToken, $user['id']]);

            // Envoyer le nouvel email de vérification
            $emailService = new EmailService();
            $emailSent = $emailService->sendConfirmationEmail($user['email'], $user['name'], $verificationToken);

            if (!$emailSent) {
                $logger->error("Failed to send verification email", ['email' => $email]);
                throw new Exception('Erreur lors de l\'envoi de l\'email de vérification');
            }

            $logger->info("Verification email sent", ['email' => $email]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Un nouvel email de vérification a été envoyé'
            ]);
            break;

        case 'profile':
            if ($method !== 'GET') {
                throw new Exception('Méthode non autorisée', 405);
            }

            $authData = $auth->authenticate();
            if (!$authData) {
                throw new Exception('Non autorisé', 401);
            }

            $stmt = $pdo->prepare('
                SELECT id, email, name, role, organization, phone, address, created_at, last_login
                FROM users WHERE id = ?
            ');
            $stmt->execute([$authData['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('Utilisateur non trouvé', 404);
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'user' => $user
                ]
            ]);
            break;

        case 'update-profile':
            if ($method !== 'PATCH') {
                throw new Exception('Méthode non autorisée', 405);
            }

            $authData = $auth->authenticate();
            if (!$authData) {
                throw new Exception('Non autorisé', 401);
            }

            $updates = [];
            $params = [];

            if (isset($input['name'])) {
                $updates[] = 'name = ?';
                $params[] = $input['name'];
            }

            if (isset($input['phone'])) {
                $updates[] = 'phone = ?';
                $params[] = $input['phone'];
            }

            if (isset($input['address'])) {
                $updates[] = 'address = ?';
                $params[] = $input['address'];
            }

            if (empty($updates)) {
                throw new Exception('Aucune mise à jour fournie', 400);
            }

            $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
            $stmt->execute(array_merge($params, [$authData['id']]));

            $logger->info('Profile updated', [
                'user_id' => $authData['id'],
                'updates' => array_combine(
                    array_map(function($u) {
                        return explode(' = ', $u)[0];
                    }, $updates),
                    array_slice($params, 0, count($updates))
                )
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Profil mis à jour avec succès'
            ]);
            break;

        case 'logout':
            if ($method !== 'POST') {
                throw new Exception('Méthode non autorisée', 405);
            }

            $logger->info("Logout request");
            echo json_encode([
                'status' => 'success',
                'message' => 'Déconnexion réussie'
            ]);
            break;

        default:
            throw new Exception('Route non trouvée', 404);
    }
} catch (Exception $e) {
    $logger->error("API Error", [
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}