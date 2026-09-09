<?php
/**
 * localisation: templates/emails/event_published_user.php
 * Role: Email : Event Published User
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
  <title>Confirmation de soumission - <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></title>
  <?php if (!empty($css)): ?><style><?= $css ?></style><?php endif; ?>
</head>
<body class="email-wrapper">
  <table role="presentation" class="email-container" cellspacing="0" cellpadding="0" border="0" align="center" width="100%">
    <tr>
      <td class="email-header">
        <h1><?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="email-badge">Confirmation de soumission</p>
      </td>
    </tr>
    <tr>
      <td class="email-content">
        <p>Bonjour,</p>
        <p>Votre événement <strong><?= htmlspecialchars($title ?? 'Votre événement', ENT_QUOTES, 'UTF-8') ?></strong> a été soumis avec succès.</p>
        <div class="email-field">
          <span class="email-label">Date de l’événement:</span>
          <span class="email-value"><?= htmlspecialchars($eventDate ?? 'date non précisée', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <p>Il sera visible après validation par un modérateur.</p>
        <p>
          <a href="<?= htmlspecialchars($link ?? '#', ENT_QUOTES, 'UTF-8') ?>" class="btn-primary">Voir l'événement</a>
        </p>
        <p>Cordialement,<br>L'équipe <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></p>
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
