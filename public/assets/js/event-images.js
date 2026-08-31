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

// Fonction pour supprimer l'image principale
async function removeMainImage(event) {
    event?.preventDefault();
    event?.stopPropagation();
    
    try {
        const formData = new FormData(document.getElementById('createEventForm'));
        formData.append('deleteMainImage', 'true');
        
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Erreur lors de la suppression');
        }
        
        // Mise à jour de l'interface
        const mainImageInput = document.getElementById('mainImage');
        const mainPreview = document.getElementById('mainImagePreview');
        const mainImageContainer = document.querySelector('.main-image-container');
        
        if (mainImageInput) mainImageInput.value = '';
        if (mainPreview) {
            mainPreview.src = '';
            mainPreview.style.display = 'none';
        }
        
        const removeButton = mainImageContainer.querySelector('.remove-image-btn');
        if (removeButton) removeButton.style.display = 'none';
        
    } catch (error) {
        console.error('❌ Erreur:', error);
        showToast('Erreur lors de la suppression de l\'image', 'error');
    }
}

// Fonction pour supprimer une image secondaire
async function removeSecondaryImage(event, imageId) {
    event?.preventDefault();
    event?.stopPropagation();
    
    console.log('🔄 Tentative de suppression de l\'image secondaire:', imageId);
    
    if (!imageId) {
        console.error('❌ Erreur: ID de l\'image non fourni');
        showToast('Erreur lors de la suppression de l\'image', 'error');
        return;
    }
    
    try {
        console.log('📝 Préparation du FormData pour la suppression');
        const formData = new FormData(document.getElementById('createEventForm'));
        formData.append('deleteImage', imageId);
        
        console.log('🌐 Envoi de la requête de suppression');
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        console.log('📥 Réponse reçue:', result);
        
        if (!result.success) {
            throw new Error(result.message || 'Erreur lors de la suppression');
        }
        
        // Mise à jour de l'interface
        console.log('🔍 Recherche des éléments DOM à mettre à jour');
        const previewContainer = document.getElementById('secondaryImagesPreview');
        console.log('Container de prévisualisation trouvé:', previewContainer);
        
        const imageContainer = document.querySelector(`img[data-image-id="${imageId}"]`);
        console.log('Image container trouvé:', imageContainer);
        
        const secondaryContainer = imageContainer?.closest('.secondary-image-container');
        console.log('Secondary container trouvé:', secondaryContainer);
        
        const parentCol = secondaryContainer?.closest('.col-md-4');
        console.log('Parent column trouvé:', parentCol);
        
        if (parentCol) {
            console.log('🗑️ Suppression de l\'élément du DOM');
            parentCol.remove();
            
            // Vérifier s'il reste des images
            const remainingImages = previewContainer?.querySelectorAll('.col-md-4');
            console.log('Images restantes:', remainingImages?.length);
            
            if (previewContainer && (!remainingImages || remainingImages.length === 0)) {
                console.log('📦 Masquage du conteneur de prévisualisation');
                previewContainer.style.display = 'none';
            }
            
            // Réinitialiser l'input file si nécessaire
            const secondaryImagesInput = document.getElementById('secondaryImages');
            if (secondaryImagesInput) {
                secondaryImagesInput.value = '';
            }
            
            console.log('✅ Suppression terminée avec succès');
        } else {
            console.warn('⚠️ Container de l\'image non trouvé dans le DOM');
            throw new Error('Container de l\'image non trouvé');
        }
        
    } catch (error) {
        console.error('❌ Erreur lors de la suppression:', error);
        showToast('Erreur lors de la suppression de l\'image', 'error');
    }
}

// Fonction pour mettre à jour la prévisualisation de l'image principale
function updateMainImagePreview(result) {
    console.log('🔍 Début updateMainImagePreview avec:', result);
    
    if (!result) {
        console.error('❌ Pas de données reçues dans updateMainImagePreview');
        return;
    }
    
    const mainPreview = document.getElementById('mainImagePreview');
    const mainImageContainer = document.querySelector('.main-image-container');
    
    if (!mainPreview || !mainImageContainer) {
        console.error('❌ Éléments DOM non trouvés');
        return;
    }
    
    if (result.mainImage) {
        console.log('🖼️ Mise à jour de l\'image principale');
        mainPreview.src = result.mainImage.path || result.mainImage;
        mainPreview.style.display = 'block';
        
        // Ajouter le bouton de suppression s'il n'existe pas déjà
        if (!mainImageContainer.querySelector('.remove-image-btn')) {
            console.log('➕ Ajout du bouton de suppression');
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-image-btn';
            removeBtn.innerHTML = '×';
            removeBtn.addEventListener('click', handleMainImageDelete);
            mainImageContainer.appendChild(removeBtn);
        }
    }
}

