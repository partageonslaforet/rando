console.log(' Début du chargement de auth.js');

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    console.log(' Auth.js chargé');
    
    // Gestion du formulaire de connexion
    const loginForm = document.getElementById('loginForm');
    console.log(' Recherche du formulaire de connexion:', loginForm ? 'trouvé' : 'non trouvé');
    
    if (loginForm) {
        console.log(' Formulaire de connexion trouvé');
        loginForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            console.log(' Formulaire soumis');
            
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const errorDiv = document.getElementById('loginError');
            const formData = new FormData(event.target);
            
            console.log(' Email utilisé:', formData.get('email'));
            
            try {
                submitBtn.disabled = true;
                console.log(' Préparation de la requête...');
                
                const requestData = {
                    email: formData.get('email'),
                    password: formData.get('password')
                };
                console.log(' Données à envoyer:', requestData);
                
                const response = await fetch('/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });

                console.log(' Réponse reçue, status:', response.status);
                console.log(' Headers:', Object.fromEntries(response.headers));
                
                const data = await response.json();
                console.log(' Données reçues:', data);
                
                if (!data.success) {
                    console.log(' Erreur:', data.message);
                    throw new Error(data.message || 'Erreur de connexion');
                }

                console.log('✅ Connexion réussie');
                console.log('📦 Session:', data.user);
                
                // Fermer le modal de connexion
                const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                if (loginModal) {
                    loginModal.hide();
                }
                
                // Recharger la page pour mettre à jour le header
                console.log('🔄 Rechargement de la page...');
                window.location.reload();
                
            } catch (error) {
                console.error(' Erreur attrapée:', error.message);
                if (errorDiv) {
                    errorDiv.textContent = error.message;
                    errorDiv.style.display = 'block';
                }
            } finally {
                submitBtn.disabled = false;
            }
        });
    } else {
        console.log(' Formulaire de connexion non trouvé');
    }
    
    // Gestion du formulaire d'inscription
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        console.log(' Formulaire d\'inscription trouvé');
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
                        password: formData.get('password')
                    })
                });

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Une erreur est survenue lors de l\'inscription');
                }

                alert(data.message);
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
    } else {
        console.log(' Formulaire d\'inscription non trouvé');
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
