// Gestion de l'image principale
function handleMainImageUpload(event) {
    const input = event.target;
    const file = input.files[0];
    const preview = document.getElementById('mainImagePreview');
    
    if (!preview) {
        console.error('Container de prévisualisation non trouvé');
        return;
    }
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Créer ou mettre à jour l'aperçu
            if (!preview.querySelector('img')) {
                const img = document.createElement('img');
                img.classList.add('img-fluid', 'rounded', 'mb-3');
                preview.appendChild(img);
            }
            
            const img = preview.querySelector('img');
            img.src = e.target.result;
            img.alt = file.name;
            
            // Afficher le conteneur de prévisualisation
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

// Gestion des images secondaires
function handleSecondaryImagesUpload(event) {
    const input = event.target;
    const files = input.files;
    const container = document.getElementById('secondaryImagesPreview');
    
    if (!container) {
        console.error('Container de prévisualisation non trouvé');
        return;
    }
    
    // Vider le conteneur existant
    container.innerHTML = '';
    
    // Créer une ligne pour contenir les images
    const row = document.createElement('div');
    row.classList.add('row', 'g-3');
    container.appendChild(row);
    
    Array.from(files).forEach(file => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Créer une colonne pour chaque image
            const col = document.createElement('div');
            col.classList.add('col-md-4');
            
            // Créer le conteneur de l'image
            const imgContainer = document.createElement('div');
            imgContainer.classList.add('secondary-image-container');
            
            // Créer l'image
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = file.name;
            img.classList.add('img-fluid', 'rounded');
            
            // Ajouter l'image au conteneur
            imgContainer.appendChild(img);
            col.appendChild(imgContainer);
            row.appendChild(col);
        };
        
        reader.readAsDataURL(file);
    });
    
    // Afficher le conteneur
    container.style.display = 'block';
}

// Gestion des images supplémentaires
function handleAdditionalImageUpload(input, index) {
    const preview = document.getElementById(`additionalImagePreview_${index}`);
    const container = document.getElementById(`additionalImageContainer_${index}`);
    handleImageUpload(input, preview, container);
}

function removeAdditionalImage(index) {
    const input = document.querySelector(`input[name="routes[${index}][image]"]`);
    const preview = document.getElementById(`additionalImagePreview_${index}`);
    const container = document.getElementById(`additionalImageContainer_${index}`);
    
    if (input) input.value = '';
    if (preview) preview.src = '';
    if (container) container.style.display = 'none';
}

// Fonction utilitaire pour gérer l'upload d'image
function handleImageUpload(input, preview, container) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
            }
            if (container) {
                container.style.display = 'block';
            }
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Attacher les gestionnaires d'événements
document.addEventListener('DOMContentLoaded', function() {
    
    const mainImageInput = document.getElementById('mainImage');
    const secondaryImagesInput = document.getElementById('secondaryImages');
    
    if (mainImageInput) {
        mainImageInput.addEventListener('change', handleMainImageUpload);
    } else {
        console.error('Input d\'image principale non trouvé');
    }
    
    if (secondaryImagesInput) {
        secondaryImagesInput.addEventListener('change', handleSecondaryImagesUpload);
    } else {
        console.error('Input d\'images secondaires non trouvé');
    }
});

// Export des fonctions
window.handleMainImageUpload = handleMainImageUpload;
window.handleSecondaryImagesUpload = handleSecondaryImagesUpload;
window.handleAdditionalImageUpload = handleAdditionalImageUpload;
window.removeAdditionalImage = removeAdditionalImage;
