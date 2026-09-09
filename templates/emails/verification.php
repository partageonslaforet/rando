<?php
/**
 * localisation: templates/emails/verification.php
 * Role: Email : Verification
 * Usage: Corps du mail envoye
 * Dépendances: Aucune
 */
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>Vérification de compte - <?= htmlspecialchars($brand ?? 'Partageons la Forêt', ENT_QUOTES, 'UTF-8') ?></title>
  <?php if (!empty($css)): ?><style><?= $css ?></style><?php endif; ?>
</head>
<body class="email-wrapper">
  <table role="presentation" class="email-container" cellspacing="0" cellpadding="0" border="0" align="center" width="100%">
    <tr>
      <td class="email-header">
        <h1><?= htmlspecialchars($brand ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="email-badge">Vérification de compte</p>
      </td>
    </tr>
    <tr>
      <td class="email-content">
        <p>Bonjour <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>,</p>
        <p>Merci de vous être inscrit sur <strong><?= htmlspecialchars($brand ?? '', ENT_QUOTES, 'UTF-8') ?></strong>. Pour activer votre compte et commencer à utiliser nos services, cliquez sur le bouton ci-dessous :</p>
        <p>
          <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" class="button">Vérifier mon compte</a>
        </p>
        <p>Si le bouton ne fonctionne pas, copiez et collez le lien suivant dans votre navigateur :</p>
        <p style="word-break: break-all; color: #6b7a6e;"><?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Note :</strong> ce lien est valable pendant 24 heures.</p>
      </td>
    </tr>
    <tr>
      <td class="email-footer">
        <p>Si vous n'avez pas créé de compte, vous pouvez ignorer cet e-mail.</p>
        <p>© <?= date('Y') ?> <?= htmlspecialchars($brand ?? '', ENT_QUOTES, 'UTF-8') ?></p>
      </td>
    </tr>
  </table>
</body>
</html>
