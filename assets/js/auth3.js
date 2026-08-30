console.log('🔄 Début du chargement de auth.js');

let loginModalInstance = null;
let registerModalInstance = null;
let forgotPasswordModalInstance = null;

// Fonction de déconnexion globale
window.logout = async function() {
    try {
        console.log('🚪 Tentative de déconnexion');
        const response = await fetch('/api/auth/logout.php');
        const data = await response.json();
        
        if (data.success) {
            console.log('✅ Déconnexion réussie');
            window.location.href = '/';
        } else {
            console.error('❌ Erreur lors de la déconnexion:', data.message);
            window.location.href = '/';
        }
    } catch (error) {
        console.error('❌ Erreur:', error);
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
    
    // Supprimer automatiquement après 5 secondes
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

// Configuration des en-têtes par défaut pour fetch
const defaultHeaders = {
    'Cache-Control': 'no-cache, no-store, must-revalidate',
    'Pragma': 'no-cache'
};

// Fonction de navigation sécurisée
window.navigateToPage = function(url, event) {
    console.log('🚀 Navigation vers:', url);
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Vérifier si l'utilisateur est toujours connecté
    fetch('/check-auth.php', {
        method: 'GET',
        credentials: 'include',
        headers: defaultHeaders
    })
    .then(response => response.json())
    .then(data => {
        console.log('✅ Vérification auth:', data);
        if (data.authenticated) {
            window.location.href = url;
        } else {
            console.log('❌ Session expirée');
            showError('Session expirée, veuillez vous reconnecter');
            if (loginModalInstance) {
                loginModalInstance.show();
            }
        }
    })
    .catch(error => {
        console.error('❌ Erreur vérification auth:', error);
        showError('Erreur de connexion au serveur');
    });
    return false;
};

// Fonction utilitaire pour afficher les erreurs
function showError(message) {
    console.error('❌ Erreur:', message);
    const errorDiv = document.getElementById('loginError');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }
}

// Fonction pour afficher un message de succès
function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show';
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Ajouter l'alerte au début du contenu principal
    const mainContent = document.querySelector('main');
    if (mainContent) {
        mainContent.insertBefore(alertDiv, mainContent.firstChild);
    }
    
    // Auto-fermeture après 3 secondes
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    console.log('🌟 Auth.js chargé');
    
    // Gestion du formulaire de connexion
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const errorDiv = document.getElementById('loginError');
            
            try {
                submitBtn.disabled = true;
                const formData = new FormData(event.target);
                
                const response = await fetch('/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: formData.get('email'),
                        password: formData.get('password')
                    })
                });

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Erreur de connexion');
                }

                window.location.href = '/';
                
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
                // Désactiver le bouton et réinitialiser les erreurs
                submitBtn.disabled = true;
                resetErrors(errorDiv);
                
                const formData = new FormData(event.target);
                
                // Vérifier que les mots de passe correspondent
                if (formData.get('password') !== formData.get('password_confirm')) {
                    throw new Error('Les mots de passe ne correspondent pas');
                }

                // Vérifier la longueur du mot de passe
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
                        password: formData.get('password')
                    })
                });

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Une erreur est survenue lors de l\'inscription');
                }

                // En cas de succès
                alert(data.message);
                window.location.href = '/';
                
            } catch (error) {
                handleError(errorDiv, error);
            } finally {
                submitBtn.disabled = false;
            }
        });
    }
    
    // Gestion du formulaire de connexion
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const errorDiv = document.getElementById('loginError');
            
            try {
                submitBtn.disabled = true;
                resetErrors(errorDiv);
                
                const formData = new FormData(event.target);
                
                const response = await fetch('/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: formData.get('email'),
                        password: formData.get('password')
                    })
                });

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Une erreur est survenue lors de la connexion');
                }

                window.location.href = '/';
                
            } catch (error) {
                handleError(errorDiv, error);
            } finally {
                submitBtn.disabled = false;
            }
        });
    }
    
    // Gestion du formulaire de réinitialisation de mot de passe
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const errorDiv = document.getElementById('forgotPasswordError');
            const successDiv = document.getElementById('forgotPasswordSuccess');
            
            try {
                submitBtn.disabled = true;
                resetErrors(errorDiv);
                if (successDiv) successDiv.style.display = 'none';
                
                const formData = new FormData(event.target);
                
                const response = await fetch('/api/auth/forgot-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: formData.get('email')
                    })
                });

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Une erreur est survenue');
                }

                if (successDiv) {
                    successDiv.textContent = data.message;
                    successDiv.style.display = 'block';
                }
                
                event.target.reset();
                
            } catch (error) {
                handleError(errorDiv, error);
            } finally {
                submitBtn.disabled = false;
            }
        });
    }
    
    // Gestion des boutons de visualisation du mot de passe
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault(); // Empêcher le formulaire de se soumettre
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });
});

// Fonction utilitaire pour gérer les erreurs
const handleError = (errorDiv, error) => {
    console.log('Erreur :', error.message); // Log plus propre
    errorDiv.textContent = error.message;
    errorDiv.style.display = 'block';
};

// Fonction utilitaire pour réinitialiser les erreurs
const resetErrors = (errorDiv) => {
    if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
    }
};

console.log('✅ Fin du chargement de auth.js');
