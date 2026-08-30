<?php
require_once '../../config/database.php';
require_once '../../includes/flash_messages.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation des données
    if (empty($input['email']) || empty($input['password']) || empty($input['name'])) {
        throw new Exception('Tous les champs sont obligatoires');
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email invalide');
    }

    if (strlen($input['password']) < 8) {
        throw new Exception('Le mot de passe doit contenir au moins 8 caractères');
    }

    $db = getConnection();

    try {
        // Vérifier si l'email existe déjà
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) {
            throw new Exception('Cette adresse email est déjà utilisée');
        }

        // Générer le token de vérification
        $verificationToken = bin2hex(random_bytes(32));
        
        // Hash du mot de passe
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

        // Insertion de l'utilisateur
        $stmt = $db->prepare('
            INSERT INTO users (email, password, name, verification_token, is_verified, role, created_at) 
            VALUES (?, ?, ?, ?, 0, "user", NOW())
        ');
        
        $stmt->execute([
            $input['email'],
            $hashedPassword,
            $input['name'],
            $verificationToken
        ]);

    } catch (PDOException $e) {
        // Si c'est une erreur de duplicate entry
        if ($e->getCode() == 23000) {
            throw new Exception('Cette adresse email est déjà utilisée');
        }
        // Pour les autres erreurs SQL
        throw new Exception('Une erreur est survenue lors de l\'inscription');
    }

    // Envoyer l'email de vérification
    $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/templates/auth/verify.php?token=" . $verificationToken . "&email=" . urlencode($input['email']);
    
    $to = $input['email'];
    $subject = "Vérification de votre compte - Partageons La Forêt";
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .container {
                background-color: #f9f9f9;
                border-radius: 5px;
                padding: 20px;
                margin: 20px 0;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .header h1 {
                color: #2c3e50;
                margin: 0;
                padding: 0;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #2c3e50;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                font-size: 0.9em;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Bienvenue sur Partageons La Forêt !</h1>
            </div>
            <p>Bonjour {$input['name']},</p>
            <p>Merci de vous être inscrit sur Partageons La Forêt. Pour activer votre compte et commencer à utiliser nos services, veuillez cliquer sur le bouton ci-dessous :</p>
            <p style='text-align: center;'>
                <a href='{$verificationLink}' class='button' style='color: white;'>Vérifier mon compte</a>
            </p>
            <p>Si le bouton ne fonctionne pas, vous pouvez copier et coller le lien suivant dans votre navigateur :</p>
            <p style='word-break: break-all;'>{$verificationLink}</p>
            <p><strong>Note :</strong> Ce lien est valable pendant 24 heures.</p>
            <div class='footer'>
                <p>À bientôt sur Partageons La Forêt !</p>
                <p>Si vous n'avez pas créé de compte, vous pouvez ignorer cet email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: Partageons La Forêt <noreply@partageonslaforet.be>',
        'Reply-To: noreply@partageonslaforet.be',
        'X-Mailer: PHP/' . phpversion()
    ];

    mail($to, $subject, $message, implode("\r\n", $headers));

    echo json_encode([
        'success' => true,
        'message' => 'Inscription réussie ! Veuillez vérifier vos emails pour activer votre compte.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
