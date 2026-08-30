<?php
// Démarrer le buffer de sortie
ob_start();

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 1728000');
header('Content-Type: application/json; charset=UTF-8');

// Gérer la requête OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit();
}

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit();
}

try {
    // Lire les données
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Données invalides');
    }

    // Valider les données
    if (empty($input['name']) || empty($input['email'])) {
        throw new Exception('Nom et email requis');
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email invalide');
    }

    // Charger la configuration
    require_once '/home/cool5792/rando.partageonslaforet.be/includes/config.php';

    // Connexion à la base de données
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Vérifier si l'email existe déjà pour un autre utilisateur
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$input['email'], $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        throw new Exception('Cet email est déjà utilisé');
    }

    // Préparer la requête de mise à jour
    if (!empty($input['password'])) {
        // Mise à jour avec nouveau mot de passe
        $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?');
        $stmt->execute([
            $input['name'],
            $input['email'],
            password_hash($input['password'], PASSWORD_DEFAULT),
            $_SESSION['user_id']
        ]);
    } else {
        // Mise à jour sans mot de passe
        $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
        $stmt->execute([
            $input['name'],
            $input['email'],
            $_SESSION['user_id']
        ]);
    }

    // Récupérer les données mises à jour
    $stmt = $pdo->prepare('SELECT id, email, name, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    echo json_encode([
        'status' => 'success',
        'message' => 'Profil mis à jour avec succès',
        'data' => ['user' => $user]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Vider le buffer de sortie
ob_end_flush();
