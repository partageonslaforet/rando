<?php
$manageUrl = htmlspecialchars($manage_url ?? '#', ENT_QUOTES, 'UTF-8');
$headerTitle = 'Gérer mes abonnements';
$headerClass = '';
$title = 'Gérer mes abonnements';
ob_start();
?>
        <p>Bonjour,</p>
        <p>Vous pouvez gérer vos abonnements (catégories et fréquence), ou vous désabonner, en cliquant sur le bouton ci-dessous&nbsp;:</p>
        <div style="text-align:center;">
            <a href="<?= $manageUrl ?>" class="button">Ouvrir la page de gestion</a>
        </div>
        <p>Ou copiez et collez ce lien dans votre navigateur&nbsp;:</p>
        <p class="link-box"><a href="<?= $manageUrl ?>" style="color:#2E8338;"><?= $manageUrl ?></a></p>
        <p>Ce lien expirera dans 24 heures.</p>
        <p class="muted">À bientôt sur Partageons La Forêt,<br>L’équipe</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
