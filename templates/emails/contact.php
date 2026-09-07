<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>Contact - <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></title>
  <?php if (!empty($css)): ?>
  <style><?= $css ?></style>
  <?php endif; ?>
</head>
<body class="email-wrapper">
  <table role="presentation" class="email-container" cellspacing="0" cellpadding="0" border="0" align="center" width="100%">
    <tr>
      <td class="email-header">
        <h1><?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></h1>
        
        <?php if (!empty($isCopy)): ?>
          <p class="email-badge">Copie de votre message</p>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td class="email-content">
        <div class="email-field">
          <span class="email-label">Nom:</span>
          <span class="email-value"><?= htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="email-field">
          <span class="email-label">Email:</span>
          <a class="email-value" href="mailto:<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>
          </a>
        </div>
        <div class="email-field">
          <span class="email-label">Sujet:</span>
          <span class="email-value"><?= htmlspecialchars($subjectTxt ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="email-field">
          <span class="email-label">Message:</span>
          <div class="email-message">
            <?= nl2br(htmlspecialchars($messageTxt ?? '', ENT_QUOTES, 'UTF-8')) ?>
          </div>
        </div>
      </td>
    </tr>
    <tr>
      <td class="email-footer">
        <p>© <?= date('Y') ?> <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?> — Ceci est un envoi automatique.</p>
      </td>
    </tr>
  </table>
</body>
</html>
