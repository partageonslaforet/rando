<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        <?php include 'styles.css'; ?>
    </style>
</head>
<body>
    <div class="header">
        <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="Partageons la Forêt" />
    </div>
    
    <div class="content">
        <?php echo $content; ?>
    </div>
    
    <div class="footer">
        <p>© <?php echo date('Y'); ?> Partageons la Forêt - Tous droits réservés</p>
    </div>
</body>
</html>