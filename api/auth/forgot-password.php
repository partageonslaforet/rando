<?php
require_once '../includes/config.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    header('Location: /');
    exit;
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Mot de passe oublié</h2>

                    <!-- Messages d'erreur/succès -->
                    <div id="resetMessage" class="alert d-none"></div>

                    <form id="resetForm" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="form-text">
                                Nous vous enverrons un lien pour réinitialiser votre mot de passe.
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">Réinitialiser le mot de passe</button>
                            <a href="/" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#loginModal">
                                Retour à la connexion
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resetForm = document.querySelector('#resetForm');
    const resetMessage = document.querySelector('#resetMessage');

    resetForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        try {
            const response = await fetch('/api/auth.php/reset-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: document.querySelector('#email').value
                })
            });

            const data = await response.json();

            if (response.ok) {
                resetMessage.className = 'alert alert-success';
                resetMessage.textContent = 'Si cette adresse email existe dans notre base de données, ' +
                                        'vous recevrez un email avec les instructions pour réinitialiser ' +
                                        'votre mot de passe.';
                resetForm.reset();
            } else {
                resetMessage.className = 'alert alert-danger';
                resetMessage.textContent = data.message || 'Une erreur est survenue';
            }
        } catch (error) {
            resetMessage.className = 'alert alert-danger';
            resetMessage.textContent = 'Erreur de connexion au serveur';
        }

        resetMessage.classList.remove('d-none');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
