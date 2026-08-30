// Gestion des formulaires AJAX
$(document).ready(function() {
    // Formulaire de connexion
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        // Cacher le message d'erreur précédent
        $('#loginError').addClass('d-none').html('');
        
        // Récupérer les données du formulaire
        const formData = {
            email: $('#email').val(),
            password: $('#password').val()
        };

        // Désactiver le bouton pendant la requête
        const submitButton = $(this).find('button[type="submit"]');
        const originalText = submitButton.text();
        submitButton.prop('disabled', true).text('Connexion...');

        $.ajax({
            url: 'https://rando.partageonslaforet.be/api/auth.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                console.log('Login response:', response);
                if (response.status === 'success' && response.data) {
                    // Stocker le token
                    localStorage.setItem('auth_token', response.data.token);
                    
                    // Stocker les infos utilisateur
                    localStorage.setItem('user', JSON.stringify(response.data.user));
                    
                    // Fermer le modal
                    $('#loginModal').modal('hide');
                    
                    // Rediriger vers /admin si l'utilisateur est admin
                    if (response.data.user.role === 'admin') {
                        window.location.href = '/admin';
                    } else {
                        // Sinon recharger la page
                        window.location.reload();
                    }
                } else {
                    // Afficher l'erreur
                    $('#loginError').removeClass('d-none').html(response.message || 'Erreur de connexion');
                }
            },
            error: function(xhr, status, error) {
                console.error('Login error:', {xhr, status, error});
                console.log('Response Text:', xhr.responseText);
                try {
                    const response = JSON.parse(xhr.responseText);
                    $('#loginError').removeClass('d-none').html(response.message || 'Erreur lors de la connexion');
                } catch (e) {
                    $('#loginError').removeClass('d-none').html('Erreur lors de la connexion. Veuillez réessayer.');
                }
            },
            complete: function() {
                // Réactiver le bouton
                submitButton.prop('disabled', false).text(originalText);
            }
        });
    });

    // Formulaire de modification du profil
    $('#editProfileForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            name: $('#editName').val(),
            email: $('#editEmail').val(),
            password: $('#editPassword').val() || undefined
        };

        $.ajax({
            url: 'https://rando.partageonslaforet.be/api/profile.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.status === 'success') {
                    // Mettre à jour les données utilisateur stockées
                    localStorage.setItem('user', JSON.stringify(response.data.user));
                    
                    // Fermer le modal
                    $('#editProfileModal').modal('hide');
                    
                    // Recharger la page pour afficher les nouvelles informations
                    window.location.reload();
                } else {
                    alert(response.message || 'Erreur lors de la mise à jour du profil');
                }
            },
            error: function(xhr, status, error) {
                console.error('Profile update error:', error);
                try {
                    const response = JSON.parse(xhr.responseText);
                    alert(response.message || 'Erreur lors de la mise à jour du profil');
                } catch (e) {
                    alert('Erreur lors de la mise à jour du profil');
                }
            }
        });
    });

    // Gestion des alertes
    $('.alert').each(function() {
        const alert = $(this);
        setTimeout(function() {
            alert.fadeOut();
        }, 5000);
    });

    // Confirmation des suppressions
    $('[data-confirm]').on('click', function(e) {
        if (!confirm($(this).data('confirm'))) {
            e.preventDefault();
        }
    });

    // Gestion des filtres d'événements
    $('.filter-form input, .filter-form select').on('change', function() {
        $(this).closest('form').submit();
    });
});

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return localStorage.getItem('auth_token') !== null;
}

// Fonction pour déconnecter l'utilisateur
function logout() {
    $.ajax({
        url: 'https://rando.partageonslaforet.be/api/logout.php',
        type: 'GET',
        success: function() {
            // Supprimer les données stockées
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            
            // Recharger la page
            window.location.reload();
        },
        error: function(xhr, status, error) {
            console.error('Logout error:', error);
            // Même en cas d'erreur, on supprime les données locales
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            window.location.reload();
        }
    });
}

// Ajouter le token d'authentification à toutes les requêtes AJAX
$.ajaxSetup({
    beforeSend: function(xhr) {
        const token = localStorage.getItem('auth_token');
        if (token) {
            xhr.setRequestHeader('Authorization', 'Bearer ' + token);
        }
    }
});

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});
