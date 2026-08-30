// Fonction pour initialiser la navigation
function initializeNavigation() {
    console.log('🚀 Initialisation de la navigation');
    
    // Ajouter les conteneurs d'erreurs s'ils n'existent pas
    addErrorContainers();
    
    // Mettre à jour l'affichage initial
    updateStepDisplay();
    updateNavigationButtons();
}

// Fonction pour ajouter les conteneurs d'erreurs
function addErrorContainers() {
    console.log('📦 Ajout des conteneurs d\'erreurs');
    
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
                console.log('✅ Conteneur d\'erreurs ajouté pour l\'étape', i);
            }
        }
    }
}

// Fonction pour mettre à jour l'affichage des étapes
function updateStepDisplay() {
    console.log('🔄 Mise à jour de l\'affichage des étapes');
    
    // Pour chaque étape
    for (let i = 1; i <= window.totalSteps; i++) {
        const stepContent = document.getElementById(`step${i}`);
        if (stepContent) {
            if (i === window.currentStep) {
                stepContent.classList.remove('d-none');
                console.log('👀 Étape', i, 'affichée');
            } else {
                stepContent.classList.add('d-none');
                console.log('👻 Étape', i, 'masquée');
            }
        }
    }
}

// Initialiser la navigation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    // Attendre que event-validation.js soit chargé
    setTimeout(() => {
        if (window.totalSteps) {
            console.log('🚀 Initialisation de la navigation (totalSteps:', window.totalSteps, ')');
            initializeNavigation();
        } else {
            console.error('❌ totalSteps non trouvé dans window');
        }
    }, 100);
});

// Export des fonctions
window.initializeNavigation = initializeNavigation;
window.updateStepDisplay = updateStepDisplay;
