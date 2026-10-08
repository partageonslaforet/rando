
/**
 * Fichier: /assets/js/core/auth.js
 * Rôle: Gestion client de l’authentification (login, inscription, réinitialisation), UI et erreurs.
 * Utilisation: Attache des listeners aux formulaires présents dans les modales/pages.
 * Dépendances: Fetch API (/api/auth/*), Bootstrap (spinners), showToast() si disponible.
 */
// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du formulaire de connexion
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            const errorDiv = document.getElementById('loginError');
            const formData = new FormData(event.target);

            if (errorDiv) {
                errorDiv.style.display = 'none';
            }

            try {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Connexion...';
                }

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

                const contentType = response.headers.get('content-type');
                let data;
                if (contentType && contentType.includes('application/json')) {
                    data = await response.json();
                } else {
                    const text = await response.text();
                    console.error('[login] Réponse non JSON:', text.substring(0, 500));
                    throw new Error('Le serveur a renvoyé une réponse inattendue.');
                }

                if (!data.success) {
                    throw new Error(data.message || 'Erreur de connexion');
                }

                const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                if (loginModal) {
                    loginModal.hide();
                }

                if (typeof showToast === 'function') {
                    showToast('Connexion réussie', 'success');
                }

                // Redirection post-login si un paramètre redirect est présent dans l'URL
                let redirect = null;
                try {
                    const url = new URL(window.location.href);
                    redirect = url.searchParams.get('redirect');
                } catch (_) {}

                setTimeout(() => {
                    if (redirect) {
                        window.location.href = redirect;
                    } else {
                        window.location.reload();
                    }
                }, 1000);

            } catch (error) {
                if (errorDiv) {
                    errorDiv.textContent = error.message;
                    errorDiv.style.display = 'block';
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        });
    }

    // Gestion du formulaire d'inscription
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            const errorDiv = document.getElementById('registerError');

            if (errorDiv) {
                errorDiv.style.display = 'none';
            }

            try {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Envoi...';
                }

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

                const contentType = response.headers.get('content-type');
                let data;
                if (contentType && contentType.includes('application/json')) {
                    data = await response.json();
                } else {
                    const text = await response.text();
                    console.error('[register] Réponse non JSON:', text.substring(0, 500));
                    throw new Error('Le serveur a renvoyé une réponse inattendue.');
                }

                if (!data.success) {
                    throw new Error(data.message || 'Une erreur est survenue lors de l\'inscription');
                }

                // Étape 2 dans la même modale : succès + incitation profil organisateur
                const registerModalEl = document.getElementById('registerModal');
                const successPanel = document.getElementById('registerSuccess');

                if (registerModalEl && successPanel) {
                    const introEl = registerModalEl.querySelector('.auth-intro');
                    const titleEl = registerModalEl.querySelector('.modal-title');
                    const footerEl = registerModalEl.querySelector('.modal-footer');
                    const successMsg = document.getElementById('registerSuccessMessage');

                    registerForm.reset();
                    registerForm.classList.add('is-hidden');
                    if (introEl) introEl.classList.add('is-hidden');
                    if (footerEl) footerEl.classList.add('is-hidden');
                    if (titleEl) titleEl.textContent = 'Bienvenue !';
                    if (successMsg && data.message) successMsg.textContent = data.message;
                    successPanel.classList.remove('is-hidden');
                } else {
                    const registerModal = bootstrap.Modal.getInstance(registerModalEl);
                    if (registerModal) {
                        registerModal.hide();
                    }

                    registerForm.reset();

                    if (typeof showToast === 'function') {
                        showToast(data.message, 'success');
                    } else {
                        alert(data.message);
                    }
                }

            } catch (error) {
                if (errorDiv) {
                    errorDiv.textContent = error.message;
                    errorDiv.style.display = 'block';
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        });
    }

    // Remet la modale d'inscription à l'étape 1 quand elle se ferme
    const registerModalElReset = document.getElementById('registerModal');
    if (registerModalElReset) {
        registerModalElReset.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('registerForm');
            const successPanel = document.getElementById('registerSuccess');
            const introEl = this.querySelector('.auth-intro');
            const titleEl = this.querySelector('.modal-title');
            const footerEl = this.querySelector('.modal-footer');
            if (form) form.classList.remove('is-hidden');
            if (successPanel) successPanel.classList.add('is-hidden');
            if (introEl) introEl.classList.remove('is-hidden');
            if (footerEl) footerEl.classList.remove('is-hidden');
            if (titleEl) titleEl.textContent = 'Créer un compte';
        });
    }

    // Gestion du formulaire de mot de passe oublié
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            const formData = new FormData(event.target);

            try {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Envoi...';
                }

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

                const contentType = response.headers.get('content-type');
                let data;
                if (contentType && contentType.includes('application/json')) {
                    data = await response.json();
                } else {
                    const text = await response.text();
                    console.error('[forgot] Réponse non JSON:', text.substring(0, 500));
                    throw new Error('Le serveur a renvoyé une réponse inattendue.');
                }

                if (!data.success) {
                    throw new Error(data.message || 'Une erreur est survenue');
                }

                showSuccess(data.message);
                forgotPasswordForm.reset();
                const modalEl = document.getElementById('forgotPasswordModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                }
            } catch (error) {
                showError(error.message);
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
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
                const password = form.querySelector('input[name="password"]').value;
                const passwordConfirm = form.querySelector('input[name="password_confirm"]').value;

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
                    msg.style.display = 'block';
                }

                setTimeout(() => window.location.href = '/', 2000);

            } catch (error) {
                if (msg) {
                    msg.className = 'alert alert-danger';
                    msg.textContent = error.message;
                    msg.style.display = 'block';
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
        const response = await fetch('/api/auth/logout.php');
        const data = await response.json();
        
        if (data.success) {
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
    if (typeof showToast === 'function') {
        showToast(message, 'danger');
    } else {
        showMessage(message, 'danger');
    }
}

function showSuccess(message) {
    if (typeof showToast === 'function') {
        showToast(message, 'success');
    } else {
        showMessage(message, 'success');
    }
}

function logEyeButtons(label) {
    document.querySelectorAll('.toggle-password').forEach((btn, i) => {
        const inputId = btn.getAttribute('data-target');
        const input = inputId ? document.getElementById(inputId) : null;
        const cs = window.getComputedStyle(btn);
    });
}

// Gestion de l'affichage/masquage des mots de passe (oeil) via délégation d'événements
document.addEventListener('click', function(e) {
    const button = e.target.closest('.toggle-password');
    if (!button) return;

    const inputId = button.getAttribute('data-target');
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');


    if (!input) {
        console.warn('[auth.js] champ mot de passe non trouvé pour', inputId);
        return;
    }

    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }

    logEyeButtons('after click');
});

document.addEventListener('DOMContentLoaded', function() {
    logEyeButtons('DOMContentLoaded');
});

document.addEventListener('shown.bs.modal', function(e) {
    logEyeButtons('modal shown');
});

