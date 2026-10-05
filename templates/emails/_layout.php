<?php
/**
 * localisation: templates/emails/_layout.php
 * Role: Email : Layout commun (style de référence = notification événement)
 * Variables attendues:
 *   - $headerTitle  string  Titre affiché dans le bandeau vert (h1)
 *   - $headerClass  string  '' (vert) | 'danger' (rouge)
 *   - $content      string  HTML du corps (généré par le template appelant via ob_start/ob_get_clean)
 *   - $title        string  (optionnel) <title> du document, défaut = $headerTitle
 * Dépendances: Aucune
 */
$headerTitle = $headerTitle ?? 'Partageons La Forêt';
$headerClass = $headerClass ?? '';
$docTitle    = $title ?? $headerTitle;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #2E8338; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .header.danger { background-color: #990047; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .card { background-color: white; border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin: 15px 0; }
        .card-title { font-size: 18px; font-weight: bold; color: #2E8338; margin-bottom: 10px; }
        .meta { font-size: 14px; color: #666; margin-bottom: 8px; }
        .field { margin-bottom: 10px; }
        .field .label { font-weight: bold; color: #555; }
        .field .value { color: #333; }
        .field .value a { color: #2E8338; }
        .info-box { background-color: #eef6ee; border-left: 4px solid #2E8338; padding: 10px 12px; margin: 15px 0; font-size: 14px; }
        .info-box ul { margin: 6px 0 0 0; padding-left: 18px; }
        .warn-box { background-color: #fdf0f6; border-left: 4px solid #990047; padding: 10px 12px; margin: 15px 0; font-size: 14px; color: #6d0033; }
        .button { display: inline-block; background-color: #2E8338; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
        .link-box { word-break: break-all; background-color: #f0f0f0; padding: 10px; border-radius: 5px; font-size: 13px; }
        .muted { font-size: 12px; color: #999; }
        .muted a { color: #999; }
        .footer { background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header<?= $headerClass !== '' ? ' ' . htmlspecialchars($headerClass, ENT_QUOTES, 'UTF-8') : '' ?>">
            <h1><?= htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>

        <div class="content">
            <?= $content ?? '' ?>
        </div>

        <div class="footer">
            <p>&copy; <?= date('Y') ?> Partageons La Forêt. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
