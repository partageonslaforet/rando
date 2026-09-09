// Fonction pour initialiser la navigation
function initializeNavigation() {
    
    // Ajouter les conteneurs d'erreurs s'ils n'existent pas
    addErrorContainers();
    
    // Mettre à jour l'affichage initial
    updateStepDisplay();
    updateNavigationButtons();
}

// Fonction pour ajouter les conteneurs d'erreurs
function addErrorContainers() {
    
    // Pour chaque étape
    for (let i = 1; i <= window.totalSteps; i++) {
        const stepContent = document.getElementById(`step${i}`);
        if (stepContent) {
            // Vérifier si le conteneur d'erreurs existe déjà
            let errorContainer = document.getElementById(`step${i}Errors`);
            if (!errorContainer) {
                // Créer le conteneur d'erreurs
                errorContainer = document.createElement('div');
                errorContainer.id = `step${i}Errors`;
                errorContainer.className = 'mb-4';
                errorContainer.style.display = 'none';
                
                // Insérer au début de l'étape
                stepContent.insertBefore(errorContainer, stepContent.firstChild);
            }
        }
    }
}

// Fonction pour mettre à jour l'affichage des étapes
function updateStepDisplay() {
    
    // Pour chaque étape
    for (let i = 1; i <= window.totalSteps; i++) {
        const stepContent = document.getElementById(`step${i}`);
        if (stepContent) {
            if (i === window.currentStep) {
                stepContent.classList.remove('d-none');
            } else {
                stepContent.classList.add('d-none');
            }
        }
    }
}

// Initialiser la navigation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    // Attendre que event-validation.js soit chargé
    setTimeout(() => {
        if (window.totalSteps) {
            initializeNavigation();
        } else {
            console.error('❌ totalSteps non trouvé dans window');
        }
    }, 100);
});

// Export des fonctions
window.initializeNavigation = initializeNavigation;
window.updateStepDisplay = updateStepDisplay;
