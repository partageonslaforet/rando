<?php
/**
 * localisation: templates/modals/register.php
 * Role: Modale : Register
 * Usage: Fenetre modale d inscription
 * Dépendances: includes/csrf.php
 */
require_once __DIR__ . '/../../includes/csrf.php'; ?>
<!-- Modal d'inscription -->
<div class="modal fade auth-modal" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="auth-icon" aria-hidden="true">
                    <i class="bi bi-person-plus"></i>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <h5 class="modal-title" id="registerModalLabel">Créer un compte</h5>
                <p class="auth-intro">Rejoignez la communauté et publiez vos sorties en quelques clics.</p>
                <div class="auth-alert auth-alert-danger is-hidden" id="registerError"></div>
                <form id="registerForm" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="username" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="registerEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="8 caractères minimum">
                            <button class="btn toggle-password" type="button" data-target="password" aria-label="Afficher le mot de passe">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="form-text">Minimum 8 caractères</small>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                            <button class="btn toggle-password" type="button" data-target="password_confirm" aria-label="Afficher le mot de passe">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-auth">Créer mon compte</button>
                </form>
                <!-- Étape 2 : succès + incitation profil organisateur (affiché par auth.js) -->
                <div id="registerSuccess" class="is-hidden">
                    <div class="auth-alert auth-alert-success" role="status">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <span id="registerSuccessMessage">Compte créé ! Un e-mail de confirmation vient de vous être envoyé.</span>
                    </div>
                    <p class="text-muted small mt-3 mb-3">
                        Une fois votre e-mail confirmé et votre compte connecté, pensez à créer votre
                        <strong>profil organisateur</strong> : il affiche le nom et le logo de votre
                        club ou association sur vos événements.
                    </p>
                    <div class="d-grid">
                        <button type="button" class="btn btn-auth" data-bs-dismiss="modal">Compris, je vais vérifier mes e-mails</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <p class="auth-footer">Déjà inscrit ? <a href="#" class="auth-link" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#loginModal">Se connecter</a></p>
            </div>
        </div>
    </div>
</div>
