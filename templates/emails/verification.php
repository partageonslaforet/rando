<?php
/**
 * localisation: templates/emails/verification.php
 * Role: Email : Verification
 * Usage: Corps du mail envoye
 * Dépendances: Aucune
 */
?>
<?php
$headerTitle = 'Vérification de votre compte';
$headerClass = '';
$title = 'Vérification de compte - ' . ($brand ?? 'Partageons la Forêt');
ob_start();
?>
        <p>Bonjour <?= htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8') ?>,</p>
        <p>Merci de vous être inscrit sur <strong><?= htmlspecialchars($brand ?? '', ENT_QUOTES, 'UTF-8') ?></strong>. Pour activer votre compte et commencer à utiliser nos services, cliquez sur le bouton ci-dessous :</p>
        <p>
          <a href="<?= htmlspecialchars($link ?? '', ENT_QUOTES, 'UTF-8') ?>" class="button">Vérifier mon compte</a>
        </p>
        <p>Si le bouton ne fonctionne pas, copiez et collez le lien suivant dans votre navigateur :</p>
        <p style="word-break: break-all; color: #6b7a6e;"><?= htmlspecialchars($link ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Note :</strong> ce lien est valable pendant 24 heures.</p>
        <p class="muted">Si vous n'avez pas créé de compte, vous pouvez ignorer cet e-mail.</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
