<?php
/**
 * localisation: templates/modals/forgot-password.php
 * Role: Modale : Forgot Password
 * Usage: Fenetre modale de mot de passe oublie
 * Dépendances: includes/csrf.php
 */
require_once __DIR__ . '/../../includes/csrf.php'; ?>
<!-- Modal de récupération de mot de passe -->
<div class="modal fade auth-modal" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="auth-icon" aria-hidden="true">
                    <i class="bi bi-envelope-paper"></i>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <h5 class="modal-title" id="forgotPasswordModalLabel">Réinitialiser votre mot de passe</h5>
                <p class="auth-intro">Indiquez votre adresse e-mail. Nous vous enverrons un lien de réinitialisation.</p>
                <div class="auth-alert auth-alert-success is-hidden" id="forgotSuccess"></div>
                <form id="forgotPasswordForm" method="post" action="api/auth/forgot-password.php">
                    <div class="mb-3">
                        <label for="forgotEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="forgotEmail" name="email" required placeholder="votre@email.com">
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-auth">Envoyer le lien</button>
                </form>
            </div>
            <div class="modal-footer">
                <p class="auth-footer"><a href="#" class="auth-link" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#loginModal">Retour à la connexion</a></p>
            </div>
        </div>
    </div>
</div>
