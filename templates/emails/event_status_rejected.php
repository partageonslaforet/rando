<?php
/**
 * localisation: templates/emails/event_status_rejected.php
 * Role: Email : Event Status Rejected
 * Usage: Corps du mail envoye
 * Dépendances: Aucune
 */
?>
<?php
$eventTitle = $title ?? 'Votre événement'; // titre de l'événement (avant écrasement de $title)
$headerTitle = 'Événement refusé';
$headerClass = 'danger';
$title = 'Événement refusé - ' . ($brand ?? 'Partageons la Forêt');
ob_start();
?>
        <p>Bonjour,</p>
        <p>Votre événement n’a pas été validé par la modération :</p>
        <div class="card">
          <div class="card-title"><?= htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="meta">📅 <?= htmlspecialchars($eventDate ?? 'date non précisée', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php if (!empty($reason)): ?>
        <div class="warn-box">
          <strong>Raison :</strong><br><?= nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')) ?>
        </div>
        <?php endif; ?>
        <p>Vous pouvez mettre à jour votre événement puis le soumettre à nouveau.</p>
        <div style="text-align: center;">
          <a href="<?= htmlspecialchars($myEventsLink ?? '/pages/user/my-events.php', ENT_QUOTES, 'UTF-8') ?>" class="button">Gérer mes événements</a>
        </div>
        <p>Cordialement,<br>L'équipe <?= htmlspecialchars($brand ?? '', ENT_QUOTES, 'UTF-8') ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