// Fonction pour mettre à jour la prévisualisation des images secondaires
function updateSecondaryImagesPreview(result) {
    console.log('🔍 Début updateSecondaryImagesPreview avec:', result);
    
    // Vérifier que nous avons bien les données attendues
    if (!result) {
        console.error('❌ Pas de données reçues dans updateSecondaryImagesPreview');
        return;
    }
    
    const container = document.getElementById('secondaryImagesPreview');
    console.log('📌 Container images secondaires trouvé:', container);
    
    if (!container) {
        console.error('❌ Container des images secondaires non trouvé');
        return;
    }
    
    // Conserver les images existantes
    const existingImages = Array.from(container.querySelectorAll('img')).map(img => ({
        id: img.dataset.imageId,
        url: img.src
    }));
    console.log('📌 Images secondaires existantes:', existingImages);
    
    if (result.secondaryImages && result.secondaryImages.length > 0) {
        console.log('🖼️ Nouvelles images secondaires reçues:', result.secondaryImages);
        container.style.display = 'block';
        
        // Fusionner les nouvelles images avec les existantes
        const allImages = [...existingImages];
        result.secondaryImages.forEach(newImage => {
            if (!allImages.some(img => img.id === newImage.id)) {
                allImages.push(newImage);
            }
        });
        console.log('📌 Images après fusion:', allImages);
        
        // Mettre à jour l'affichage
        allImages.forEach(image => {
            // Vérifier si l'image existe déjà
            const existingImage = container.querySelector(`img[data-image-id="${image.id}"]`);
            if (!existingImage) {
                console.log('➕ Ajout d\'une nouvelle image:', image.id);
                
                const col = document.createElement('div');
                col.className = 'col-md-4 mb-3';
                
                const imageContainer = document.createElement('div');
                imageContainer.className = 'secondary-image-container';
                
                const img = document.createElement('img');
                img.src = image.url;
                img.dataset.imageId = image.id;
                img.className = 'img-fluid';
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-image-btn';
                removeBtn.innerHTML = '×';
                
                imageContainer.appendChild(img);
                imageContainer.appendChild(removeBtn);
                col.appendChild(imageContainer);
                container.appendChild(col);
            }
        });
    }
    
    console.log('✅ Fin updateSecondaryImagesPreview');
}

// Fonction pour gérer l'upload de l'image principale
async function handleMainImageUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Vérifier la taille du fichier (5Mo max)
    if (file.size > 5 * 1024 * 1024) {
        showToast('L\'image ne doit pas dépasser 5Mo', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('mainImage', file);
    
    // Ajouter le draftId s'il existe
    const draftId = document.querySelector('input[name="draftId"]')?.value;
    if (draftId) {
        formData.append('draftId', draftId);
    }
    
    // Récupérer les images secondaires existantes
    const secondaryImages = Array.from(document.querySelectorAll('#secondaryImagesPreview img')).map(img => ({
        id: img.dataset.imageId,
        path: img.src
    }));
    
    // Ajouter les images secondaires existantes à la requête
    if (secondaryImages.length > 0) {
        formData.append('existingSecondaryImages', JSON.stringify(secondaryImages));
    }

    try {
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Erreur lors de l\'upload');
        }
        
        // Préserver les images secondaires dans le résultat
        result.secondaryImages = result.secondaryImages || secondaryImages;
        
        // Mise à jour uniquement de l'image principale
        updateMainImagePreview(result);
        
    } catch (error) {
        console.error('❌ Erreur:', error);
        showToast('Erreur lors de l\'upload de l\'image', 'error');
    }
}

// Fonction pour gérer l'upload des images secondaires
async function handleSecondaryImagesUpload(event) {
    const files = event.target.files;
    if (!files || files.length === 0) return;
    
    // Vérifier la taille de chaque fichier
    for (let file of files) {
        if (file.size > 2 * 1024 * 1024) {
            showToast(`L'image ${file.name} ne doit pas dépasser 2Mo`, 'warning');
            event.target.value = '';
            return;
        }
    }
    
    try {
        const formData = new FormData(document.getElementById('createEventForm'));
        
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Erreur lors de l\'upload');
        }
        
        // Mise à jour uniquement des images secondaires
        updateSecondaryImagesPreview(result);
        
    } catch (error) {
        console.error('❌ Erreur:', error);
        showToast('Erreur lors de l\'upload des images', 'error');
        event.target.value = '';
    }
}

// Fonction pour gérer la suppression de l'image principale
async function handleMainImageDelete(event) {
    const formData = new FormData();
    const draftId = document.querySelector('input[name="draftId"]')?.value;
    if (draftId) {
        formData.append('draftId', draftId);
        formData.append('deleteMainImage', '1');
    }

    try {
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Erreur lors de la suppression');
        }

        // Réinitialiser l'aperçu
        const mainPreview = document.getElementById('mainImagePreview');
        if (mainPreview) {
            mainPreview.src = '';
            mainPreview.style.display = 'none';
        }

        // Supprimer le bouton de suppression
        const removeBtn = document.querySelector('.main-image-container .remove-image-btn');
        if (removeBtn) {
            removeBtn.remove();
        }

        // Réinitialiser l'input file
        const mainImageInput = document.getElementById('mainImage');
        if (mainImageInput) {
            mainImageInput.value = '';
        }

    } catch (error) {
        console.error('❌ Erreur lors de la suppression:', error);
        showToast('Erreur lors de la suppression de l\'image', 'error');
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 Initialisation des gestionnaires d\'événements');
    
    // Gestionnaire pour l'image principale
    const mainImageInput = document.getElementById('mainImage');
    if (mainImageInput) {
        mainImageInput.addEventListener('change', handleMainImageUpload);
    }
    
    // Gestionnaire pour les images secondaires
    const secondaryImagesInput = document.getElementById('secondaryImages');
    if (secondaryImagesInput) {
        secondaryImagesInput.addEventListener('change', handleSecondaryImagesUpload);
    }
});

// Export des fonctions
window.handleMainImageUpload = handleMainImageUpload;
window.handleSecondaryImagesUpload = handleSecondaryImagesUpload;
