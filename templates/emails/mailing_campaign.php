<?php
/**
 * localisation: templates/emails/mailing_campaign.php
 * Role: Email : campagne de publipostage (contacts mailing) — même layout
 *       que les notifications du site (bandeau vert, card, footer).
 * Variables attendues:
 *   - $headerTitle string  Titre du bandeau vert (= objet de l'email)
 *   - $bodyHtml    string  Corps HTML déjà personnalisé + échappé (nl2br)
 * Dépendances: templates/emails/_layout.php
 */
$headerTitle = $headerTitle ?? 'Partageons La Forêt';
$headerClass = '';
$headerLogo  = function_exists('getFullUrl')
    ? getFullUrl('assets/images/logoplfrond.png')
    : 'https://rando.partageonslaforet.be/assets/images/logoplfrond.png';
$dark        = true;
$docTitle    = $headerTitle . ' - ' . ($brand ?? 'Partageons la Forêt');
// Titre : texte échappé + domaine cliquable (couleur via .header h1 a)
$headerTitleSafe = str_replace(
    'rando.partageonslaforet.be',
    '<a href="https://rando.partageonslaforet.be">rando.partageonslaforet.be</a>',
    htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8')
);
ob_start();
?>
        <div class="card">
          <?= $bodyHtml ?? '' ?>
          <div style="margin-top:20px;padding-top:15px;border-top:1px solid #505050;text-align:center">
            <img src="<?= htmlspecialchars($headerLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Partageons la Forêt" style="height:48px">
          </div>
        </div>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
