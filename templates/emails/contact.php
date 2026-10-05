<?php
/**
 * localisation: templates/emails/contact.php
 * Role: Email : Contact
 * Usage: Corps du mail envoye
 * Dépendances: Aucune
 */
?>
<?php
$headerTitle = !empty($isCopy) ? 'Copie de votre message' : 'Nouveau message de contact';
$headerClass = '';
$title = 'Contact - ' . ($brand ?? 'Partageons la Forêt');
ob_start();
?>
        <div class="card">
          <div class="field"><span class="label">Nom :</span> <span class="value"><?= htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="field">
            <span class="label">Email :</span>
            <a class="value" href="mailto:<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></a>
          </div>
          <div class="field"><span class="label">Sujet :</span> <span class="value"><?= htmlspecialchars($subjectTxt ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="field">
            <span class="label">Message :</span>
            <div style="margin-top:5px;"><?= nl2br(htmlspecialchars($messageTxt ?? '', ENT_QUOTES, 'UTF-8')) ?></div>
          </div>
        </div>
        <p class="muted">Ceci est un envoi automatique.</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
