<?php
/**
 * localisation: templates/emails/reset.php
 * Role: Email : Reset
 * Usage: Corps du mail envoye
 * Dépendances: Aucune
 */
?>
<?php
$headerTitle = 'Réinitialisation de mot de passe';
$headerClass = '';
$title = 'Réinitialisation de mot de passe - ' . ($brand ?? 'Partageons la Forêt');
ob_start();
?>
        <p>Bonjour <?= htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8') ?>,</p>
        <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe :</p>
        <p>
          <a href="<?= htmlspecialchars($link ?? '', ENT_QUOTES, 'UTF-8') ?>" class="button">Réinitialiser le mot de passe</a>
        </p>
        <p>Si le bouton ne fonctionne pas, copiez et collez le lien suivant dans votre navigateur :</p>
        <p style="word-break: break-all; color: #6b7a6e;"><?= htmlspecialchars($link ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Note :</strong> ce lien est valable 1 heure et à usage unique.</p>
        <p class="muted">Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet e-mail.</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
