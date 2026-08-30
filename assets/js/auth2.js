console.log('🚀 Chargement de auth.js');

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

// Une seule initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Chargement de auth.js');

    // Variables pour les instances de modals
    let loginModalInstance, registerModalInstance;

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

    // Gestionnaire pour les liens modaux
    document.addEventListener('click', function(event) {
        const modalLink = event.target.closest('.modal-link, .register-link');
        if (!modalLink) return;

        event.preventDefault();
        
        const targetModalId = modalLink.getAttribute('data-bs-target');
        if (!targetModalId) return;

        const targetModal = document.querySelector(targetModalId);
        if (!targetModal) {
            console.log('Modal cible non trouvé:', targetModalId);
            return;
        }

        // Si c'est un lien d'inscription, fermer d'abord le modal de connexion
        if (modalLink.classList.contains('register-link')) {
            const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
            if (loginModal) {
                loginModal.hide();
                document.getElementById('loginModal').addEventListener('hidden.bs.modal', function openRegister() {
                    document.getElementById('loginModal').removeEventListener('hidden.bs.modal', openRegister);
                    const registerModal = new bootstrap.Modal(targetModal);
                    registerModal.show();
                }, { once: true });
                return;
            }
        }

        // Pour les autres liens, ouvrir directement
        const modalInstance = new bootstrap.Modal(targetModal);
        modalInstance.show();
    });

    // Nettoyage après fermeture d'un modal
    document.addEventListener('hidden.bs.modal', function(event) {
        // Nettoyer les backdrops et classes
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        
        // Réinitialiser les formulaires et les messages d'erreur
        const modal = event.target;
        const form = modal.querySelector('form');
        const errorDiv = modal.querySelector('.alert-danger');
        const successDiv = modal.querySelector('.alert-success');
        
        if (form) form.reset();
        if (errorDiv) resetErrors(errorDiv);
        if (successDiv) successDiv.style.display = 'none';
    });

    // Initialiser les modals
    const loginModalElement = document.getElementById('loginModal');
    const registerModalElement = document.getElementById('registerModal');
    const forgotPasswordModalElement = document.getElementById('forgotPasswordModal');

    console.log('🔍 Vérification des modals :', {
        login: loginModalElement ? '✅' : '❌',
        register: registerModalElement ? '✅' : '❌',
        forgotPassword: forgotPasswordModalElement ? '✅' : '❌'
    });

    // Initialiser les instances Bootstrap des modals
    if (loginModalElement) {
        loginModalInstance = new bootstrap.Modal(loginModalElement);
        console.log('✅ Modal de connexion initialisé');
    }

    if (registerModalElement) {
        registerModalInstance = new bootstrap.Modal(registerModalElement);
        console.log('✅ Modal d\'inscription initialisé');
    }
});
