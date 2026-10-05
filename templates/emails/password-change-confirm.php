<?php
/**
 * localisation: templates/emails/password-change-confirm.php
 * Role: Email : Confirmation d'un changement de mot de passe demandé depuis le profil
 * Variables disponibles: $link (lien de confirmation, injecté par Mailer::sendPasswordChangeConfirmEmail)
 * Dépendances: templates/emails/_layout.php
 */
$headerTitle = 'Confirmation de changement de mot de passe';
$headerClass = '';
$title = 'Confirmation de changement de mot de passe';
ob_start();
?>
        <p>Bonjour,</p>
        <p>Une demande de changement de mot de passe a été effectuée pour votre compte.</p>
        <p>Pour confirmer ce changement, veuillez cliquer sur le bouton ci-dessous :</p>
        <div style="text-align: center;">
            <a href="<?= htmlspecialchars($link ?? '#', ENT_QUOTES, 'UTF-8') ?>" class="button">Confirmer le changement</a>
        </div>
        <p>Ou copiez et collez ce lien dans votre navigateur :</p>
        <p class="link-box"><?= htmlspecialchars($link ?? '#', ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Attention :</strong> ce lien expirera dans 1 heure.</p>
        <p class="muted">Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email en toute sécurité.</p>
        <p>Cordialement,<br>L'équipe Partageons La Forêt</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
