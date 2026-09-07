<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>Événement refusé - <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></title>
  <?php if (!empty($css)): ?><style><?= $css ?></style><?php endif; ?>
</head>
<body class="email-wrapper">
  <table role="presentation" class="email-container" cellspacing="0" cellpadding="0" border="0" align="center" width="100%">
    <tr>
      <td class="email-header">
        <h1><?= htmlspecialchars($brand ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="email-badge">Événement refusé</p>
      </td>
    </tr>
    <tr>
      <td class="email-content">
        <p>Bonjour,</p>
        <p>Votre événement <strong><?= htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8') ?></strong> n’a pas été validé.</p>
        <div class="email-field">
          <span class="email-label">Date de l’événement:</span>
          <span class="email-value"><?= htmlspecialchars($eventDate ?? 'date non précisée', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php if (!empty($reason)): ?>
        <div class="email-field">
          <span class="email-label">Raison:</span>
          <span class="email-value"><?= nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')) ?></span>
        </div>
        <?php endif; ?>
        <p>Vous pouvez mettre à jour votre événement puis le soumettre à nouveau.</p>
        <p><a href="<?= htmlspecialchars($myEventsLink ?? '/pages/user/my-events.php', ENT_QUOTES, 'UTF-8') ?>" class="btn-primary">Gérer mes événements</a></p>
        <p>Cordialement,<br>L'équipe <?= htmlspecialchars($brand ?? '', ENT_QUOTES, 'UTF-8') ?></p>
      </td>
    </tr>
    <tr>
      <td class="email-footer">
        <p>© <?= date('Y') ?> <?= htmlspecialchars($brand ?? '', ENT_QUOTES, 'UTF-8') ?></p>
      </td>
    </tr>
  </table>
</body>
</html>
