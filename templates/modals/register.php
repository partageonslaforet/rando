<?php require_once __DIR__ . '/../../includes/csrf.php'; ?>
<!-- Modal d'inscription -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="registerModalLabel">Inscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" id="registerError" style="display: none;"></div>
                <form id="registerForm">
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
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">Minimum 8 caractères</small>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_confirm">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <!--<div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">J'accepte les <a href="#" target="_blank">conditions d'utilisation</a></label>
                    </div>-->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-secondary w-100">S'inscrire</button>
                </form>
            </div>
            <div class="modal-footer">
                <div class="text-center mb-2">
                    <p>Déjà inscrit ? <a href="#" class="text-decoration-none modal-link" data-bs-target="#loginModal">Se connecter</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
