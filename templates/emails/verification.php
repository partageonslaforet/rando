<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de compte</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .container { background: #f9f9f9; border-radius: 5px; padding: 20px; margin: 20px 0; }
        .button { display: inline-block; padding: 10px 20px; background: #2c3e50; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bienvenue sur <?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?> !</h1>
        <p>Bonjour <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>,</p>
        <p>Merci de vous être inscrit. Pour activer votre compte, cliquez sur le bouton ci-dessous :</p>
        <p style="text-align: center;">
            <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" class="button" style="color: white;">Vérifier mon compte</a>
        </p>
        <p>Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>
        <p style="word-break: break-all;"><?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Note :</strong> ce lien est valable 24 heures et à usage unique.</p>
        <div class="footer">
            <p>Si vous n'avez pas créé de compte, vous pouvez ignorer cet e-mail.</p>
        </div>
    </div>
</body>
</html>
