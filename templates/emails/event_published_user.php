<?php
/**
 * localisation: templates/emails/event_published_user.php
 * Role: Email : Event Published User
 * Usage: Corps du mail envoye
 * Dépendances: Aucune
 */
?>
<?php
$eventTitle = $title ?? 'Votre événement'; // titre de l'événement (avant écrasement de $title)
$headerTitle = 'Événement soumis !';
$headerClass = '';
$title = 'Confirmation de soumission - ' . ($brand ?? 'Partageons la Forêt');
ob_start();
?>
        <p>Bonjour,</p>
        <p>Votre événement a été soumis avec succès :</p>
        <div class="card">
          <div class="card-title"><?= htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="meta">📅 <?= htmlspecialchars($eventDate ?? 'date non précisée', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <p>Il sera visible après validation par un modérateur.</p>
        <div style="text-align: center;">
          <a href="<?= htmlspecialchars($link ?? '#', ENT_QUOTES, 'UTF-8') ?>" class="button">Voir l'événement</a>
        </div>
        <p>Cordialement,<br>L'équipe <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
