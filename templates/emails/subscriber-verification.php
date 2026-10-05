<?php
/**
 * Template: Email de vérification d'abonnement
 * Variables disponibles: $email, $verificationUrl (injectées par api/subscribers/register.php)
 */
/** @var string $verificationUrl */
$verificationUrl ??= '';
$headerTitle = 'Confirmation d’abonnement';
$headerClass = '';
$title = 'Confirmez votre abonnement';
ob_start();
?>
        <p>Bonjour,</p>

        <p>Merci de vous être abonné à nos notifications d'événements ! Pour confirmer votre adresse email et activer votre abonnement, veuillez cliquer sur le bouton ci-dessous :</p>

        <div style="text-align: center;">
            <a href="<?= htmlspecialchars($verificationUrl) ?>" class="button">Confirmer mon email</a>
        </div>

        <p>Ou copiez et collez ce lien dans votre navigateur :</p>
        <p class="link-box"><?= htmlspecialchars($verificationUrl) ?></p>

        <p>Ce lien expirera dans 24 heures.</p>

        <p class="muted">Si vous n'avez pas demandé cet abonnement, vous pouvez ignorer cet email.</p>

        <p>À bientôt sur Partageons La Forêt !<br>
        L'équipe</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
