<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../../private/includes/config.php';
require_once __DIR__ . '/../../private/includes/db.php';
require_once __DIR__ . '/../../private/utils/mailer_debug.php';
require_once __DIR__ . '/../../private/includes/auth_middleware.php';
require_once __DIR__ . '/../../private/includes/Logger.php';
require_once __DIR__ . '/../../private/includes/init_logs.php';
require_once __DIR__ . '/../services/EmailService.php';

// Configuration du fichier de log
$logPath = $_ENV['LOG_PATH'] ?? '/home/cool5792/api.rando.partageons.laforet.be/logs';
$logFile = $logPath . '/auth-debug.log';

// Créer le dossier de logs s'il n'existe pas
if (!file_exists($logPath)) {
    mkdir($logPath, 0775, true);
}

// Configurer error_log pour écrire dans notre fichier
ini_set('error_log', $logFile);
error_log("=== Démarrage du service d'authentification ===");

// Tableau pour stocker les messages de debug
$debugLogs = [];

function debug_log($message, $data = null) {
    global $debugLogs;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= ': ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    // Log dans le fichier
    error_log($logMessage);
    
    // Stocker pour la réponse API
    $debugLogs[] = $logMessage;
    
    // Si on est en développement, afficher aussi dans la console PHP
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development') {
        echo $logMessage . "\n";
    }
}

// Log initial pour tester
debug_log("Service d'authentification initialisé", [
    'log_file' => $logFile,
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
]);

// Vérifier les permissions
if (file_exists($logPath)) {
    $perms = substr(sprintf('%o', fileperms($logPath)), -4);
    debug_log("Permissions du dossier de logs", ['perms' => $perms]);
    
    $owner = posix_getpwuid(fileowner($logPath));
    $group = posix_getgrgid(filegroup($logPath));
    debug_log("Propriétaire et groupe du dossier de logs", ['owner' => $owner['name'], 'group' => $group['name']]);
}

// Initialiser le dossier de logs
$logInit = initLogsDirectory();

// Utiliser debug_log pour le débogage initial
debug_log("Résultat de l'initialisation des logs", $logInit);

// Si l'initialisation des logs a échoué, utiliser debug_log
if (!$logInit['success']) {
    debug_log("Échec de l'initialisation des logs", $logInit['errors']);
}

$logger = new Logger();
$logger->info('Auth endpoint initialized', [
    'logs_directory' => $logInit['directory'],
    'log_file' => $logInit['file'],
    'init_success' => $logInit['success'],
    'init_errors' => $logInit['errors']
]);

use Firebase\JWT\JWT;

$config = require __DIR__ . '/../../private/includes/config.php';

$auth = new AuthMiddleware($pdo);

// Gérer les CORS
$auth->handleCORS();

// Définir l'en-tête de réponse JSON
header('Content-Type: application/json');

// Récupérer les données de la requête
$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];
debug_log("Requête reçue", ['method' => $method, 'input' => $input]);

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
                error_log("🛑 [AUTH] Méthode non autorisée: " . $method);
                throw new Exception('Méthode non autorisée', 405);
            }

            error_log("📝 [AUTH] Début du processus d'inscription");

            // Validation des données
            if (!isset($input['email']) || !isset($input['name'])) {
                error_log("❌ [AUTH] Données manquantes dans la requête");
                throw new Exception('Données manquantes', 422);
            }

            $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
            if (!$email) {
                error_log("❌ [AUTH] Email invalide: " . $input['email']);
                throw new Exception('Email invalide', 422);
            }

            error_log("✅ [AUTH] Données validées pour: " . $email);

            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                error_log("⚠️ [AUTH] Email déjà existant: " . $email);
                http_response_code(409);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Cet email est déjà utilisé'
                ]);
                break;
            }

            error_log("✅ [AUTH] Email disponible: " . $email);

            // Générer le token de vérification
            $verificationToken = bin2hex(random_bytes(32));
            error_log("🔑 [AUTH] Token de vérification généré");

            // Insérer l'utilisateur
            $stmt = $pdo->prepare('
                INSERT INTO users (name, email, verification_token, created_at) 
                VALUES (?, ?, ?, NOW())
            ');

            try {
                error_log("💾 [AUTH] Tentative d'insertion en base de données...");
                $stmt->execute([
                    $input['name'],
                    $email,
                    $verificationToken
                ]);
                error_log("✅ [AUTH] Utilisateur inséré avec succès en base de données");

                // Envoyer l'email de vérification
                try {
                    error_log("📧 [AUTH] Création de l'instance EmailService...");
                    $emailService = new EmailService();
                    error_log("✅ [AUTH] Instance EmailService créée");
                    
                    error_log("📧 [AUTH] Tentative d'envoi de l'email de confirmation...");
                    $emailSent = $emailService->sendConfirmationEmail($email, $input['name'], $verificationToken);
                    error_log($emailSent ? "✅ [AUTH] Email envoyé avec succès" : "❌ [AUTH] Échec de l'envoi de l'email");

                    if (!$emailSent) {
                        error_log("❌ [AUTH] Échec de l'envoi de l'email de vérification");
                        throw new Exception('Erreur lors de l\'envoi de l\'email de vérification');
                    }

                } catch (Exception $e) {
                    error_log("🚨 [AUTH] Erreur lors de l'envoi de l'email: " . $e->getMessage());
                    throw new Exception('Erreur lors de l\'envoi de l\'email de vérification: ' . $e->getMessage());
                }

                error_log("🎉 [AUTH] Inscription réussie pour: " . $email);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Inscription réussie. Veuillez vérifier votre email.'
                ]);

            } catch (Exception $e) {
                error_log("🚨 [AUTH] Erreur lors de l'inscription: " . $e->getMessage());
                throw new Exception('Erreur lors de l\'inscription: ' . $e->getMessage(), 500);
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
                ],
                'debug' => $debugLogs
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
                'message' => 'Email vérifié avec succès',
                'debug' => $debugLogs
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
            try {
                $emailService = new EmailService();
                $emailSent = $emailService->sendConfirmationEmail($user['email'], $user['name'], $verificationToken);

                if (!$emailSent) {
                    $logger->error("Failed to send verification email", ['email' => $email]);
                    throw new Exception('Erreur lors de l\'envoi de l\'email de vérification');
                }

                $logger->info("Verification email sent successfully", ['email' => $email]);
            } catch (Exception $e) {
                $logger->error("Email sending failed", [
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
                throw new Exception('Erreur lors de l\'envoi de l\'email de vérification');
            }

            $logger->info("Verification email resent", ['email' => $email]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Un nouvel email de vérification a été envoyé',
                'debug' => $debugLogs
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
                ],
                'debug' => $debugLogs
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
                'message' => 'Profil mis à jour avec succès',
                'debug' => $debugLogs
            ]);
            break;

        case 'logout':
            if ($method !== 'POST') {
                throw new Exception('Méthode non autorisée', 405);
            }

            $logger->info("Logout request");
            echo json_encode([
                'status' => 'success',
                'message' => 'Déconnexion réussie',
                'debug' => $debugLogs
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
        'message' => $e->getMessage(),
        'debug' => $debugLogs
    ]);
}