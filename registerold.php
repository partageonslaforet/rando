<?php
require_once 'includes/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

include 'includes/header.php';
?>

<main class="auth-page">
    <div class="container">
        <div class="auth-form-container">
            <h2>Inscription</h2>
            <form class="auth-form" method="POST" action="api/auth.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" name="action" value="register" class="submit-btn">S'inscrire</button>
            </form>
            <p class="auth-link">Déjà un compte ? <a href="login.php">Se connecter</a></p>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>