<?php require_once __DIR__ . '/../../includes/csrf.php'; ?>
<!-- Modal de connexion -->
<div class="modal fade auth-modal" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="auth-icon" aria-hidden="true">
                    <i class="bi bi-arrow-up-right"></i>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <h5 class="modal-title" id="loginModalLabel">Connexion</h5>
                <p class="auth-intro">Connectez-vous pour gérer vos événements et suivre leur validation.</p>
                <div class="auth-alert auth-alert-danger" id="loginError" style="display: none;"></div>
                <form id="loginForm" method="post">
                    <div class="mb-3">
                        <label for="loginEmail" class="form-label">Adresse e-mail</label>
                        <input type="email" class="form-control" id="loginEmail" name="email" required placeholder="vous@exemple.be">
                    </div>
                    <div class="mb-3">
                        <label for="loginPassword" class="form-label">Mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="loginPassword" name="password" required placeholder="••••••••">
                            <button class="btn toggle-password" type="button" data-target="loginPassword" aria-label="Afficher le mot de passe">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="auth-remember-row">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">Se souvenir de moi</label>
                        </div>
                        <a href="#" class="auth-link" onclick="showForgotPasswordModal(); return false;">Mot de passe oublié ?</a>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-auth">Se connecter</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <div class="auth-separator"><span>ou</span></div>
                <p class="auth-footer">
                    Vous n'avez pas encore de compte ?
                    <!-- <i class="bi bi-arrow-down-circle-fill" aria-hidden="true" style="color: #1a1a1a; font-size: 1rem; vertical-align: middle; margin: 0 0.25rem;"></i> -->
                    <a href="#" class="auth-link" onclick="showRegisterModal(); return false;">Créer un compte</a>
                </p>
            </div>
        </div>
    </div>
</div>
