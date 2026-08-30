<?php
require_once '../../includes/csrf.php';
$pageTitle = 'Réinitialisation du mot de passe';
require_once '../../templates/components/header/header.php';
require_once '../../templates/components/footer/footer.php';
render_header();
?>
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
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Confirmer</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_confirm">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-connexion w-100">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
render_footer();
require_once '../../includes/scripts.php';
?>
