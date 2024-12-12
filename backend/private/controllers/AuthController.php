<?php
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../config/config.php';

class AuthController {
    private $pdo;
    private $authMiddleware;
    private $logger;
    private $emailService;
    private $logFile;
    private $config;
    private $corsHeadersSent = false;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware($pdo);
        $this->logger = new Logger();
        $this->logFile = __DIR__ . '/../logs/auth.log';
        $this->config = require __DIR__ . '/../config/config.php';
        
        try {
            $this->emailService = new EmailService();
            $this->log("EmailService initialized successfully");
        } catch (Exception $e) {
            $this->log("Failed to initialize EmailService: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        error_log($message); // Also log to PHP error log
    }

    private function generateVerificationToken(): string {
        return bin2hex(random_bytes(32));
    }

    private function handleCORS() {
        if ($this->corsHeadersSent) {
            return;
        }

        // Autoriser l'origine spécifique
        header("Access-Control-Allow-Origin: " . $this->config['FRONTEND_URL']);
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");
        
        $this->corsHeadersSent = true;

        // Si c'est une requête OPTIONS, arrêter ici
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
    }

    public function register() {
        try {
            $this->handleCORS();
            $this->log("Starting registration process");
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception('Données invalides', 400);
            }

            // Log des données reçues (sans le mot de passe)
            $logData = $data;
            unset($logData['password']);
            $this->log("Registration data received: " . json_encode($logData));

            // Validation des données
            $requiredFields = ['email', 'password', 'name'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $this->log("Missing required field: $field", 'ERROR');
                    throw new Exception("Le champ $field est requis", 400);
                }
            }

            // Vérifier si l'email existe déjà
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                $this->log("Email already exists: " . $data['email'], 'WARNING');
                throw new Exception('Cet email est déjà utilisé', 409);
            }

            // Démarrer la transaction
            $this->pdo->beginTransaction();
            $this->log("Starting database transaction");

            try {
                // Hash du mot de passe
                $hashedPassword = password_hash($data['password'], PASSWORD_ARGON2ID);

                // Insertion de l'utilisateur
                $stmt = $this->pdo->prepare('
                    INSERT INTO users (email, password, name, organization, role, is_verified, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ');
                
                $stmt->execute([
                    $data['email'],
                    $hashedPassword,
                    $data['name'],
                    $data['organization'] ?? null,
                    'user',
                    0
                ]);

                $userId = $this->pdo->lastInsertId();
                $this->log("User inserted with ID: $userId");

                // Générer et sauvegarder le token de vérification
                $verificationToken = $this->generateVerificationToken();
                $stmt = $this->pdo->prepare('
                    INSERT INTO email_verifications (user_id, token, expires_at)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                ');
                $stmt->execute([$userId, $verificationToken]);
                $this->log("Verification token saved for user: $userId");

                // Envoyer l'email de confirmation
                try {
                    $emailSent = $this->emailService->sendConfirmationEmail(
                        $data['email'],
                        $data['name'],
                        $verificationToken
                    );

                    if (!$emailSent) {
                        throw new Exception("L'envoi de l'email a échoué");
                    }

                    $this->log("Verification email sent to: " . $data['email']);
                    $this->pdo->commit();
                    $this->log("Transaction committed");

                    return [
                        'status' => 'success',
                        'message' => 'Inscription réussie. Veuillez vérifier votre email.'
                    ];

                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    $this->log("Registration failed during email sending: " . $e->getMessage(), 'ERROR');
                    throw new Exception("Erreur lors de l'envoi de l'email de confirmation");
                }

            } catch (Exception $e) {
                $this->pdo->rollBack();
                $this->log("Registration failed: " . $e->getMessage(), 'ERROR');
                throw $e;
            }

        } catch (Exception $e) {
            $this->log("Registration error: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    public function verifyEmail() {
        try {
            $this->handleCORS();
            
            $token = $_GET['token'] ?? null;
            
            if (!$token) {
                throw new Exception('Token de vérification manquant', 400);
            }

            // Vérifier le token
            $stmt = $this->pdo->prepare('
                SELECT user_id 
                FROM email_verifications 
                WHERE token = ? 
                AND expires_at > NOW() 
                AND used_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([$token]);
            $verification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$verification) {
                throw new Exception('Token invalide ou expiré', 400);
            }

            // Mettre à jour le statut de l'utilisateur
            $stmt = $this->pdo->prepare('
                UPDATE users 
                SET is_verified = 1 
                WHERE id = ?
            ');
            $stmt->execute([$verification['user_id']]);

            // Marquer le token comme utilisé
            $stmt = $this->pdo->prepare('
                UPDATE email_verifications 
                SET used_at = NOW() 
                WHERE token = ?
            ');
            $stmt->execute([$token]);

            $this->logger->info('Email verified', [
                'user_id' => $verification['user_id']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Email vérifié avec succès'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Email verification error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            http_response_code($e->getCode() ?: 500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function login() {
        try {
            $this->handleCORS();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || empty($data['email']) || empty($data['password'])) {
                throw new Exception('Email et mot de passe requis', 400);
            }

            // Récupération de l'utilisateur
            $stmt = $this->pdo->prepare('
                SELECT id, email, password, name, role, is_verified, organization, created_at, last_login
                FROM users 
                WHERE email = ? AND is_active = 1
            ');
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($data['password'], $user['password'])) {
                throw new Exception('Identifiants invalides', 401);
            }

            // Mise à jour du dernier login
            $stmt = $this->pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $stmt->execute([$user['id']]);

            // Génération des tokens
            $tokens = $this->authMiddleware->generateTokens($user['id'], $user['role']);

            // Retirer le mot de passe de la réponse
            unset($user['password']);

            $this->logger->info('User logged in', [
                'user_id' => $user['id'],
                'email' => $user['email']
            ]);

            echo json_encode([
                'success' => true,
                'user' => $user,
                'token' => $tokens['token']
            ]);

        } catch (Exception $e) {
            $this->logger->error('Login error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            http_response_code($e->getCode() ?: 500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function logout() {
        try {
            $this->handleCORS();

            $headers = getallheaders();
            $auth = $headers['Authorization'] ?? '';

            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                $token = $matches[1];
                
                $this->logger->info('User logout', [
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Logout error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erreur lors de la déconnexion'
            ]);
        }
    }

    public function refreshToken() {
        try {
            $this->handleCORS();
            
            $headers = getallheaders();
            $refreshToken = $headers['X-Refresh-Token'] ?? null;

            if (!$refreshToken) {
                throw new Exception('Refresh token non fourni', 400);
            }

            $tokens = $this->authMiddleware->handleTokenRefresh($refreshToken);

            echo json_encode([
                'success' => true,
                'token' => $tokens['token']
            ]);

        } catch (Exception $e) {
            $this->logger->error('Token refresh error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            http_response_code($e->getCode() ?: 500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}