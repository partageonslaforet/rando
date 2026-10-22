<?php
/**
 * localisation: templates/pages/404.php
 * Role: Page d'erreur 404 standalone (sans header/footer du site).
 * Usage: incluse par index.php pour toute route inconnue.
 * Dépendances: public/assets/css/pages/404.css
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>404 - Page introuvable | Partageons La Forêt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/pages/404.css?v=1">
</head>
<body class="page-404">
    <div class="error-card">
        <i class="bi bi-signpost-split error-icon" aria-hidden="true"></i>
        <h1>404</h1>
        <p class="error-text">Cette page n'existe pas ou plus.</p>
        <div class="error-actions">
            <a href="/" class="btn-404 btn-404-primary">
                <i class="bi bi-house"></i> Retour à l'accueil
            </a>
        </div>
    </div>
</body>
</html>
