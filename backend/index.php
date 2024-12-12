<?php
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/logs/error.log');
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/cool5792/logs/api.rando.partageonslaforet.be.error.log');


// Debug simple pour les requêtes
error_log(date('Y-m-d H:i:s') . ' - Début requête: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);

if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && $_SERVER['REQUEST_URI'] === '/auth/profile') {
    // Désactiver la compression
    if(ini_get('zlib.output_compression')) {
        ini_set('zlib.output_compression', 'Off');
    }
    
    // Désactiver le buffering
    if (ob_get_level()) ob_end_clean();
    
    // Headers spécifiques
    header('Content-Type: application/json');
    
    // Log des données reçues
    $input = file_get_contents('php://input');
    error_log('PATCH données reçues: ' . $input);
    
    try {
        // Décoder les données JSON
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }
        
        // Log des données décodées
        error_log('PATCH données décodées: ' . print_r($data, true));
        
        // Traiter la requête normalement...
        // Votre code existant ici...
        
        // Log avant d'envoyer la réponse
        error_log('PATCH traitement terminé, envoi réponse...');
        
        // Envoyer une réponse minimale
        echo json_encode(['status' => 'success']);
        exit;
        
    } catch (Exception $e) {
        error_log('PATCH erreur: ' . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}



// Ajouter cette fonction de log
function debug_log($message, $data = null) {
    $log = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log .= ' - ' . json_encode($data);
    }
    error_log($log);
}

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

try {
    debug_log('Request started', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'input' => file_get_contents('php://input')
    ]);
    // Chargement des variables d'environnement
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    // Connexion à la base de données
    $pdo = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Configuration des headers CORS

    header('Content-Type: application/json');
   

    // Gérer plusieurs origines possibles
    $allowedOrigins = [
        'https://rando.partageonslaforet.be',
        'https://www.rando.partageonslaforet.be'
    ];
    
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }


    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Fonction pour formater les données utilisateur selon votre structure de base de données
    function formatUserData($user) {
        return [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'is_verified' => (bool)$user['is_verified'],
            'is_active' => (bool)$user['is_active'],
            'created_at' => $user['created_at'],
            'last_login' => $user['last_login']
        ];
    }

    // Fonction pour générer un token JWT
    function generateJWT($user) {
        $issuedAt = time();
        $expire = $issuedAt + 60 * 60 * 24; // 24 heures

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'user' => formatUserData($user)
        ];

        return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = trim($uri, '/');
    $input = json_decode(file_get_contents('php://input'), true);
    
    debug_log('Processing request', [
        'method' => $method,
        'uri' => $uri,
        'input' => $input
    ]);

    switch ($uri) {
        case 'auth/register':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            if (!isset($input['email']) || !isset($input['password']) || !isset($input['name'])) {
                throw new Exception('Email, password and name are required', 400);
            }

            // Validation
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format', 400);
            }

            if (strlen($input['password']) < 8) {
                throw new Exception('Password must be at least 8 characters', 400);
            }

            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$input['email']]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists', 409);
            }

            // Générer un token de vérification
            $verificationToken = bin2hex(random_bytes(32));

            // Créer l'utilisateur
            $stmt = $pdo->prepare('
                INSERT INTO users (
                    email, 
                    password, 
                    name, 
                    role,
                    is_verified,
                    is_active,
                    verification_token,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            
            $stmt->execute([
                $input['email'],
                password_hash($input['password'], PASSWORD_DEFAULT),
                $input['name'],
                'user',
                0, // is_verified
                1, // is_active
                $verificationToken
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'User registered successfully'
            ]);
            break;

        case 'auth/login':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            if (!isset($input['email']) || !isset($input['password'])) {
                throw new Exception('Email and password are required', 400);
            }

            // Récupérer l'utilisateur
            $stmt = $pdo->prepare('
                SELECT * FROM users 
                WHERE email = ? 
                AND is_active = 1
            ');
            $stmt->execute([$input['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($input['password'], $user['password'])) {
                throw new Exception('Invalid credentials', 401);
            }

            if (!$user['is_verified']) {
                throw new Exception('Please verify your email address', 403);
            }

            // Mettre à jour last_login
            $updateStmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $updateStmt->execute([$user['id']]);

            // Générer le token
            $token = generateJWT($user);
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'token' => $token,
                    'user' => formatUserData($user)
                ]
            ]);
            break;

        case 'auth/profile':
            debug_log('Processing auth/profile');
            // Vérifier le token
            $headers = getallheaders();
            $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            
            if (!preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                throw new Exception('No token provided', 401);
            }
            // Vérifier le token
            $headers = getallheaders();
            $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            
            if (!preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                throw new Exception('No token provided', 401);
            }
        
            try {
                $token = $matches[1];
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                
                debug_log('Token decoded', ['user_id' => $decoded->user->id]);
                
                switch ($method) {
                    case 'GET':
                        // Code existant pour GET
                        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
                        $stmt->execute([$decoded->user->id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
                        if (!$user) {
                            throw new Exception('User not found', 404);
                        }
        
                        echo json_encode([
                            'status' => 'success',
                            'data' => [
                                'user' => formatUserData($user)
                            ]
                        ]);
                        break;
        
                    case 'PATCH':
                        // Validation des données reçues
                        if (!$input) {
                            throw new Exception('Invalid input data', 400);
                        }
        
                        // Champs autorisés à être mis à jour
                        $allowedFields = ['name', 'email'];
                        $updates = [];
                        $params = [];
        
                        foreach ($allowedFields as $field) {
                            if (isset($input[$field])) {
                                $updates[] = "$field = ?";
                                $params[] = $input[$field];
                            }
                        }
        
                        if (empty($updates)) {
                            throw new Exception('No valid fields to update', 400);
                        }
        
                        // Ajouter l'ID utilisateur aux paramètres
                        $params[] = $decoded->user->id;
        
                        // Construire et exécuter la requête de mise à jour
                        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
        
                        // Récupérer les données mises à jour
                        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
                        $stmt->execute([$decoded->user->id]);
                        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Profile updated successfully',
                            'data' => [
                                'user' => formatUserData($updatedUser)
                            ]
                        ]);
                        
                        debug_log('Success response', $response);
                        echo json_encode($response);
                        break;
        
                    default:
                        throw new Exception('Method not allowed', 405);
                }
            } catch (Exception $e) {
                debug_log('Token error', ['message' => $e->getMessage()]);
                throw new Exception('Invalid token', 401);
                
            }
        break;
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    $code = $e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    debug_log('Error caught', [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}