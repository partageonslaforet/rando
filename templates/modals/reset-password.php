<?php
/**
 * localisation: templates/modals/reset-password.php
 * Role: Modale : Reset Password
 * Usage: Fenetre modale de reinitialisation du mot de passe
 * Dépendances: includes/csrf.php
 */
require_once __DIR__ . '/../../includes/csrf.php';

// Si ce fichier est appelé directement (ancien lien), rediriger vers la home avec la modale
if (strpos($_SERVER['REQUEST_URI'] ?? '', 'reset-password.php') !== false) {
    $params = ['reset' => '1'];
    if (!empty($_GET['token'])) {
        $params['token'] = $_GET['token'];
    }
    if (!empty($_GET['email'])) {
        $params['email'] = $_GET['email'];
    }
    header('Location: /?' . http_build_query($params));
    exit;
}
?>
<!-- Modal de réinitialisation du mot de passe -->
<div class="modal fade auth-modal" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="auth-icon" aria-hidden="true">
                    <i class="bi bi-key"></i>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <h5 class="modal-title" id="resetPasswordModalLabel">Nouveau mot de passe</h5>
                <p class="auth-intro">Définissez votre nouveau mot de passe.</p>
                <div class="auth-alert auth-alert-success" id="resetMessage" style="display: none;"></div>
                <form id="resetPasswordForm" data-token="<?php echo htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-email="<?php echo htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label for="resetPassword" class="form-label">Nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="resetPassword" name="password" required minlength="8" placeholder="8 caractères minimum">
                            <button class="btn toggle-password" type="button" data-target="resetPassword" aria-label="Afficher le mot de passe">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="form-text">Minimum 8 caractères</small>
                    </div>
                    <div class="mb-3">
                        <label for="resetPasswordConfirm" class="form-label">Confirmer</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="resetPasswordConfirm" name="password_confirm" required minlength="8">
                            <button class="btn toggle-password" type="button" data-target="resetPasswordConfirm" aria-label="Afficher le mot de passe">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-auth w-100">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
