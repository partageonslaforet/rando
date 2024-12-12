<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use \Firebase\JWT\ExpiredException;

class AuthMiddleware {
    private $config;
    private $pdo;
    private $logger;
    private static $corsHeadersSent = false;

    public function __construct($pdo) {
        $this->config = [
            'jwt' => require __DIR__ . '/../config/jwt.php',
            'cors' => [
                'allowed_origins' => [
                    'http://localhost:5173',
                    'https://rando.partageonslaforet.be',
                    'https://www.rando.partageonslaforet.be'
                ],
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'Authorization', 'X-Refresh-Token']
            ]
        ];
        $this->pdo = $pdo;
        $this->logger = new Logger();
    }

    public function handleCORS() {
        if (self::$corsHeadersSent) {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Autoriser toutes les origines listées
        if (in_array($origin, $this->config['cors']['allowed_origins'])) {
            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Allow-Methods: " . implode(', ', $this->config['cors']['allowed_methods']));
            header("Access-Control-Allow-Headers: " . implode(', ', $this->config['cors']['allowed_headers']));
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 3600");
            header("Vary: Origin");
            self::$corsHeadersSent = true;
        }

        // Gérer la requête OPTIONS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header("HTTP/1.1 200 OK");
            exit(0);
        }
    }
    
    public function authenticate($requireVerified = true) {
        $this->handleCORS();

        try {
            $headers = getallheaders();
            $auth = $headers['Authorization'] ?? '';

            if (!preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                throw new Exception('Token non fourni', 401);
            }

            $token = $matches[1];
            
            try {
                $decoded = JWT::decode($token, new Key($this->config['jwt']['secret'], 'HS256'));
            } catch (ExpiredException $e) {
                throw new Exception('Token expiré', 401);
            } catch (Exception $e) {
                throw new Exception('Token invalide', 401);
            }

            // Vérification de l'utilisateur
            $stmt = $this->pdo->prepare('
                SELECT id, email, name, role, is_verified, organization, created_at, last_login 
                FROM users 
                WHERE id = ? AND is_active = 1
                ' . ($requireVerified ? ' AND is_verified = 1' : '')
            );
            $stmt->execute([$decoded->sub]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('Utilisateur non trouvé ou inactif', 401);
            }

            return [
                'success' => true,
                'user' => $user,
                'token' => $token
            ];

        } catch (Exception $e) {
            $this->logger->error('Authentication error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            $this->sendError($e->getMessage(), $e->getCode() ?: 401);
        }
    }

    public function requireAdmin() {
        $auth = $this->authenticate();
        if ($auth['user']['role'] !== 'admin') {
            throw new Exception('Accès non autorisé', 403);
        }
        return $auth;
    }

    public function generateTokens($userId, $role = 'user') {
        $issuedAt = time();
        $accessExpiration = $issuedAt + $this->config['jwt']['expiration'];
        
        $accessToken = JWT::encode([
            'iss' => $this->config['jwt']['issuer'] ?? 'rando.partageonslaforet.be',
            'sub' => $userId,
            'role' => $role,
            'iat' => $issuedAt,
            'exp' => $accessExpiration
        ], $this->config['jwt']['secret'], 'HS256');

        return [
            'success' => true,
            'token' => $accessToken,
            'expires_in' => $this->config['jwt']['expiration']
        ];
    }

    public function handleTokenRefresh($refreshToken) {
        try {
            $decoded = JWT::decode($refreshToken, new Key($this->config['jwt']['secret'], 'HS256'));
            return $this->generateTokens($decoded->sub, $decoded->role);
        } catch (Exception $e) {
            $this->logger->error('Token refresh error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw new Exception('Échec du rafraîchissement du token', 401);
        }
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
}