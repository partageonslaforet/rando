<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .content {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="content">
        <h2>Votre événement a été soumis pour validation</h2>
        
        <p>Bonjour,</p>
        
        <p>Votre événement "<?php echo htmlspecialchars($data['title']); ?>" a été soumis avec succès pour validation.</p>
        
        <p>Détails de l'événement :</p>
        <ul>
            <li>Date : <?php echo date('d/m/Y', strtotime($data['date'])); ?></li>
            <li>Horaires : <?php echo $data['start_time']; ?> à <?php echo $data['end_time']; ?></li>
            <li>Lieu : <?php echo htmlspecialchars($data['location']); ?></li>
        </ul>
        
        <p>Notre équipe va examiner votre demande dans les plus brefs délais. Vous recevrez une notification par email dès que votre événement sera validé et publié sur le site.</p>
        
        <div class="button-container">
            <a href="<?php echo APP_URL; ?>/events/<?php echo $data['eventId']; ?>" class="button">
                Voir mon événement
            </a>
        </div>
        
        <p>Si vous souhaitez modifier votre événement avant sa validation, vous pouvez le faire en vous connectant à votre compte.</p>
        
        <p>Merci de contribuer à notre communauté !</p>
    </div>
    
    <div class="footer">
        <p> <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - Tous droits réservés</p>
        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
    </div>
</body>
</html>