<?php
/**
 * localisation: templates/emails/event_status_approved.php
 * Role: Email : Event Status Approved
 * Usage: Corps du mail envoye
 * Dépendances: Aucune
 */
?>
<?php
$eventTitle = $title ?? 'Votre événement'; // titre de l'événement (avant écrasement de $title)
$headerTitle = 'Événement approuvé !';
$headerClass = '';
$title = 'Événement approuvé - ' . ($brand ?? 'Partageons la Forêt');
ob_start();
?>
        <p>Bonjour,</p>
        <p>Bonne nouvelle ! Votre événement a été approuvé et est maintenant visible sur le site :</p>
        <div class="card">
          <div class="card-title"><?= htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="meta">📅 <?= htmlspecialchars($eventDate ?? 'date non précisée', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div style="text-align: center;">
          <a href="<?= htmlspecialchars($link ?? '#', ENT_QUOTES, 'UTF-8') ?>" class="button">Voir l'événement</a>
        </div>
        <p>Cordialement,<br>L'équipe <?= htmlspecialchars($brand ?? '', ENT_QUOTES, 'UTF-8') ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
