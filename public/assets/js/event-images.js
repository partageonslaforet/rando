const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
const MAX_SECONDARY_IMAGES = 3;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
const ACCEPTED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

function updateDraftId(draftId) {
    if (!draftId) return;
    const input = document.querySelector('input[name="draftId"]') || document.getElementById('draftId');
    if (input) input.value = draftId;
    window.draftId = draftId;
}

function validateImageFile(file) {
    if (!file) return 'Aucun fichier sélectionné';
    if (file.size > MAX_IMAGE_SIZE) {
        return `L'image ${file.name} dépasse la taille maximale de 5 Mo`;
    }
    const ext = file.name.split('.').pop().toLowerCase();
    if (!ACCEPTED_IMAGE_EXTENSIONS.includes(ext) || !ACCEPTED_IMAGE_TYPES.includes(file.type)) {
        return `Le fichier ${file.name} n'est pas une image autorisée (jpg, png, gif, webp)`;
    }
    return null;
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

        if (mainImageContainer) {
            mainImageContainer.classList.remove('has-image');
            const removeButton = mainImageContainer.querySelector('.remove-image-btn');
            if (removeButton) removeButton.remove();
        }

    } catch (error) {
        console.error('❌ Erreur suppression image principale:', error);
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
                previewContainer.style.display = '';
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

        // Marquer le conteneur comme occupé
        mainImageContainer.classList.add('has-image');

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
        id: String(img.dataset.imageId || ''),
        url: img.src
    }));
    console.log('📌 Images secondaires existantes:', existingImages);

    if (result.secondaryImages && result.secondaryImages.length > 0) {
        console.log('🖼️ Nouvelles images secondaires reçues:', result.secondaryImages);

        // Fusionner les nouvelles images avec les existantes
        const allImages = [...existingImages];
        result.secondaryImages.forEach(newImage => {
            const newId = String(newImage.id || '');
            if (!allImages.some(img => img.id === newId)) {
                allImages.push({ ...newImage, id: newId });
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
                img.src = image.path || image.url;
                img.dataset.imageId = image.id;
                img.className = 'img-fluid';
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-image-btn';
                removeBtn.type = 'button';
                removeBtn.innerHTML = '×';
                removeBtn.addEventListener('click', (e) => removeSecondaryImage(e, image.id));

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

    const validationError = validateImageFile(file);
    if (validationError) {
        showToast(validationError, 'warning');
        event.target.value = '';
        return;
    }

    const form = document.getElementById('createEventForm');
    const formData = form ? new FormData(form) : new FormData();
    formData.set('mainImage', file);

    try {
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Erreur lors de l\'upload');
        }

        updateDraftId(result.draftId);
        updateMainImagePreview(result);
        updateSecondaryImagesPreview(result);

        event.target.value = '';

    } catch (error) {
        console.error('❌ Erreur:', error);
        showToast(error.message || 'Erreur lors de l\'upload de l\'image', 'error');
    }
}

// Fonction pour gérer l'upload des images secondaires
async function handleSecondaryImagesUpload(event) {
    const files = event.target.files;
    if (!files || files.length === 0) return;

    const existingCount = document.querySelectorAll('#secondaryImagesPreview img').length;
    if (existingCount + files.length > MAX_SECONDARY_IMAGES) {
        showToast(`Vous pouvez ajouter jusqu'à ${MAX_SECONDARY_IMAGES} images secondaires`, 'warning');
        event.target.value = '';
        return;
    }

    for (let file of files) {
        const validationError = validateImageFile(file);
        if (validationError) {
            showToast(validationError, 'warning');
            event.target.value = '';
            return;
        }
    }

    const form = document.getElementById('createEventForm');
    if (!form) {
        showToast('Formulaire introuvable', 'error');
        event.target.value = '';
        return;
    }

    const formData = new FormData(form);

    try {
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Erreur lors de l\'upload');
        }

        updateDraftId(result.draftId);
        updateSecondaryImagesPreview(result);

        event.target.value = '';

    } catch (error) {
        console.error('❌ Erreur:', error);
        showToast(error.message || 'Erreur lors de l\'upload des images', 'error');
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

        const mainImageContainer = document.querySelector('.main-image-container');
        if (mainImageContainer) {
            mainImageContainer.classList.remove('has-image');
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
document.addEventListener('DOMContentLoaded', async function() {
    console.log('🔄 Initialisation des gestionnaires d\'événements');
    // Pré-chargement des images d'un brouillon existant
    try {
        const draftIdInput = document.getElementById('draftId');
        const draftId = draftIdInput && draftIdInput.value ? parseInt(draftIdInput.value, 10) : 0;
        if (draftId) {
            const res = await fetch('/api/events/get_draft_images.php?draft_id=' + encodeURIComponent(draftId));
            const data = await res.json();
            if (data && data.success) {
                const payload = {
                    mainImage: data.mainImage ? { path: data.mainImage.path, id: data.mainImage.id } : null,
                    secondaryImages: (data.secondaryImages || []).map(i => ({ id: i.id, path: i.path }))
                };
                updateMainImagePreview(payload);
                updateSecondaryImagesPreview(payload);
            }
        }
    } catch (e) {
        console.warn('[event-images] Préchargement images brouillon échoué:', e);
    }
    
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
