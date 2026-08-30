<?php
require_once '../../config/database.php';
require_once '../../includes/flash_messages.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe - <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h2 class="text-center mb-4">Nouveau mot de passe</h2>
                        <div id="resetMessage" class="alert d-none"></div>
                        <form id="resetPasswordForm" data-token="<?php echo htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-email="<?php echo htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label for="password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            </div>
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Confirmer</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                            </div>
                            <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.target;
            const msg = document.getElementById('resetMessage');

            const password = form.querySelector('#password').value;
            const confirm = form.querySelector('#password_confirm').value;

            if (password.length < 8 || password !== confirm) {
                msg.className = 'alert alert-danger';
                msg.textContent = 'Le mot de passe doit faire au moins 8 caractères et les deux champs doivent correspondre.';
                msg.classList.remove('d-none');
                return;
            }

            try {
                const response = await fetch('/api/auth/reset-password.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        token: form.dataset.token,
                        email: form.dataset.email,
                        password: password,
                        password_confirm: confirm,
                        csrf_token: document.getElementById('csrf_token').value
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    msg.className = 'alert alert-success';
                    msg.textContent = data.message;
                    setTimeout(() => window.location.href = '/', 2000);
                } else {
                    msg.className = 'alert alert-danger';
                    msg.textContent = data.message || 'Erreur';
                }
            } catch (error) {
                msg.className = 'alert alert-danger';
                msg.textContent = 'Erreur de connexion au serveur';
            }
            msg.classList.remove('d-none');
        });
    </script>
</body>
</html>
