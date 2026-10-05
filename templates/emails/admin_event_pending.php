<?php
/**
 * localisation: templates/emails/admin_event_pending.php
 * Role: Email : Admin Event Pending
 * Usage: Corps du mail envoye
 * Dépendances: Aucune
 */
?>
<?php
$eventTitle = $title ?? 'Événement'; // titre de l'événement (avant écrasement de $title)
$headerTitle = 'Nouvel événement à valider';
$headerClass = '';
$docTitle = 'Nouvel événement à valider - ' . ($brand ?? 'Partageons la Forêt');
ob_start();
?>
        <div class="card">
          <div class="card-title"><?= htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="field"><span class="label">Date de l’événement :</span> <span class="value"><?= htmlspecialchars($eventDate ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="field"><span class="label">Publié le :</span> <span class="value"><?= htmlspecialchars($publishedAt ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
          <div class="field"><span class="label">Organisation :</span> <span class="value"><?= htmlspecialchars($organisation ?? 'Non spécifiée', ENT_QUOTES, 'UTF-8') ?></span></div>
        </div>
        <div style="text-align: center;">
          <a href="<?= htmlspecialchars($adminLink ?? '#', ENT_QUOTES, 'UTF-8') ?>" class="button">Ouvrir pour valider</a>
        </div>
        <p class="muted">Notification automatique de modération.</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
