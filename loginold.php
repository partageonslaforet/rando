<?php
require_once 'config/database.php';
require_once 'includes/flash_messages.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Partageons La Forêt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet"> 
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 15px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .btn {
            border-radius: 25px;
            padding: 10px 20px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Connexion</h2>
                <form id="loginForm">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Se connecter</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#registerModal">
                            S'inscrire
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'templates/modals/register.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion du formulaire de connexion
            document.getElementById('loginForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = {
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value
                };

                try {
                    const response = await fetch('/templates/auth/login-process.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(formData)
                    });

                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        window.location.href = data.data.redirect || '/';
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
                    alertDiv.innerHTML = `
                        ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.getElementById('loginForm').insertAdjacentElement('beforebegin', alertDiv);
                }
            });

            // Gestion du formulaire d'inscription
            document.getElementById('registerForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = {
                    name: document.getElementById('username').value,
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value,
                    password_confirm: document.getElementById('password_confirm').value,
                    terms: document.getElementById('terms').checked
                };

                // Log des données avant envoi
                console.log('Données à envoyer:', formData);

                try {
                    const response = await fetch('/templates/auth/register.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(formData)
                    });

                    // Log de la réponse
                    console.log('Status:', response.status);
                    const data = await response.json();
                    console.log('Réponse:', data);
                    
                    if (data.status === 'success') {
                        // Log du succès
                        console.log('Inscription réussie');
                        
                        // Afficher le message de succès
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
                        alertDiv.innerHTML = `
                            ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.querySelector('.card-body').insertBefore(alertDiv, document.getElementById('loginForm'));
                        
                        // Réinitialiser le formulaire
                        this.reset();
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    // Log de l'erreur
                    console.error('Erreur:', error);
                    
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show mb-3';
                    alertDiv.innerHTML = `
                        ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.getElementById('registerForm').insertAdjacentElement('beforebegin', alertDiv);
                }
            });
        });
    </script>
</body>
</html>