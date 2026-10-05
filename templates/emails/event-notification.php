<?php
/**
 * Template: Email de notification d'un événement (publication, mise à jour, annulation)
 * Variables disponibles: $event, $eventUrl, $unsubscribeUrl, $type ('new'|'update'|'cancelled')
 */
?>
<?php
$type = $type ?? 'new';
$headerTitle = $type === 'cancelled' ? 'Événement annulé' : ($type === 'update' ? 'Événement mis à jour !' : 'Nouvel événement publié !');
$headerClass = $type === 'cancelled' ? 'danger' : '';
$title = $headerTitle . ' : ' . ($event['title'] ?? 'Événement');
ob_start();
?>
        <p>Bonjour,</p>

        <p><?= $type === 'cancelled'
            ? 'Un événement correspondant à vos intérêts a été annulé :'
            : ($type === 'update'
                ? 'Un événement correspondant à vos intérêts a été mis à jour :'
                : 'Un nouvel événement correspondant à vos intérêts a été publié :') ?></p>

        <?php if ($type === 'update' && !empty($changedFields)): ?>
            <div class="info-box">
                <strong>Éléments modifiés :</strong>
                <ul>
                    <?php foreach ($changedFields as $field): ?>
                        <li><?= htmlspecialchars($field) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title"><?= htmlspecialchars($event['title'] ?? 'Événement') ?></div>

            <?php if ($type === 'cancelled' && !empty($event['cancellation_reason'])): ?>
                <div class="warn-box">
                    <strong>Raison de l'annulation :</strong> <?= htmlspecialchars($event['cancellation_reason']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($event['date'])): ?>
                <div class="meta">📅 <?= htmlspecialchars(date('d/m/Y', strtotime($event['date']))) ?></div>
            <?php endif; ?>

            <?php if (!empty($event['start_time'])): ?>
                <div class="meta">🕐 <?= htmlspecialchars($event['start_time']) ?></div>
            <?php endif; ?>

            <?php if (!empty($event['location'])): ?>
                <div class="meta">📍 <?= htmlspecialchars($event['location']) ?></div>
            <?php endif; ?>

            <?php if (!empty($event['description'])): ?>
                <p style="margin-top: 10px; font-size: 14px;">
                    <?= nl2br(htmlspecialchars(substr($event['description'], 0, 200))) ?>
                    <?php if (strlen($event['description']) > 200): ?>...<?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <div style="text-align: center;">
            <a href="<?= htmlspecialchars($eventUrl ?? '#') ?>" class="button"><?= $type === 'cancelled' ? 'Voir les détails' : 'Voir l\'événement' ?></a>
        </div>

        <p class="muted" style="margin-top: 20px;">
            Vous recevez cet email car vous êtes abonné aux notifications d'événements pour cette catégorie.
            <a href="<?= htmlspecialchars($unsubscribeUrl ?? '#') ?>">Gérer vos préférences</a>
        </p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
