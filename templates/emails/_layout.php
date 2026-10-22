<?php
/**
 * localisation: templates/emails/_layout.php
 * Role: Email : Layout commun (style de référence = notification événement)
 * Variables attendues:
 *   - $headerTitle  string  Titre affiché dans le bandeau vert (h1)
 *   - $headerClass  string  '' (vert) | 'danger' (rouge)
 *   - $content      string  HTML du corps (généré par le template appelant via ob_start/ob_get_clean)
 *   - $title        string  (optionnel) <title> du document, défaut = $headerTitle
 *   - $headerLogo   string  (optionnel) URL absolue du logo affiché dans le bandeau, au-dessus du titre
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
        .header { background-color: #c8e6c9; color: #3d3d3d; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .header.danger { background-color: #990047; color: white; }
        .header.danger h1 a { color: white; }
        .header h1 { margin: 0; font-size: 24px; }
        .header h1 a { color: #2E8338; }
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
        <?php if (!empty($dark)): ?>
        body { background-color: #2b2b2b; color: #e6e6e6; }
        .content { background-color: #333333; border-color: #444444; }
        .card { background-color: #3d3d3d; border-color: #505050; color: #e6e6e6; }
        .card a, .content a { color: #8fc3ff; }
        .card-title { color: #7cc47f; }
        .muted, .muted a { color: #888888; }
        .footer { background-color: #2b2b2b; color: #999999; }
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="container">
        <div class="header<?= $headerClass !== '' ? ' ' . htmlspecialchars($headerClass, ENT_QUOTES, 'UTF-8') : '' ?>">
            <?php if (!empty($headerLogo)): ?>
            <img src="<?= htmlspecialchars($headerLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Partageons la Forêt" style="height:64px;display:block;margin:0 auto 10px;">
            <?php endif; ?>
            <h1><?= $headerTitleSafe ?? htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8') ?></h1>
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
