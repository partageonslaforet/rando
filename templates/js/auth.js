
let loginModalInstance = null;

// Fonction de déconnexion globale
window.logout = async function() {
    try {
        const response = await fetch('/logout.php');
        const data = await response.json();
        
        if (data.success) {
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
        if (data.authenticated) {
            window.location.href = url;
        } else {
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
    initAuth();
});

function initAuth() {
    
    // Ne cherche le modal que sur les pages qui en ont besoin
    const loginModalElement = document.getElementById('loginModal');
    
    // Si le modal n'existe pas, on est probablement sur une page qui n'en a pas besoin
    if (!loginModalElement) {
        return;
    }
    
    // Initialiser l'instance du modal
    loginModalInstance = new bootstrap.Modal(loginModalElement);
    
    // Initialisation du modal et des événements de connexion
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            const rememberMe = document.getElementById('loginRememberMe').checked;
            
            try {
                
                const requestData = { 
                    email, 
                    password, 
                    remember_me: rememberMe
                };
                
                const response = await fetch('/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...defaultHeaders
                    },
                    body: JSON.stringify(requestData)
                });
                
                
                // Récupérer le texte brut de la réponse
                const rawResponse = await response.text();
                
                // Essayer de parser le JSON
                let data;
                try {
                    data = JSON.parse(rawResponse);
                } catch (parseError) {
                    console.error('❌ Erreur de parsing JSON:', parseError);
                    throw new Error('Réponse invalide du serveur');
                }
                
                if (data.success) {
                    loginModalInstance.hide();
                    updateUIAfterLogin(data.user);
                    showSuccess('Connexion réussie !');
                    window.location.reload();
                } else {
                    console.error('❌ Échec de la connexion:', data.message);
                    showError(data.message || 'Erreur de connexion');
                }
            } catch (error) {
                console.error('❌ Erreur:', error);
                showError('Erreur de connexion au serveur');
            }
        });
    }

    // Gérer les liens qui ouvrent la modal de connexion
    document.querySelectorAll('a[href="#"][data-bs-target="#loginModal"]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            if (loginModalInstance) {
                loginModalInstance.show();
            }
        });
    });

    try {
        // Initialiser la modal de connexion
        // const loginModal = new bootstrap.Modal(loginModalElement, {
        //     backdrop: true,
        //     keyboard: true
        // });
    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation:', error);
    }
}

// Fonction pour mettre à jour l'interface utilisateur après la connexion
function updateUIAfterLogin(user) {
    
    // Fermer le modal de connexion
    const loginModal = document.getElementById('loginModal');
    if (loginModal) {
        const modal = bootstrap.Modal.getInstance(loginModal);
        if (modal) {
            modal.hide();
        }
    }

    // Si c'est un admin, rediriger vers le dashboard
    if (user.role === 'admin') {
        window.location.href = '/pages/admin/dashboard.php';
        return;
    }


    // Pour les utilisateurs normaux, mettre à jour l'interface
    document.querySelectorAll('[data-bs-target="#loginModal"]').forEach(el => {
        el.style.display = 'none';
    });

    // Ajouter le menu utilisateur s'il n'existe pas
    const navbarNav = document.getElementById('navbarNav');
    const userMenuHtml = `
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                Mon Compte
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                <li><a class="dropdown-item" href="#" onclick="return navigateToPage('/pages/user/profile.php', event)">Mon Profil</a></li>
                <li><a class="dropdown-item" href="#" onclick="return navigateToPage('/pages/user/my-events.php', event)">Mes Événements</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="logout(); return false;">Déconnexion</a></li>
            </ul>
        </li>`;

    if (navbarNav) {
        // Supprimer l'ancien menu s'il existe
        const existingMenu = navbarNav.querySelector('.dropdown');
        if (existingMenu) {
            existingMenu.remove();
        }

        // Ajouter le nouveau menu
        const ul = navbarNav.querySelector('ul');
        if (ul) {
            // Insérer avant le dernier élément (thème toggle)
            const lastItem = ul.lastElementChild;
            const div = document.createElement('div');
            div.innerHTML = userMenuHtml;
            while (div.firstChild) {
                ul.insertBefore(div.firstChild, lastItem);
            }
        }
    }
    
    // Mettre à jour les liens "Créer un événement"
    document.querySelectorAll('a[href="#"][data-bs-target="#loginModal"]').forEach(link => {
        link.href = '/templates/events/create-event.php';
        link.removeAttribute('data-bs-target');
    });

    // Vérifier si nous devons rediriger vers la page de création d'événement
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('redirect') && urlParams.get('redirect') === 'create-event') {
        window.location.href = '/templates/events/create-event.php';
    }
}
