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
    console.log('🔍 DOM Content Loaded');
    initAuth();
});

function initAuth() {
    console.log('✅ Première initialisation auth');
    
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

    // Gestionnaire pour les liens modaux
    document.addEventListener('click', function(event) {
        const modalLink = event.target.closest('.modal-link, .register-link');
        if (!modalLink) return;

        event.preventDefault();
        console.log('👆 Clic sur lien modal:', modalLink.outerHTML);

        const targetModalId = modalLink.getAttribute('data-bs-target');
        if (!targetModalId) return;

        const targetModal = document.querySelector(targetModalId);
        if (!targetModal) {
            console.error('❌ Modal cible non trouvé:', targetModalId);
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
        const modal = event.target;
        console.log('🔒 Modal fermé:', modal.id);
        
        // Nettoyer les backdrops et classes
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    });

    // Initialisation du formulaire de connexion
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        console.log('✅ Formulaire de connexion trouvé');
        
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('🔄 Soumission du formulaire de connexion');
            
            try {
                // Récupérer les valeurs du formulaire
                const email = document.getElementById('loginEmail')?.value;
                const password = document.getElementById('loginPassword')?.value;
                const rememberMe = document.getElementById('remember_me')?.checked || false;
                
                if (!email || !password) {
                    throw new Error('Email et mot de passe requis');
                }

                const requestData = { 
                    email, 
                    password, 
                    remember_me: rememberMe
                };
                
                const response = await fetch('/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                // Récupérer le texte brut de la réponse
                const rawResponse = await response.text();
                console.log('🔍 Réponse brute du serveur:', rawResponse);
                console.log('🔍 Status HTTP:', response.status);
                console.log('🔍 Headers:', Object.fromEntries(response.headers));
                
                // Essayer de parser le JSON
                let data;
                try {
                    data = JSON.parse(rawResponse);
                    console.log('🔍 Données parsées:', {
                        success: data.success,
                        message: data.message,
                        redirect: data.redirect,
                        user: data.user
                    });
                } catch (parseError) {
                    console.error('❌ Erreur de parsing JSON:', parseError);
                    console.log('❌ Texte qui a causé l\'erreur:', rawResponse);
                    throw new Error('Réponse invalide du serveur');
                }
                
                if (!response.ok || !data.success) {
                    console.error('❌ Erreur de réponse:', {
                        ok: response.ok,
                        success: data.success,
                        message: data.message
                    });
                    throw new Error(data.message || 'Erreur lors de la connexion');
                }
                
                // Mettre à jour l'interface utilisateur
                try {
                    console.log('🔄 Début mise à jour UI avec:', data);
                    updateUIAfterLogin(data);
                    console.log('✅ Fin mise à jour UI');
                } catch (uiError) {
                    console.error('❌ Erreur lors de la mise à jour UI:', uiError);
                    throw uiError;
                }
                
            } catch (error) {
                console.error('❌ Erreur:', error);
                showError(error.message || 'Une erreur est survenue lors de la connexion');
            }
        });
    }

    // Initialisation du formulaire d'inscription
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        console.log('✅ Formulaire d\'inscription trouvé');
        
        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('🔄 Soumission du formulaire d\'inscription');
            
            try {
                // Récupérer les valeurs du formulaire
                const email = document.getElementById('registerEmail')?.value;
                const password = document.getElementById('password')?.value;
                const name = document.getElementById('username')?.value;
                
                console.log('Champs trouvés:', {
                    emailField: document.getElementById('registerEmail'),
                    passwordField: document.getElementById('password'),
                    nameField: document.getElementById('username')
                });
                console.log('Valeurs du formulaire:', { email, password, name });
                
                if (!email || !password || !name) {
                    throw new Error('Tous les champs sont obligatoires');
                }

                const requestData = { 
                    email, 
                    password,
                    name
                };
                
                const response = await fetch('/api/auth/register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Erreur lors de l\'inscription');
                }
                
                // Fermer le modal d'inscription
                const registerModal = document.getElementById('registerModal');
                if (registerModal) {
                    const modal = bootstrap.Modal.getInstance(registerModal);
                    if (modal) {
                        modal.hide();
                    }
                }
                
                // Afficher le message de succès
                showSuccess(data.message || 'Inscription réussie ! Veuillez vérifier votre email.');
                
            } catch (error) {
                console.error('❌ Erreur:', error);
                showError(error.message || 'Une erreur est survenue lors de l\'inscription');
            }
        });
    }

    // Fonction pour mettre à jour l'interface utilisateur après la connexion
    function updateUIAfterLogin(data) {
        console.log('🔍 updateUIAfterLogin appelé avec:', data);
        
        // Fermer le modal de connexion
        const loginModal = document.getElementById('loginModal');
        if (loginModal) {
            console.log('🔍 Modal de connexion trouvé');
            const modal = bootstrap.Modal.getInstance(loginModal);
            if (modal) {
                console.log('🔍 Instance bootstrap du modal trouvée, fermeture...');
                modal.hide();
            }
        }

        
        setTimeout(() => {
            console.log('⏰ Timer de 15s terminé, redirection...');
            // Rediriger vers l'URL spécifiée par le serveur ou recharger la page
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.reload();
            }
        }, 100);
    }
}
