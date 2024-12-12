<?php
require_once 'includes/db.php';
session_start();

$token = $_GET['token'] ?? '';
$message = '';
$success = false;

if ($token) {
    try {
        $stmt = $pdo->prepare('
            SELECT id, email 
            FROM users 
            WHERE verification_token = ? AND is_verified = 0
        ');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Mettre à jour l'utilisateur
            $stmt = $pdo->prepare('
                UPDATE users 
                SET is_verified = 1, 
                    verification_token = NULL 
                WHERE id = ?
            ');
            $stmt->execute([$user['id']]);

            $success = true;
            $message = 'Votre compte a été vérifié avec succès ! Vous pouvez maintenant vous connecter.';
        } else {
            $message = 'Token de vérification invalide ou déjà utilisé.';
        }
    } catch (PDOException $e) {
        $message = 'Une erreur est survenue lors de la vérification.';
        error_log($e->getMessage());
    }
} else {
    $message = 'Token de vérification manquant.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification du compte - PartagonsLaForet</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            background-color: #f3f4f6;
        }
        .container {
            max-width: 500px;
            width: 100%;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .icon {
            width: 64px;
            height: 64px;
            margin-bottom: 24px;
        }
        h1 {
            color: #990047;
            margin-bottom: 16px;
        }
        .message {
            margin-bottom: 24px;
            color: <?php echo $success ? '#059669' : '#dc2626'; ?>;
        }
        .button {
            display: inline-block;
            background-color: #990047;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #800039;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/assets/<?php echo $success ? 'check' : 'error'; ?>.svg" alt="Icon" class="icon">
        <h1>Vérification du compte</h1>
        <p class="message"><?php echo $message; ?></p>
        <a href="/" class="button">Retour à l'accueil</a>
    </div>
</body>
</html>