<?php
/**
 * localisation: templates/emails/organizer-event-online.php
 * Role: Email de notification à l'organisateur que son événement est en ligne,
 *       avec lien de revendication tokenisé.
 * Variables attendues:
 *   - $eventTitle   string
 *   - $eventDate    string  (d/m/Y)
 *   - $eventPlace   string
 *   - $eventUrl     string  URL publique de l'événement
 *   - $claimUrl     string  URL /pages/claim-event.php?token=...
 *   - $brand        string
 * Dépendances: templates/emails/_layout.php
 */
$headerTitle = 'Votre événement est en ligne';
$headerClass = '';
$title = 'Votre événement est en ligne - ' . ($brand ?? 'Partageons la Forêt');
ob_start();
?>
        <p>Bonjour,</p>
        <p>Votre événement <strong><?= htmlspecialchars($eventTitle ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
           a été référencé sur <strong>rando.partageonslaforet.be</strong>,
           le site qui regroupe les randonnées et activités nature en Belgique :</p>

        <div class="card">
          <div class="card-title"><?= htmlspecialchars($eventTitle ?? '', ENT_QUOTES, 'UTF-8') ?></div>
          <div class="meta">📅 <?= htmlspecialchars($eventDate ?? 'date non précisée', ENT_QUOTES, 'UTF-8') ?></div>
          <?php if (!empty($eventPlace)): ?>
            <div class="meta">📍 <?= htmlspecialchars($eventPlace, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
        </div>

        <div style="text-align: center;">
          <a href="<?= htmlspecialchars($eventUrl ?? '#', ENT_QUOTES, 'UTF-8') ?>" class="button">Voir l'événement</a>
        </div>

        <p style="font-size: 0.9em; color: #666;"><em>rando.partageonslaforet.be est une émanation de
           partageonslaforet.be, le site qui permet de consulter les dates de battues et de chasse
           en Wallonie pour planifier vos balades en toute sécurité.</em></p>

        <p><strong>Cet événement est le vôtre ?</strong> Revendiquez-le gratuitement pour pouvoir
           le modifier, le compléter et suivre ses vues :</p>

        <div style="text-align: center;">
          <a href="<?= htmlspecialchars($claimUrl ?? '#', ENT_QUOTES, 'UTF-8') ?>" class="button">Revendiquer cet événement</a>
        </div>

        <p>Une information incorrecte ? Répondez simplement à cet e-mail.</p>

        <p>Bien cordialement,<br>L'équipe <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
