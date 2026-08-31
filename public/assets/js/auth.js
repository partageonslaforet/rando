
// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du formulaire de connexion
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const errorDiv = document.getElementById('loginError');
            const formData = new FormData(event.target);

            try {
                submitBtn.disabled = true;

                const requestData = {
                    email: formData.get('email'),
                    password: formData.get('password'),
                    remember_me: formData.get('remember_me') === 'on',
                    csrf_token: formData.get('csrf_token')
                };

                const response = await fetch('/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Erreur de connexion');
                }

                const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                if (loginModal) {
                    loginModal.hide();
                }

                window.location.reload();

            } catch (error) {
                if (errorDiv) {
                    errorDiv.textContent = error.message;
                    errorDiv.style.display = 'block';
                }
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    // Gestion du formulaire d'inscription
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const errorDiv = document.getElementById('registerError');

            try {
                submitBtn.disabled = true;
                const formData = new FormData(event.target);

                if (formData.get('password') !== formData.get('password_confirm')) {
                    throw new Error('Les mots de passe ne correspondent pas');
                }

                if (formData.get('password').length < 8) {
                    throw new Error('Le mot de passe doit contenir au moins 8 caractères');
                }

                const response = await fetch('/api/auth/register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        name: formData.get('name'),
                        email: formData.get('email'),
                        password: formData.get('password'),
                        password_confirm: formData.get('password_confirm'),
                        csrf_token: formData.get('csrf_token')
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Une erreur est survenue lors de l\'inscription');
                }

                const registerModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
                if (registerModal) {
                    registerModal.hide();
                }

                const successDiv = document.getElementById('registerSuccess') || document.createElement('div');
                if (successDiv && successDiv.id === 'registerSuccess') {
                    successDiv.textContent = data.message;
                    successDiv.className = 'alert alert-success';
                    successDiv.style.display = 'block';
                } else {
                    alert(data.message);
                }
                registerForm.reset();

            } catch (error) {
                if (errorDiv) {
                    errorDiv.textContent = error.message;
                    errorDiv.style.display = 'block';
                }
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    // Gestion du formulaire de mot de passe oublié
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const formData = new FormData(event.target);

            try {
                submitBtn.disabled = true;

                const response = await fetch('/api/auth/forgot-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: formData.get('email'),
                        csrf_token: formData.get('csrf_token')
                    })
                });

                const data = await response.json();

                if (data.success) {
                    const successDiv = document.getElementById('forgotSuccess') || document.createElement('div');
                    if (successDiv && successDiv.id === 'forgotSuccess') {
                        successDiv.textContent = data.message;
                        successDiv.className = 'alert alert-success';
                        successDiv.style.display = 'block';
                    } else {
                        alert(data.message);
                    }
                    forgotPasswordForm.reset();
                } else {
                    throw new Error(data.message || 'Une erreur est survenue');
                }

            } catch (error) {
                const errorDiv = document.getElementById('forgotError') || document.createElement('div');
                if (errorDiv && errorDiv.id === 'forgotError') {
                    errorDiv.textContent = error.message;
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.style.display = 'block';
                } else {
                    alert(error.message);
                }
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    // Gestion du formulaire de réinitialisation de mot de passe
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const msg = document.getElementById('resetMessage');

            try {
                submitBtn.disabled = true;

                const form = event.target;
                const password = form.querySelector('#password').value;
                const passwordConfirm = form.querySelector('#password_confirm').value;

                if (password.length < 8) {
                    throw new Error('Le mot de passe doit contenir au moins 8 caractères');
                }

                if (password !== passwordConfirm) {
                    throw new Error('Les mots de passe ne correspondent pas');
                }

                const response = await fetch('/api/auth/reset-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: form.dataset.token,
                        email: form.dataset.email,
                        password: password,
                        password_confirm: passwordConfirm,
                        csrf_token: form.querySelector('[name="csrf_token"]').value
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Erreur');
                }

                if (msg) {
                    msg.className = 'alert alert-success';
                    msg.textContent = data.message;
                    msg.classList.remove('d-none');
                }

                setTimeout(() => window.location.href = '/', 2000);

            } catch (error) {
                if (msg) {
                    msg.className = 'alert alert-danger';
                    msg.textContent = error.message;
                    msg.classList.remove('d-none');
                }
            } finally {
                submitBtn.disabled = false;
            }
        });
    }
});

// Fonction de déconnexion
window.logout = async function() {
    try {
        console.log(' Tentative de déconnexion');
        const response = await fetch('/api/auth/logout.php');
        const data = await response.json();
        
        if (data.success) {
            console.log(' Déconnexion réussie');
            window.location.href = '/';
        } else {
            console.error(' Erreur lors de la déconnexion:', data.message);
            window.location.href = '/';
        }
    } catch (error) {
        console.error(' Erreur:', error);
        window.location.href = '/';
    }
};

// Fonctions utilitaires pour les messages
function showMessage(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.style.zIndex = '9999';
    
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function showError(message) {
    showMessage(message, 'danger');
}

function showSuccess(message) {
    showMessage(message, 'success');
}

console.log(' Fin du chargement de auth.js');
