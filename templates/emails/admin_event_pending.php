<?php
/**
 * localisation: templates/emails/admin_event_pending.php
 * Role: Email : Admin Event Pending
 * Usage: Corps du mail envoye
 * Dépendances: Aucune
 */
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>Nouvel Evenement à valider - <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></title>
  <?php if (!empty($css)): ?><style><?= $css ?></style><?php endif; ?>
</head>
<body class="email-wrapper">
  <table role="presentation" class="email-container" cellspacing="0" cellpadding="0" border="0" align="center" width="100%">
    <tr>
      <td class="email-header">
        <h1><?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="email-badge">Notification modération</p>
      </td>
    </tr>
    <tr>
      <td class="email-content">
        <div class="email-field">
          <span class="email-label">Titre:</span>
          <span class="email-value"><?= htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="email-field">
          <span class="email-label">Date de l’événement:</span>
          <span class="email-value"><?= htmlspecialchars($eventDate ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="email-field">
          <span class="email-label">Publié le:</span>
          <span class="email-value"><?= htmlspecialchars($publishedAt ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="email-field">
          <span class="email-label">Organisation:</span>
          <span class="email-value"><?= htmlspecialchars($organisation ?? 'Non spécifiée', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="email-field">
          <span class="email-label">Lien admin:</span>
          <a class="email-value" href="<?= htmlspecialchars($adminLink ?? '#', ENT_QUOTES, 'UTF-8') ?>">Ouvrir pour valider</a>
        </div>
      </td>
    </tr>
    <tr>
      <td class="email-footer">
        <p>© <?= date('Y') ?> <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?> — Notification automatique.</p>
      </td>
    </tr>
  </table>
</body>
</html>
