console.log('🔄 Chargement de modals.js');

// Définition directe des fonctions globales
window.showLoginModal = function() {
    console.log('🎯 showLoginModal appelé');
    const modalElement = document.getElementById('loginModal');
    
    if (!modalElement) {
        console.error('❌ Modal non trouvé!');
        return;
    }
    
    try {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        console.log('✅ Modal affiché avec succès');
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
    console.log('🌟 modals.js - DOMContentLoaded');
    console.log('📌 Vérification des éléments:', {
        'showLoginModal': typeof window.showLoginModal,
        'loginModal': document.getElementById('loginModal'),
        'bootstrap': typeof bootstrap,
        'bootstrap.Modal': typeof bootstrap?.Modal
    });
});
