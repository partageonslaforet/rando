/**
 * Fichier: /assets/js/core/modals.js
 * Rôle: Fonctions globales d’affichage des modales d’authentification.
 * Utilisation: `showLoginModal()`, `showRegisterModal()`, `showForgotPasswordModal()` depuis le front.
 * Dépendances: Bootstrap Modal (window.bootstrap).
 */
// Définition directe des fonctions globales
window.showLoginModal = function() {
    const modalElement = document.getElementById('loginModal');
    
    if (!modalElement) {
        console.error('❌ Modal non trouvé!');
        return;
    }
    
    try {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } catch (error) {
        console.error('❌ Erreur lors de l\'affichage du modal:', error);
        console.error('Bootstrap disponible?', typeof bootstrap);
        console.error('Modal element:', modalElement);
    }
};

window.showRegisterModal = function() {
    const modalElement = document.getElementById('registerModal');
    if (!modalElement) {
        console.error('❌ Modal d\'inscription non trouvé!');
        return;
    }
    
    try {
        const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
        if (loginModal) loginModal.hide();
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } catch (error) {
        console.error('❌ Erreur lors de l\'affichage du modal d\'inscription:', error);
    }
};

window.showForgotPasswordModal = function() {
    const modalElement = document.getElementById('forgotPasswordModal');
    if (!modalElement) {
        console.error('❌ Modal de mot de passe oublié non trouvé!');
        return;
    }
    
    try {
        const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
        if (loginModal) loginModal.hide();
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } catch (error) {
        console.error('❌ Erreur lors de l\'affichage du modal de mot de passe oublié:', error);
    }
};

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', function() {
    // Ouvrir automatiquement la modale de login si demandé via l'URL
    try {
        const url = new URL(window.location.href);
        if (url.searchParams.get('showLogin') === '1') {
            window.showLoginModal();
        }
    } catch (_) {}
});
