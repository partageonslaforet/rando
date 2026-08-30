// Variables globales
let currentStep = 1;
let totalSteps = 3; // Modification : on passe à 3 étapes au lieu de 5
let mapInitialized = false;
let currentGpxLayers = {};
let previewModalInstance = null;

// Fonction pour initialiser la modale de prévisualisation
function initPreviewModal() {
    console.log(" Initialisation de la modale de prévisualisation");
    const previewModal = document.getElementById('previewModal');
    
    if (!previewModal) {
        console.error(" Élément #previewModal non trouvé dans le DOM");
        return null;
    }

    if (!window.bootstrap) {
        console.error(" Bootstrap n'est pas chargé");
        return null;
    }

    try {
        // Vérifier si une instance existe déjà
        const existingModal = bootstrap.Modal.getInstance(previewModal);
        if (existingModal) {
            console.log(" Instance de modale existante trouvée");
            return existingModal;
        }

        // Créer une nouvelle instance
        const modalInstance = new bootstrap.Modal(previewModal, {
            backdrop: 'static',
            keyboard: false,
            focus: true
        });

        console.log(" Nouvelle instance de modale créée");
        return modalInstance;
    } catch (error) {
        console.error(" Erreur lors de l'initialisation de la modale:", error);
        return null;
    }
}

// Fonction pour afficher la prévisualisation
async function showPreview() {
    try {
        hideGlobalErrors();
        
        const form = document.getElementById('createEventForm');
        const formData = new FormData(form);

        // Combiner latitude et longitude en coordonnées
        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;
        if (lat && lng) {
            formData.set('coordinates', `${lat},${lng}`);
        }

        // Ajouter les contacts
        const contactsContainer = document.getElementById('contactsContainer');
        if (contactsContainer) {
            const contacts = [];
            const contactDivs = contactsContainer.getElementsByClassName('contact-field');
            
            Array.from(contactDivs).forEach((div, index) => {
                const name = div.querySelector('[name^="contact_name"]')?.value || '';
                const email = div.querySelector('[name^="contact_email"]')?.value || '';
                const phone = div.querySelector('[name^="contact_phone"]')?.value || '';
                
                if (name || email || phone) {
                    contacts.push({ name, email, phone });
                }
            });
            
            if (contacts.length > 0) {
                // S'assurer que les contacts sont envoyés comme une chaîne JSON
                const contactsJson = JSON.stringify(contacts);
                formData.append('contacts', contactsJson);
                console.log(" ✓ Contacts ajoutés (JSON):", contactsJson);
            }
        }

        // Ajouter les parcours
        const routesContainer = document.getElementById('routesContainer');
        if (routesContainer) {
            const routes = [];
            const routeDivs = routesContainer.getElementsByClassName('route-field');
            
            Array.from(routeDivs).forEach((div, index) => {
                const name = div.querySelector('[name^="route_name"]')?.value || '';
                const distance = div.querySelector('[name^="route_distance"]')?.value || '';
                const elevation = div.querySelector('[name^="route_elevation"]')?.value || '';
                const gpxInput = div.querySelector('[name^="route_gpx"]');
                const downloadable = div.querySelector('[name^="route_gpx_downloadable"]')?.checked || false;
                
                if (name || distance || elevation || (gpxInput && gpxInput.files[0])) {
                    const route = { 
                        name, 
                        distance, 
                        elevation,  // Changé de elevation_gain à elevation pour correspondre à la structure de la base de données
                        gpx_downloadable: downloadable
                    };
                    routes.push(route);
                    
                    // Ajouter le fichier GPX s'il existe
                    if (gpxInput && gpxInput.files[0]) {
                        formData.append(`route_gpx_${index}`, gpxInput.files[0]);
                    }
                }
            });
            
            if (routes.length > 0) {
                // Convertir en JSON et ajouter au formData
                const routesJson = JSON.stringify(routes);
                formData.append('routes', routesJson);
                console.log(" ✓ Parcours ajoutés (JSON):", routesJson);
            }
        }

        // Ajouter les images secondaires
        const secondaryImagesInput = document.getElementById('secondary_images');
        if (secondaryImagesInput && secondaryImagesInput.files.length > 0) {
            Array.from(secondaryImagesInput.files).forEach((file, index) => {
                formData.append(`secondary_images[${index}]`, file);
            });
            console.log(" ✓ Images secondaires ajoutées:", secondaryImagesInput.files.length);
        }
        
        // Log pour le débogage
        console.log(" Données du formulaire :");
        for (let [key, value] of formData.entries()) {
            if (value instanceof File) {
                console.log(`  ${key} => File: ${value.name}`);
            } else {
                console.log(`  ${key} => ${value}`);
            }
        }
        
        // Ajouter les données de l'organisateur
        const organizerSelect = document.getElementById('organizerSelect');
        const organizerId = organizerSelect ? organizerSelect.value : null;
        
        if (organizerId && organizerId !== "") {
            formData.append('organizerId', organizerId);
            console.log(" ✓ ID de l'organisateur ajouté:", organizerId);
        } else {
            console.log(" ℹ️ Pas d'organisateur existant sélectionné, création d'un nouvel organisateur");
            
            // Pour un nouvel organisateur, on envoie tous les champs
            const organizerName = document.getElementById('organizerName');
            const organizerEmail = document.getElementById('organizerEmail');
            const organizerAddress = document.getElementById('organizerAddress');
            const organizerDescription = document.getElementById('organizerDescription');
            const organizerWebsite = document.getElementById('organizerWebsite');
            const organizerPhone = document.getElementById('organizerPhone');
            
            if (organizerName) formData.append('organizerName', organizerName.value);
            if (organizerEmail) formData.append('organizerEmail', organizerEmail.value);
            if (organizerAddress) formData.append('organizerAddress', organizerAddress.value);
            if (organizerDescription) formData.append('organizerDescription', organizerDescription.value);
            if (organizerWebsite) formData.append('organizerWebsite', organizerWebsite.value);
            if (organizerPhone) formData.append('organizerPhone', organizerPhone.value);
            
            console.log(" ✓ Données du nouvel organisateur ajoutées");
        }

        // Ajouter le logo de l'organisateur s'il existe
        const logoInput = document.getElementById('organizerLogo');
        if (logoInput && logoInput.files[0]) {
            formData.append('organizerLogo', logoInput.files[0]);
            console.log(" ✓ Logo de l'organisateur ajouté");
        }
        
        // 1. Enregistrer d'abord le brouillon
        console.log(" Enregistrement du brouillon...");
        
        // Ajouter le draftId s'il existe déjà
        const hiddenDraftId = document.querySelector('input[name="draftId"]');
        if (hiddenDraftId && hiddenDraftId.value) {
            formData.append('draftId', hiddenDraftId.value);
            console.log(" ℹ️ Utilisation du brouillon existant:", hiddenDraftId.value);
        }
        
        const saveResponse = await fetch('../../api/events/save_draft.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        if (!saveResponse.ok) {
            const errorText = await saveResponse.text();
            console.error(" ❌ Erreur HTTP:", saveResponse.status, errorText);
            throw new Error(`Erreur lors de l'enregistrement: ${saveResponse.status}`);
        }

        const saveData = await saveResponse.json();
        console.log(" Réponse de save_draft.php:", saveData);
        
        if (!saveData.success) {
            throw new Error(saveData.message || "Erreur lors de l'enregistrement du brouillon");
        }

        const draftId = saveData.draftId;
        if (!draftId) {
            console.error(" ❌ Pas de draftId dans la réponse:", saveData);
            throw new Error("ID du brouillon manquant dans la réponse");
        }
        
        console.log(" ✓ Brouillon enregistré avec ID:", draftId);
        
        // Mettre à jour le champ caché
        if (hiddenDraftId) {
            hiddenDraftId.value = draftId;
        } else {
            const newHiddenInput = document.createElement('input');
            newHiddenInput.type = 'hidden';
            newHiddenInput.name = 'draftId';
            newHiddenInput.value = draftId;
            document.getElementById('createEventForm').appendChild(newHiddenInput);
        }

        // 2. Récupérer la prévisualisation
        console.log(" Récupération de la prévisualisation...");
        const requestData = { draftId: draftId };
        console.log(" Données envoyées:", requestData);
        
        const previewResponse = await fetch('../../api/events/preview.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestData)
        });

        if (!previewResponse.ok) {
            console.error(" ❌ Erreur HTTP:", previewResponse.status);
            const errorText = await previewResponse.text();
            console.error(" Réponse d'erreur:", errorText);
            throw new Error(`Erreur lors de la prévisualisation: ${previewResponse.status}`);
        }

        const previewData = await previewResponse.json();
        console.log(" Réponse de prévisualisation:", previewData);
        
        if (!previewData.success) {
            throw new Error(previewData.message || "Erreur lors de la prévisualisation");
        }

        // Mettre à jour le contenu de la prévisualisation
        const previewContent = document.getElementById('eventPreview');
        if (!previewContent) {
            throw new Error("L'élément de prévisualisation n'existe pas");
        }

        previewContent.innerHTML = previewData.data.html;
        console.log(" ✓ Prévisualisation affichée avec succès");

    } catch (error) {
        console.error(" Erreur:", error);
        showToast(error.message || "Une erreur est survenue", "error");
    }
}

// Fonction pour passer à l'étape suivante
async function nextStep() {
    console.log(' Passage à l\'étape suivante');
    if (currentStep < totalSteps) {
        if (validateStep(currentStep)) {
            try {
                // Si on passe à l'étape 3 (prévisualisation)
                if (currentStep === 2) {
                    console.log(' Étape de prévisualisation, appel de showPreview()');
                    
                    // Cacher l'étape actuelle
                    const currentStepElement = document.getElementById(`step${currentStep}`);
                    if (currentStepElement) {
                        currentStepElement.classList.add('d-none');
                    }
                    
                    // Afficher l'étape suivante
                    currentStep++;
                    const nextStepElement = document.getElementById(`step${currentStep}`);
                    if (nextStepElement) {
                        nextStepElement.classList.remove('d-none');
                        // S'assurer que le conteneur de prévisualisation est visible
                        const previewContainer = document.getElementById('eventPreview');
                        if (previewContainer) {
                            previewContainer.classList.remove('d-none');
                        }
                        
                        // Afficher le bouton de publication
                        const publishButton = document.getElementById('publishButton');
                        if (publishButton) {
                            publishButton.classList.remove('d-none');
                        }
                    }
                    
                    // Afficher la prévisualisation
                    await showPreview();
                    
                    // Mettre à jour la progression
                    updateProgress();
                    updateButtons();
                    hideGlobalErrors();
                    return;
                }
                
                // Pour les autres étapes
                const currentStepElement = document.getElementById(`step${currentStep}`);
                if (currentStepElement) {
                    currentStepElement.classList.add('d-none');
                }
                
                currentStep++;
                const nextStepElement = document.getElementById(`step${currentStep}`);
                if (nextStepElement) {
                    nextStepElement.classList.remove('d-none');
                }
                
                updateProgress();
                updateButtons();
                hideGlobalErrors();
            } catch (error) {
                console.error(' Erreur lors du passage à l\'étape suivante:', error);
                showToast("Une erreur est survenue lors du passage à l'étape suivante", "error");
            }
        }
    }
}

// Fonction pour revenir à l'étape précédente
function prevStep() {
    if (currentStep <= 1) return;
    
    try {
        // Cacher l'étape actuelle
        const currentStepElement = document.getElementById(`step${currentStep}`);
        if (currentStepElement) {
            currentStepElement.classList.add('d-none');
        }
        
        // Décrémenter et afficher la nouvelle étape
        currentStep--;
        const previousStepElement = document.getElementById(`step${currentStep}`);
        if (previousStepElement) {
            previousStepElement.classList.remove('d-none');
        }
        
        // Mettre à jour la barre de progression et les boutons
        updateProgress();
        updateButtons();
        hideGlobalErrors();
    } catch (error) {
        console.error(' Erreur lors du retour à l\'étape précédente:', error);
        showToast("Une erreur est survenue lors du retour à l'étape précédente", "error");
    }
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    console.log(" Chargement de la page");
    
    // Vérifier que nous sommes sur la bonne page
    const createEventForm = document.getElementById('createEventForm');
    if (!createEventForm) {
        console.log(" Pas sur la page de création d'événement");
        return;
    }

    // Pré-initialiser la modale
    previewModalInstance = initPreviewModal();
    if (previewModalInstance) {
        console.log(" Modale de prévisualisation initialisée");
    }
});

// Palette de couleurs pour les parcours
const routeColors = [
    '#FF0000', // Rouge
    '#00FF00', // Vert
    '#0000FF', // Bleu
    '#FFA500', // Orange
    '#800080', // Violet
    '#008080', // Turquoise
    '#FFD700', // Or
    '#FF69B4', // Rose
    '#4B0082', // Indigo
    '#32CD32'  // Vert lime
];

// Fonction pour gérer l'upload d'images
function handleImageUpload(input, previewId, maxSize = 5) {
    console.log(' Début de l\'upload d\'image', { previewId, maxSize });
    
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    if (!preview) {
        console.error(' Élément de prévisualisation non trouvé:', previewId);
        return;
    }
    
    const container = preview.parentElement;
    const uploadButton = container.querySelector('.image-upload-button');
    
    console.log('Fichier sélectionné:', file);
    console.log('Élément de prévisualisation:', preview);
    console.log('Bouton d\'upload:', uploadButton);
    
    const errorContainer = container.querySelector('.invalid-feedback') || document.createElement('div');
    
    if (!errorContainer.classList.contains('invalid-feedback')) {
        errorContainer.className = 'invalid-feedback';
        container.appendChild(errorContainer);
    }

    if (file) {
        console.log('Vérification du fichier:', {
            name: file.name,
            type: file.type,
            size: `${(file.size / 1024 / 1024).toFixed(2)}MB`
        });

        // Vérifier la taille du fichier (en MB)
        if (file.size > maxSize * 1024 * 1024) {
            console.error(' Fichier trop volumineux');
            input.value = '';
            errorContainer.textContent = `L'image ne doit pas dépasser ${maxSize}MB`;
            errorContainer.style.display = 'block';
            return;
        }

        // Vérifier le type de fichier
        if (!file.type.startsWith('image/')) {
            console.error(' Type de fichier non valide');
            input.value = '';
            errorContainer.textContent = 'Veuillez sélectionner une image';
            errorContainer.style.display = 'block';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            console.log(' Image chargée avec succès');
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (uploadButton) {
                uploadButton.style.display = 'none';
            }
            errorContainer.style.display = 'none';

            // Ajouter le bouton de suppression
            let removeButton = container.querySelector('.remove-image');
            if (!removeButton) {
                removeButton = document.createElement('button');
                removeButton.className = 'remove-image';
                removeButton.innerHTML = '<i class="bi bi-x"></i>';
                removeButton.onclick = function() {
                    preview.src = '';
                    preview.style.display = 'none';
                    input.value = '';
                    this.style.display = 'none';
                    if (uploadButton) {
                        uploadButton.style.display = 'flex';
                    }
                };
                container.appendChild(removeButton);
            }
            removeButton.style.display = 'flex';
        };
        
        reader.onerror = function(e) {
            console.error(' Erreur lors de la lecture du fichier:', e);
            errorContainer.textContent = 'Erreur lors de la lecture du fichier';
            errorContainer.style.display = 'block';
            preview.src = '';
            preview.style.display = 'none';
            if (uploadButton) {
                uploadButton.style.display = 'flex';
            }
        };
        
        console.log('Début de la lecture du fichier...');
        reader.readAsDataURL(file);
    } else {
        console.log('Aucun fichier sélectionné');
        preview.src = '';
        preview.style.display = 'none';
        if (uploadButton) {
            uploadButton.style.display = 'flex';
        }
        
        const removeButton = container.querySelector('.remove-image');
        if (removeButton) {
            removeButton.style.display = 'none';
        }
    }
}

// Fonction pour gérer l'upload du logo
function handleLogoUpload(input) {
    console.log('Début du chargement du logo...');
    
    if (!input.files || !input.files[0]) {
        console.error('Aucun fichier logo sélectionné');
        return;
    }

    const file = input.files[0];
    const maxSize = 2; // 2 Mo maximum

    // Vérifier la taille du fichier
    if (file.size > maxSize * 1024 * 1024) {
        showToast(`Le logo ne doit pas dépasser ${maxSize}Mo`, 'danger');
        input.value = '';
        return;
    }

    // Vérifier le type de fichier
    if (!file.type.startsWith('image/')) {
        showToast('Le fichier doit être une image', 'danger');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    const preview = document.getElementById('logoPreview');

    reader.onload = function(e) {
        console.log('Logo chargé avec succès');
        preview.src = e.target.result;
        preview.style.display = 'block';
        
        // Créer une image temporaire pour vérifier les dimensions
        const tempImg = new Image();
        tempImg.onload = function() {
            // Vérifier les dimensions de l'image
            if (tempImg.width < 100 || tempImg.height < 100) {
                showToast('Le logo doit faire au moins 100x100 pixels', 'warning');
            }
            if (tempImg.width > 1000 || tempImg.height > 1000) {
                showToast('Le logo ne doit pas dépasser 1000x1000 pixels', 'warning');
            }
        };
        tempImg.src = e.target.result;
    };

    reader.onerror = function() {
        console.error('Erreur lors de la lecture du fichier');
        showToast('Erreur lors du chargement du logo', 'danger');
        input.value = '';
    };

    console.log('Lecture du fichier logo...');
    reader.readAsDataURL(file);
}

// Fonction pour gérer l'upload des images secondaires
function handleSecondaryImagesUpload(event) {
    console.log(' Début de l\'upload d\'images secondaires');
    
    const input = event.target;
    const files = input.files;
    if (!files) {
        console.error('Aucun fichier sélectionné');
        return;
    }

    const previewGrid = document.getElementById('secondaryImagesPreview');
    const maxImages = 3;
    const currentImages = previewGrid.children.length;
    const remainingSlots = maxImages - currentImages;
    
    if (files.length > remainingSlots) {
        showToast(`Vous ne pouvez ajouter que ${remainingSlots} image${remainingSlots > 1 ? 's' : ''} supplémentaire${remainingSlots > 1 ? 's' : ''}`, 'warning');
        return;
    }

    // Afficher la grille de prévisualisation si elle était cachée
    previewGrid.style.display = 'flex';

    // Stocker les fichiers dans un tableau pour une utilisation ultérieure
    if (!window.secondaryImagesFiles) {
        window.secondaryImagesFiles = [];
    }

    Array.from(files).forEach((file, index) => {
        if (currentImages + index >= maxImages) return;

        // Ajouter le fichier au tableau
        window.secondaryImagesFiles.push(file);

        const container = document.createElement('div');
        container.className = 'secondary-image-container';
        container.dataset.fileIndex = window.secondaryImagesFiles.length - 1;
        
        const img = document.createElement('img');
        const removeButton = document.createElement('button');
        removeButton.className = 'remove-image';
        removeButton.innerHTML = '<i class="bi bi-x"></i>';
        
        removeButton.onclick = function() {
            // Supprimer le fichier du tableau
            const fileIndex = parseInt(container.dataset.fileIndex);
            window.secondaryImagesFiles.splice(fileIndex, 1);
            // Mettre à jour les indices des autres conteneurs
            const containers = previewGrid.getElementsByClassName('secondary-image-container');
            Array.from(containers).forEach((c, i) => {
                if (parseInt(c.dataset.fileIndex) > fileIndex) {
                    c.dataset.fileIndex = parseInt(c.dataset.fileIndex) - 1;
                }
            });
            container.remove();
            updateSecondaryImagesButton();
        };

        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            container.appendChild(img);
            container.appendChild(removeButton);
            previewGrid.appendChild(container);
            updateSecondaryImagesButton();
        };
        reader.readAsDataURL(file);
    });
}

// Fonction pour ajouter un champ de contact
function addContactField() {
    const container = document.getElementById('contacts-container');
    const index = container.children.length;
    
    const contactField = document.createElement('div');
    contactField.className = 'contact-field mb-3 border p-3 rounded';
    contactField.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Contact ${index + 1}</h5>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeContact(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="mb-2">
            <label class="form-label">Nom</label>
            <input type="text" class="form-control" name="contacts[${index}][name]">
        </div>
        <div class="mb-2">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="contacts[${index}][email]">
        </div>
        <div class="mb-2">
            <label class="form-label">Téléphone</label>
            <input type="tel" class="form-control" name="contacts[${index}][phone]">
        </div>
    `;
    
    container.appendChild(contactField);
}

// Fonction pour supprimer un contact
function removeContact(button) {
    button.closest('.contact-field').remove();
}

// Fonction pour initialiser les cartes
function initializeMaps() {
    console.log('Initialisation des cartes...');

    // Initialiser la carte de localisation
    const locationMapElement = document.getElementById('locationMap');
    console.log('Élément locationMap:', locationMapElement);

    if (locationMapElement) {
        console.log('Création de la carte de localisation');
        console.log(' Appel de initLocationMap, vérification de locationMap...');
        console.log('État de locationMap avant l\'appel:', window.locationMap);
        initLocationMap();
        console.log(' Retour de initLocationMap, vérification de locationMap...');
        console.log('État de locationMap après l\'appel:', window.locationMap);
    }

    // Initialiser la carte GPX
    const gpxMapElement = document.getElementById('gpxMap');
    console.log('Élément gpxMap:', gpxMapElement);

    if (gpxMapElement) {
        console.log('Création de la carte GPX');
        initGpxMap();
    } else {
        console.log(' Élément gpxMap non trouvé');
    }
}

// Fonction pour gérer le téléchargement d'un fichier GPX
function handleGpxUpload(input, routeIndex) {
    console.log(' Traitement du fichier GPX pour le parcours', routeIndex);
    
    const file = input.files[0];
    if (!file) {
        console.log('Aucun fichier sélectionné');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const gpxData = e.target.result;
            
            // Créer un élément temporaire pour parser le GPX
            const parser = new DOMParser();
            const gpx = parser.parseFromString(gpxData, 'text/xml');
            
            // Vérifier si le document est bien un GPX
            if (gpx.documentElement.nodeName !== 'gpx') {
                throw new Error('Le fichier n\'est pas un GPX valide');
            }

            // Extraire les points du parcours
            const points = Array.from(gpx.getElementsByTagName('trkpt'));
            if (points.length < 2) {
                throw new Error('Le fichier GPX ne contient pas assez de points');
            }

            // Calculer la distance et le dénivelé
            let distance = 0;
            let elevation = 0;
            const coordinates = [];
            
            for (let i = 1; i < points.length; i++) {
                const prev = points[i - 1];
                const curr = points[i];
                
                const lat1 = parseFloat(prev.getAttribute('lat'));
                const lon1 = parseFloat(prev.getAttribute('lon'));
                const lat2 = parseFloat(curr.getAttribute('lat'));
                const lon2 = parseFloat(curr.getAttribute('lon'));
                
                coordinates.push([lat1, lon1]);
                if (i === points.length - 1) {
                    coordinates.push([lat2, lon2]);
                }
                
                // Calculer la distance entre les points
                distance += calculateHaversineDistance(lat1, lon1, lat2, lon2);
                
                // Calculer le dénivelé si disponible
                const ele1 = prev.getElementsByTagName('ele')[0];
                const ele2 = curr.getElementsByTagName('ele')[0];
                if (ele1 && ele2) {
                    const diff = parseFloat(ele2.textContent) - parseFloat(ele1.textContent);
                    if (diff > 0) elevation += diff;
                }
            }

            // Mettre à jour les champs du formulaire
            const distanceField = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
            const elevationField = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);
            
            if (distanceField) distanceField.value = distance.toFixed(1);
            if (elevationField) elevationField.value = Math.round(elevation);

            // Afficher le parcours sur la carte
            const color = routeColors[routeIndex % routeColors.length];
            
            // Supprimer l'ancien parcours s'il existe
            if (window.currentGpxLayers[routeIndex]) {
                window.gpxMap.removeLayer(window.currentGpxLayers[routeIndex]);
            }

            // Créer le nouveau parcours
            const polyline = L.polyline(coordinates, {
                color: color,
                weight: 3,
                opacity: 0.7
            });

            // Ajouter le nouveau parcours
            window.currentGpxLayers[routeIndex] = polyline;
            polyline.addTo(window.gpxMap);

            // Ajuster la vue de la carte pour montrer tous les parcours
            const bounds = polyline.getBounds();
            for (const layerId in window.currentGpxLayers) {
                if (layerId !== routeIndex.toString()) {
                    bounds.extend(window.currentGpxLayers[layerId].getBounds());
                }
            }
            window.gpxMap.flyToBounds(bounds, {
                padding: [50, 50],
                duration: 0.5
            });

            // Mettre à jour la légende
            updateGpxLegend();

            console.log(` Parcours ${routeIndex} chargé avec succès`);
        } catch (error) {
            console.error(' Erreur lors du traitement du fichier GPX:', error);
            showToast('Erreur lors du traitement du fichier GPX. Vérifiez que le fichier est valide.', 'error');
        }
    };

    reader.onerror = function() {
        console.error(' Erreur lors de la lecture du fichier');
        showToast('Erreur lors de la lecture du fichier', 'error');
    };

    reader.readAsText(file);
}

// Fonction pour mettre à jour la légende des GPX
function updateGpxLegend() {
    const legendContent = document.getElementById('gpx-legend-content');
    if (!legendContent) return;
    
    legendContent.innerHTML = '';
    
    Object.keys(window.currentGpxLayers).forEach(index => {
        const routeDiv = document.querySelector(`.route-field:nth-child(${parseInt(index) + 1})`);
        if (routeDiv) {
            const name = routeDiv.querySelector('input[name$="[name]"]').value || `Parcours ${parseInt(index) + 1}`;
            const distance = routeDiv.querySelector('input[name$="[distance]"]').value || '?';
            const elevation = routeDiv.querySelector('input[name$="[elevation]"]').value || '?';
            
            const color = routeColors[index % routeColors.length];
            const item = document.createElement('div');
            item.className = 'border rounded p-2';
            item.style.minWidth = '200px';
            item.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <span style="display: inline-block; width: 20px; height: 3px; background-color: ${color};"></span>
                    <span class="fw-medium">${name}</span>
                </div>
                <div class="d-flex align-items-center gap-2 ms-4 text-muted small">
                    <span>${distance} km</span>
                    <span>|</span>
                    <span>${elevation} m D+</span>
                </div>
            `;
            legendContent.appendChild(item);
        }
    });
}

// Fonction pour ajouter un parcours
function addRoute() {
    const container = document.getElementById('routes-container');
    const routeIndex = container.children.length;
    
    const routeDiv = document.createElement('div');
    routeDiv.className = 'route-field mb-3 border p-3 rounded';
    routeDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Parcours ${routeIndex + 1}</h5>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRoute(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="mb-2">
            <label class="form-label required-field">Nom du parcours</label>
            <input type="text" class="form-control" name="routes[${routeIndex}][name]" required>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="mb-2">
                    <label class="form-label">Distance (km)</label>
                    <input type="number" step="0.1" class="form-control" name="routes[${routeIndex}][distance]">
                    <div class="form-text">Calculé automatiquement si un fichier GPX est fourni</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-2">
                    <label class="form-label">Dénivelé (m)</label>
                    <input type="number" class="form-control" name="routes[${routeIndex}][elevation]">
                    <div class="form-text">Calculé automatiquement si un fichier GPX est fourni</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-2">
                    <label class="form-label required-field">Prix (€)</label>
                    <input type="number" step="0.01" class="form-control" name="routes[${routeIndex}][price]" required>
                </div>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label">Fichier GPX</label>
            <input type="file" class="form-control" name="routes[${routeIndex}][gpx]" accept=".gpx" onchange="handleGpxUpload(this, ${routeIndex})">
        </div>
        <div class="mb-2">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="gpx_downloadable_${routeIndex}" name="routes[${routeIndex}][gpx_downloadable]" value="1">
                <label class="form-check-label" for="gpx_downloadable_${routeIndex}">Autoriser le téléchargement du GPX</label>
            </div>
        </div>
    `;
    
    container.appendChild(routeDiv);
}

// Fonction pour supprimer un parcours
function removeRoute(button) {
    button.closest('.route-field').remove();
}

// Fonction pour valider tout le formulaire
function validateForm() {
    console.log(" Début de la validation du formulaire");
    const errors = [];
    
    // Liste des champs requis avec leurs messages d'erreur
    const requiredFields = {
        'title': 'Titre de l\'événement',
        'date': 'Date',
        'startTime': 'Heure de début',
        'location_name': 'Nom du local',
        'category': 'Catégorie',
        'organizerName': 'Nom de l\'organisation',
        'organizerEmail': 'Email de l\'organisation'
    };

    // Vérifier chaque champ requis
    for (const [fieldId, fieldName] of Object.entries(requiredFields)) {
        const field = document.querySelector(`[name="${fieldId}"]`);
        console.log(`Vérification du champ ${fieldId}:`, field);
        if (!field || !field.value) {
            errors.push(`Le champ "${fieldName}" est requis`);
        }
    }

    if (errors.length > 0) {
        console.log(" Validation échouée avec les erreurs:", errors);
        showGlobalErrors(errors);
        return false;
    }

    console.log(" Validation réussie");
    return true;
}

// Fonction pour valider une étape
function validateStep(stepNumber) {
    console.log(" Début de la validation de l'étape", stepNumber);
    
    const stepContent = document.querySelector(`#step${stepNumber}`);
    console.log(" Élément étape trouvé:", stepContent);
    
    if (!stepContent) {
        console.error(" Étape non trouvée");
        return false;
    }

    const requiredFields = stepContent.querySelectorAll('[required]');
    console.log(" Nombre de champs requis trouvés:", requiredFields.length);
    
    let isValid = true;
    let errors = [];
    let invalidFields = [];

    requiredFields.forEach((field, index) => {
        console.log("\n Vérification du champ", index + 1, ":", field);
        
        // Retirer les classes de validation précédentes
        field.classList.remove('is-invalid', 'is-valid');
        
        // Récupérer le label du champ
        const label = field.closest('.form-group')?.querySelector('label')?.textContent 
                     || field.getAttribute('placeholder') 
                     || field.name;
        
        let fieldValid = false;
        
        // Validation selon le type de champ
        switch(field.type) {
            case 'file':
                fieldValid = field.files && field.files.length > 0;
                break;
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                fieldValid = emailRegex.test(field.value.trim());
                break;
            case 'tel':
                const phoneRegex = /^[+]?[(]?[0-9]{3}[)]?[-\s.]?[0-9]{3}[-\s.]?[0-9]{4,6}$/;
                fieldValid = field.value.trim() === '' || phoneRegex.test(field.value.trim());
                break;
            case 'url':
                try {
                    const url = new URL(field.value.trim());
                    fieldValid = url.protocol === "http:" || url.protocol === "https:";
                } catch {
                    fieldValid = field.value.trim() === '';
                }
                break;
            default:
                fieldValid = field.value.trim() !== '';
        }
        
        if (!fieldValid) {
            console.log(" Champ invalide:", label);
            field.classList.add('is-invalid');
            
            // Message d'erreur spécifique selon le type
            let errorMessage = `Le champ "${label}" est requis`;
            if (field.type === 'email') {
                errorMessage = `Veuillez entrer une adresse email valide`;
            } else if (field.type === 'tel') {
                errorMessage = `Veuillez entrer un numéro de téléphone valide`;
            } else if (field.type === 'url') {
                errorMessage = `Veuillez entrer une URL valide`;
            }
            
            errors.push(errorMessage);
            isValid = false;
            
            // Ajouter ou mettre à jour le message d'erreur
            let feedback = field.nextElementSibling;
            if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                field.parentNode.insertBefore(feedback, field.nextSibling);
            }
            feedback.textContent = errorMessage;
            feedback.style.display = 'block';
            feedback.style.color = 'var(--bs-primary)';
        } else {
            console.log(" Champ valide:", label);
            field.classList.add('is-valid');
        }
    });

    // Afficher les erreurs dans un toast
    if (!isValid) {
        showToast('Veuillez remplir tous les champs obligatoires', 'primary');
        
        // Faire défiler jusqu'au premier champ invalide
        if (invalidFields.length > 0) {
            invalidFields[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            invalidFields[0].focus();
        }
    }

    console.log(" Résultat de la validation:", isValid ? ' Valide' : ' Invalide');
    return isValid;
}

// Fonction pour mettre à jour la barre de progression
function updateProgress() {
    const progressBar = document.getElementById('progressBar');
    if (progressBar) {
        const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
        progressBar.style.width = `${progress}%`;
        progressBar.setAttribute('aria-valuenow', progress);
    }

    // Mettre à jour l'état des étapes
    document.querySelectorAll('.step').forEach((step, index) => {
        const stepNumber = index + 1;
        if (stepNumber < currentStep) {
            // Étapes terminées
            step.classList.add('completed');
            step.classList.remove('active');
        } else if (stepNumber === currentStep) {
            // Étape actuelle
            step.classList.add('active');
            step.classList.remove('completed');
        } else {
            // Étapes à venir
            step.classList.remove('active', 'completed');
        }
    });
}

// Fonction pour mettre à jour l'affichage des boutons
function updateButtons() {
    console.log(" Mise à jour des boutons. Étape actuelle:", currentStep);
    const nextButton = document.getElementById('nextButton');
    const prevButton = document.getElementById('prevButton');
    const publishButton = document.getElementById('publishButton');
    
    if (prevButton) {
        if (currentStep === 1) {
            prevButton.classList.add('d-none');
        } else {
            prevButton.classList.remove('d-none');
        }
    }

    if (nextButton) {
        if (currentStep === totalSteps) {
            nextButton.classList.add('d-none');
        } else {
            nextButton.classList.remove('d-none');
        }
    }

    if (publishButton) {
        console.log(" Gestion du bouton de publication à l'étape:", currentStep);
        if (currentStep === totalSteps) {
            publishButton.classList.remove('d-none');
            console.log(" Affichage du bouton de publication");
        } else {
            publishButton.classList.add('d-none');
            console.log(" Masquage du bouton de publication");
        }
    } else {
        console.error(" Bouton de publication non trouvé dans le DOM");
    }
}

// Fonction pour mettre à jour les boutons d'étape
function updateStepButtons() {
    const prevBtn = document.querySelector('.prev-step');
    const nextBtn = document.querySelector('.next-step');
    const submitBtn = document.querySelector('.submit-form');
    
    if (prevBtn) {
        prevBtn.style.display = currentStep > 1 ? 'block' : 'none';
    }
    
    if (nextBtn) {
        nextBtn.style.display = currentStep < totalSteps ? 'block' : 'none';
    }
    
    if (submitBtn) {
        submitBtn.style.display = currentStep === totalSteps ? 'block' : 'none';
    }
    
    // Mettre à jour la classe active des étapes
    document.querySelectorAll('.step-indicator').forEach((step, index) => {
        if (index + 1 === currentStep) {
            step.classList.add('active');
        } else {
            step.classList.remove('active');
        }
    });
}

// Fonction pour afficher un toast
function showToast(message, type = 'success') {
    // Créer l'élément toast
    const toastEl = document.createElement('div');
    toastEl.classList.add('toast', `bg-${type}`, 'text-white', 'position-fixed', 'top-50', 'start-50', 'translate-middle');
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
        <div class="toast-body text-center">
            <i class="bi bi-check-circle-fill me-2"></i>
            ${message}
        </div>
    `;
    document.body.appendChild(toastEl);
    
    // Initialiser et afficher le toast
    const toast = new bootstrap.Toast(toastEl, {
        animation: true,
        autohide: true,
        delay: 2000
    });
    
    // Afficher le toast
    toast.show();
    
    // Nettoyer le DOM après que le toast soit caché
    toastEl.addEventListener('hidden.bs.toast', () => {
        document.body.removeChild(toastEl);
    });
}

// Fonction pour initialiser les gestionnaires d'upload d'images
function initializeImageUploads() {
    console.log(" Initialisation des gestionnaires d'upload d'images");

    // Image principale
    const mainImageInput = document.getElementById('mainImage');
    if (mainImageInput) {
        console.log("Configuration de l'upload d'image principale");
        mainImageInput.addEventListener('change', function() {
            console.log("Changement détecté sur l'image principale");
            handleImageUpload(this, 'mainImagePreview');
        });
    }

    // Image secondaire
    const secondaryImageInput = document.getElementById('secondaryImages');
    if (secondaryImageInput) {
        console.log("Configuration de l'upload d'image secondaire");
        secondaryImageInput.addEventListener('change', handleSecondaryImagesUpload);
    }

    // Logo de l'organisateur
    const logoInput = document.getElementById('organizerLogo');
    if (logoInput) {
        console.log("Configuration de l'upload du logo");
        logoInput.addEventListener('change', function() {
            console.log("Changement détecté sur le logo");
            handleLogoUpload(this);
        });
    }

    // Initialiser les boutons de suppression
    document.querySelectorAll('.remove-image').forEach(button => {
        button.addEventListener('click', function() {
            const wrapper = this.closest('.preview-wrapper, .logo-preview-wrapper');
            if (wrapper) {
                const preview = wrapper.querySelector('img');
                const input = wrapper.parentElement.querySelector('input[type="file"]');
                if (preview) preview.src = '';
                if (input) input.value = '';
                wrapper.style.display = 'none';
            }
        });
    });
}

// Fonction pour initialiser les événements du formulaire
function initializeFormEvents() {
    console.log(" Initialisation des événements du formulaire");
    
    // Initialiser les boutons suivant
    const nextButtons = document.querySelectorAll('.btn-next');
    nextButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            console.log(" Clic sur le bouton suivant");
            nextStep();
        });
    });
    
    // Initialiser les boutons précédent
    const prevButtons = document.querySelectorAll('.btn-prev');
    prevButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            prevStep();
        });
    });
}

// Fonction pour initialiser le toggle du profil organisateur
function initializeProfileToggle() {
    const useProfileCheckbox = document.getElementById('useProfileInfo');
    const organizerFields = document.getElementById('organizerFields');
    const organizerSelect = document.getElementById('organizerSelect');
    const newOrganizerToggle = document.getElementById('newOrganizerToggle');
    
    if (!organizerFields) {
        console.log(" Éléments du profil organisateur non trouvés");
        return;
    }

    // Fonction pour mettre à jour la visibilité des champs
    function updateFieldsVisibility() {
        const isNewOrganizer = !organizerSelect || organizerSelect.value === '';
        const useProfile = useProfileCheckbox.checked;
        
        // Afficher/masquer le toggle de profil
        if (newOrganizerToggle) {
            newOrganizerToggle.style.display = isNewOrganizer ? 'block' : 'none';
        }
        
        // Activer/désactiver les champs
        const fields = organizerFields.querySelectorAll('input:not([type="file"]), textarea');
        fields.forEach(field => {
            field.disabled = !isNewOrganizer || useProfile;
            if (!isNewOrganizer || useProfile) {
                field.classList.remove('is-invalid');
            }
        });

        // Gérer le champ de logo
        const logoInput = document.getElementById('organizerLogo');
        const logoPreview = document.getElementById('logoPreview');
        if (logoInput) {
            logoInput.disabled = !isNewOrganizer || useProfile;
        }

        // Si un organisateur est sélectionné, charger ses informations
        if (organizerSelect && organizerSelect.value !== '') {
            loadOrganizerInfo(organizerSelect.value);
        } else if (useProfile) {
            // Si on utilise le profil utilisateur, charger les informations par défaut
            loadDefaultProfile();
        } else {
            // Réinitialiser les champs et le logo
            resetOrganizerFields();
        }
    }

    // Charger les informations d'un organisateur
    async function loadOrganizerInfo(organizerId) {
        try {
            console.log("Chargement des informations de l'organisateur:", organizerId);
            const response = await fetch(`/api/organizer/get_profile.php?id=${organizerId}`);
            const data = await response.json();
            
            if (data.success && data.profile) {
                console.log("Profil chargé:", data.profile);
                fillOrganizerFields(data.profile);
                if (data.profile.logo_path) {
                    console.log("Logo trouvé:", data.profile.logo_path);
                    displayLogo(data.profile.logo_path);
                } else {
                    console.log("Pas de logo trouvé");
                    resetLogo();
                }
            } else {
                console.error("Erreur lors du chargement du profil:", data.message);
                showToast("Erreur lors du chargement des informations de l'organisateur", "danger");
            }
        } catch (error) {
            console.error("Erreur lors du chargement des informations de l'organisateur:", error);
            showToast("Erreur lors du chargement des informations de l'organisateur", "danger");
        }
    }

    // Charger le profil par défaut de l'utilisateur
    async function loadDefaultProfile() {
        try {
            const response = await fetch('/api/organizer/get_profile.php');
            const data = await response.json();
            
            if (data.success && data.profile) {
                fillOrganizerFields(data.profile);
                if (data.profile.logo_path) {
                    displayLogo(data.profile.logo_path);
                }
            }
        } catch (error) {
            console.error("Erreur lors du chargement du profil par défaut:", error);
            showToast("Erreur lors du chargement du profil par défaut", "danger");
        }
    }

    // Afficher le logo
    function displayLogo(logoPath) {
        console.log("Affichage du logo:", logoPath);
        const logoPreview = document.getElementById('logoPreview');
        if (logoPreview) {
            logoPreview.src = logoPath;
            logoPreview.style.display = 'block';
            console.log("Logo affiché");
        } else {
            console.error("Élément logoPreview non trouvé");
        }
    }

    // Réinitialiser le logo
    function resetLogo() {
        console.log("Réinitialisation du logo");
        const logoPreview = document.getElementById('logoPreview');
        const logoInput = document.getElementById('organizerLogo');
        if (logoPreview) {
            logoPreview.src = '';
            logoPreview.style.display = 'none';
        }
        if (logoInput) {
            logoInput.value = '';
        }
        console.log("Logo réinitialisé");
    }

    // Remplir les champs avec les informations
    function fillOrganizerFields(profile) {
        document.getElementById('organizerName').value = profile.name || '';
        document.getElementById('organizerAddress').value = profile.address || '';
        document.getElementById('organizerDescription').value = profile.description || '';
        document.getElementById('organizerWebsite').value = profile.website || '';
        document.getElementById('organizerPhone').value = profile.phone || '';
        document.getElementById('organizerEmail').value = profile.email || '';
    }

    // Initialiser l'état des champs
    updateFieldsVisibility();

    // Ajouter les écouteurs d'événements
    if (useProfileCheckbox) {
        useProfileCheckbox.addEventListener('change', updateFieldsVisibility);
    }
    
    if (organizerSelect) {
        organizerSelect.addEventListener('change', updateFieldsVisibility);
    }
}

// Fonction pour mettre à jour le bouton d'images secondaires
function updateSecondaryImagesButton() {
    const previewGrid = document.getElementById('secondaryImagesPreview');
    const uploadButton = document.querySelector('label[for="secondaryImages"]');
    const maxImages = 3;
    
    if (previewGrid && uploadButton) {
        const currentImages = previewGrid.querySelectorAll('.secondary-image-container').length;
        uploadButton.style.display = currentImages >= maxImages ? 'none' : 'block';
    }
}

// Fonction pour initialiser la carte de localisation
function initLocationMap() {
    console.log(" Initialisation de la carte de localisation");
    
    const mapContainer = document.getElementById('locationMap');
    if (!mapContainer) {
        console.error(" Container de carte non trouvé");
        return;
    }

    try {
        // Créer la carte
        window.locationMap = L.map('locationMap').setView([50.4, 4.4], 8);
        
        // Ajouter le fond de carte OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(window.locationMap);
        
        // Ajouter le marqueur
        window.locationMarker = L.marker([50.4, 4.4], {
            draggable: true
        }).addTo(window.locationMap);
        
        // Gérer le déplacement du marqueur
        window.locationMarker.on('dragend', function(e) {
            updateAddress(e.target.getLatLng());
        });

        // Configurer la recherche d'adresse
        setupAddressSearch();

        console.log(" Carte initialisée avec succès");
    } catch (error) {
        console.error(" Erreur lors de l'initialisation de la carte:", error);
    }
}

// Fonction pour configurer la recherche d'adresse
function setupAddressSearch() {
    const searchButton = document.getElementById('searchAddressBtn');
    const addressInput = document.getElementById('address');

    if (!searchButton || !addressInput) {
        console.error(" Éléments de recherche non trouvés");
        return;
    }

    // Fonction de recherche
    const searchAddress = () => {
        const address = addressInput.value.trim();
        if (!address) return;

        console.log(" Recherche de l'adresse:", address);

        // Utiliser l'API Nominatim pour la recherche
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    const result = data[0];
                    const latlng = { lat: parseFloat(result.lat), lng: parseFloat(result.lon) };
                    
                    console.log(" Résultat trouvé:", result);
                    
                    // Mettre à jour le marqueur et la carte
                    window.locationMarker.setLatLng(latlng);
                    window.locationMap.flyTo(latlng, 16);
                    updateAddress(latlng);
                } else {
                    console.error(" Aucun résultat trouvé");
                    alert("Aucune adresse trouvée");
                }
            })
            .catch(error => {
                console.error(" Erreur lors de la recherche:", error);
                alert("Erreur lors de la recherche de l'adresse");
            });
    };

    // Gérer le clic sur le bouton de recherche
    searchButton.addEventListener('click', searchAddress);

    // Gérer la touche Entrée dans le champ d'adresse
    addressInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchAddress();
        }
    });
}

// Fonction pour mettre à jour l'adresse lors du déplacement du marqueur
function updateAddress(latlng) {
    console.log(" Mise à jour de l'adresse:", latlng);
    
    // Vérifier si les champs existent
    const latitudeField = document.getElementById('latitude');
    const longitudeField = document.getElementById('longitude');
    const addressField = document.getElementById('address');

    if (!latitudeField || !longitudeField || !addressField) {
        console.error(" Champs non trouvés");
        return;
    }

    // Mettre à jour les champs de latitude et longitude
    latitudeField.value = latlng.lat.toFixed(6);
    longitudeField.value = latlng.lng.toFixed(6);

    // Utiliser le geocoder pour obtenir l'adresse
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&addressdetails=1`)
        .then(response => response.json())
        .then(data => {
            console.log(" Données d'adresse reçues:", data);
            
            if (data && data.address) {
                const addr = data.address;
                const parts = [];

                // Construire l'adresse dans l'ordre
                if (addr.house_number) parts.push(addr.house_number);
                if (addr.road) parts.push(addr.road);
                if (addr.hamlet) parts.push(addr.hamlet);
                if (addr.suburb) parts.push(addr.suburb);
                if (addr.town || addr.city || addr.village) {
                    parts.push(addr.town || addr.city || addr.village);
                }
                if (addr.postcode) parts.push(addr.postcode);

                // Mettre à jour le champ d'adresse
                const fullAddress = parts.join(' ');
                console.log(" Adresse complète:", fullAddress);
                addressField.value = fullAddress || data.display_name;
            } else {
                console.error(" Données d'adresse invalides");
                addressField.value = '';
            }
        })
        .catch(error => {
            console.error(" Erreur lors de la récupération de l'adresse:", error);
        });
}

// Fonction pour initialiser la carte GPX
function initGpxMap() {
    console.log(" Initialisation de la carte GPX");
    
    const mapContainer = document.getElementById('gpxMap');
    if (!mapContainer) {
        console.error(" Container de la carte GPX non trouvé");
        return;
    }

    try {
        // Initialiser la carte
        window.gpxMap = L.map('gpxMap').setView([46.227638, 2.213749], 5); // Centre de la France
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: ' OpenStreetMap contributors'
        }).addTo(window.gpxMap);

        // Initialiser le conteneur des couches GPX
        window.currentGpxLayers = {};

        console.log(" Carte GPX initialisée avec succès");
    } catch (error) {
        console.error(" Erreur lors de l'initialisation de la carte GPX:", error);
    }
}

// Fonction pour traiter le fichier GPX
function handleGpxUpload(input, routeIndex) {
    console.log(" Traitement du fichier GPX pour le parcours", routeIndex);
    
    const file = input.files[0];
    if (!file) {
        console.log("Aucun fichier sélectionné");
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const gpxData = e.target.result;
            
            // Créer un élément temporaire pour parser le GPX
            const parser = new DOMParser();
            const gpx = parser.parseFromString(gpxData, 'text/xml');
            
            // Vérifier si le document est bien un GPX
            if (gpx.documentElement.nodeName !== 'gpx') {
                throw new Error('Le fichier n\'est pas un GPX valide');
            }

            // Extraire les points du parcours
            const points = Array.from(gpx.getElementsByTagName('trkpt'));
            if (points.length < 2) {
                throw new Error('Le fichier GPX ne contient pas assez de points');
            }

            // Calculer la distance et le dénivelé
            let distance = 0;
            let elevation = 0;
            const coordinates = [];
            
            for (let i = 1; i < points.length; i++) {
                const prev = points[i - 1];
                const curr = points[i];
                
                const lat1 = parseFloat(prev.getAttribute('lat'));
                const lon1 = parseFloat(prev.getAttribute('lon'));
                const lat2 = parseFloat(curr.getAttribute('lat'));
                const lon2 = parseFloat(curr.getAttribute('lon'));
                
                coordinates.push([lat1, lon1]);
                if (i === points.length - 1) {
                    coordinates.push([lat2, lon2]);
                }
                
                // Calculer la distance entre les points
                distance += calculateHaversineDistance(lat1, lon1, lat2, lon2);
                
                // Calculer le dénivelé si disponible
                const ele1 = prev.getElementsByTagName('ele')[0];
                const ele2 = curr.getElementsByTagName('ele')[0];
                if (ele1 && ele2) {
                    const diff = parseFloat(ele2.textContent) - parseFloat(ele1.textContent);
                    if (diff > 0) elevation += diff;
                }
            }

            // Mettre à jour les champs du formulaire
            const distanceField = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
            const elevationField = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);
            
            if (distanceField) distanceField.value = distance.toFixed(1);
            if (elevationField) elevationField.value = Math.round(elevation);

            // Afficher le parcours sur la carte
            const color = routeColors[routeIndex % routeColors.length];
            
            // Supprimer l'ancien parcours s'il existe
            if (window.currentGpxLayers[routeIndex]) {
                window.gpxMap.removeLayer(window.currentGpxLayers[routeIndex]);
            }

            // Créer le nouveau parcours
            const polyline = L.polyline(coordinates, {
                color: color,
                weight: 3,
                opacity: 0.7
            });

            // Ajouter le nouveau parcours
            window.currentGpxLayers[routeIndex] = polyline;
            polyline.addTo(window.gpxMap);

            // Ajuster la vue de la carte pour montrer tous les parcours
            const bounds = polyline.getBounds();
            for (const layerId in window.currentGpxLayers) {
                if (layerId !== routeIndex.toString()) {
                    bounds.extend(window.currentGpxLayers[layerId].getBounds());
                }
            }
            window.gpxMap.flyToBounds(bounds, {
                padding: [50, 50],
                duration: 0.5
            });

            // Mettre à jour la légende
            updateGpxLegend();

            console.log(` Parcours ${routeIndex} chargé avec succès`);
        } catch (error) {
            console.error(' Erreur lors du traitement du fichier GPX:', error);
            showToast('Erreur lors du traitement du fichier GPX. Vérifiez que le fichier est valide.', 'error');
        }
    };

    reader.onerror = function() {
        console.error(' Erreur lors de la lecture du fichier');
        showToast('Erreur lors de la lecture du fichier', 'error');
    };

    reader.readAsText(file);
}

// Fonction pour calculer la distance d'un parcours GPX
function calculateDistance(gpxData) {
    console.log(" Calcul de la distance du parcours");
    
    try {
        const parser = new DOMParser();
        const gpx = parser.parseFromString(gpxData, 'text/xml');
        const points = Array.from(gpx.getElementsByTagName('trkpt'));
        
        if (points.length < 2) {
            console.error(" Pas assez de points pour calculer la distance");
            return 0;
        }

        let distance = 0;
        for (let i = 1; i < points.length; i++) {
            const prev = points[i - 1];
            const curr = points[i];
            
            const lat1 = parseFloat(prev.getAttribute('lat'));
            const lon1 = parseFloat(prev.getAttribute('lon'));
            const lat2 = parseFloat(curr.getAttribute('lat'));
            const lon2 = parseFloat(curr.getAttribute('lon'));
            
            distance += calculateHaversineDistance(lat1, lon1, lat2, lon2);
        }
        
        console.log(` Distance calculée: ${distance.toFixed(2)} km`);
        return distance;
    } catch (error) {
        console.error(" Erreur lors du calcul de la distance:", error);
        return 0;
    }
}

// Fonction pour calculer la distance entre deux points (formule de Haversine)
function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Rayon de la Terre en km
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
             Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * 
             Math.sin(dLon/2) * Math.sin(dLon/2);
             
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Fonction pour convertir les degrés en radians
function toRad(degrees) {
    return degrees * Math.PI / 180;
}

// Fonction pour afficher les erreurs globales
function showGlobalErrors(errors) {
    const container = document.getElementById('globalErrorContainer');
    const list = container.querySelector('ul');
    list.innerHTML = '';
    
    errors.forEach(error => {
        const li = document.createElement('li');
        li.textContent = error;
        list.appendChild(li);
    });
    
    container.classList.add('show');
}

// Fonction pour masquer les erreurs globales
function hideGlobalErrors() {
    const container = document.getElementById('globalErrorContainer');
    container.classList.remove('show');
}

// Fonction pour afficher une erreur sur un champ spécifique
function showFieldError(field, message) {
    if (!field) {
        console.warn(" Tentative d'afficher une erreur sur un champ null:", message);
        return;
    }
    
    field.classList.add('is-invalid');
    
    // Créer ou mettre à jour le message d'erreur
    let errorDiv = field.nextElementSibling;
    if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        field.parentNode.insertBefore(errorDiv, field.nextSibling);
    }
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

// Fonction pour masquer l'erreur d'un champ
function hideFieldError(field) {
    field.classList.remove('is-invalid');
    const feedback = field.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.style.display = 'none';
    }
}

// Fonction pour réinitialiser les champs de l'organisateur
function resetOrganizerFields() {
    console.log("Réinitialisation des champs de l'organisateur");
    const organizerFields = document.getElementById('organizerFields');
    if (!organizerFields) {
        console.error("Élément organizerFields non trouvé");
        return;
    }

    // Réinitialiser tous les champs texte et textarea
    const fields = organizerFields.querySelectorAll('input:not([type="file"]), textarea');
    fields.forEach(field => {
        field.value = '';
        field.disabled = false;
        console.log("Champ réinitialisé:", field.name);
    });

    // Réinitialiser le logo
    resetLogo();
    
    console.log("Tous les champs ont été réinitialisés");
}

// Réinitialiser le logo
function resetLogo() {
    console.log("Réinitialisation du logo");
    const logoPreview = document.getElementById('logoPreview');
    const logoInput = document.getElementById('organizerLogo');
    if (logoPreview) {
        logoPreview.src = '';
        logoPreview.style.display = 'none';
    }
    if (logoInput) {
        logoInput.value = '';
    }
    console.log("Logo réinitialisé");
}

// Réinitialiser les champs de l'organisateur
function resetOrganizerFields() {
    console.log("Réinitialisation des champs de l'organisateur");
    const organizerFields = document.getElementById('organizerFields');
    if (!organizerFields) {
        console.error("Élément organizerFields non trouvé");
        return;
    }

    // Réinitialiser tous les champs texte et textarea
    const fields = organizerFields.querySelectorAll('input:not([type="file"]), textarea');
    fields.forEach(field => {
        field.value = '';
        field.disabled = false;
        console.log("Champ réinitialisé:", field.name);
    });

    // Réinitialiser le logo
    resetLogo();
    
    console.log("Tous les champs ont été réinitialisés");
}

document.addEventListener('DOMContentLoaded', function() {
    console.log(" Initialisation du JS");
    
    // Ne s'exécuter que sur la page de création d'événement
    const form = document.getElementById('createEventForm');
    if (!form) {
        console.log(" Pas sur la page de création d'événement");
        return;
    }

    console.log(" Page de création d'événement détectée");
    
    // Validation à la soumission
    form.addEventListener('submit', function(event) {
        console.log(" ⚡ Événement submit déclenché");
        if (!validateForm()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    // Validation en temps réel
    form.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('input', function() {
            if (this.checkValidity()) {
                this.classList.remove('is-invalid');
            }
        });
    });
    
    // Initialiser les gestionnaires d'événements
    initializeFormEvents();
    initializeProfileToggle();
    initializeImageUploads();
    initLocationMap();
    setupAddressSearch();
    initGpxMap();
    updateProgress();
    updateButtons();
    updateStepButtons();
    
    // Ajouter l'écouteur pour le bouton de publication
    const publishButton = document.getElementById('publishButton');
    console.log(" 🔍 Recherche du bouton de publication:", publishButton);
    
    if (publishButton) {
        console.log(" ✅ Bouton de publication trouvé");
        console.log("- Type:", publishButton.type);
        console.log("- HTML:", publishButton.outerHTML);
        
        // Supprimer les anciens listeners pour éviter les doublons
        publishButton.replaceWith(publishButton.cloneNode(true));
        const newPublishButton = document.getElementById('publishButton');
        
        // Ajouter le nouveau listener
        newPublishButton.addEventListener('click', async function(e) {
            console.log(" 🖱️ Clic sur le bouton de publication");
            console.log("- Event:", e);
            console.log("- Target:", e.target);
            console.log("- Current Target:", e.currentTarget);
            
            // Empêcher tout comportement par défaut
            e.preventDefault();
            e.stopPropagation();
            
            // Désactiver le bouton pendant la publication
            this.disabled = true;
            
            try {
                // Appel direct de submitEvent
                console.log(" 🚀 Appel de submitEvent");
                await submitEvent();
            } catch (error) {
                console.error(" ❌ Erreur attrapée dans le handler de clic:", error);
                this.disabled = false;
            }
        });
        
        console.log(" ✅ Listener ajouté au bouton");
    } else {
        console.error(" ❌ Bouton de publication non trouvé dans le DOM");
        // Rechercher tous les boutons pour déboguer
        const allButtons = document.querySelectorAll('button');
        console.log(" 📋 Liste de tous les boutons:", Array.from(allButtons).map(b => ({
            id: b.id,
            class: b.className,
            text: b.textContent,
            html: b.outerHTML
        })));
    }
});

// Fonction pour publier l'événement
async function submitEvent() {
    console.log(" 🚀 Début de submitEvent");
    const publishButton = document.getElementById('publishButton');
    
    try {
        // Désactiver le bouton
        if (publishButton) {
            publishButton.disabled = true;
            publishButton.innerHTML = 'Publication en cours...';
        }

        console.log(" 1️⃣ Validation du formulaire");
        if (!validateForm()) {
            console.error(" ❌ Formulaire invalide");
            throw new Error("Veuillez corriger les erreurs dans le formulaire");
        }
        console.log(" ✅ Formulaire validé");

        // Récupérer l'ID du brouillon
        console.log(" 2️⃣ Recherche de l'input draftId");
        const hiddenDraftId = document.querySelector('input[name="draftId"]');
        console.log(" Input trouvé:", hiddenDraftId);
        if (!hiddenDraftId || !hiddenDraftId.value) {
            console.error(" ❌ ID du brouillon manquant");
            throw new Error("L'ID du brouillon est manquant");
        }
        const draftId = hiddenDraftId.value;
        console.log(" ✅ Draft ID trouvé:", draftId);

        // Créer les données à envoyer
        console.log(" 3️⃣ Préparation FormData");
        const formData = new FormData();
        formData.append('draftId', draftId);
        console.log(" ✅ FormData créé avec draftId:", draftId);

        // Envoi de la requête
        const url = '../../api/events/publish.php';
        console.log(" 4️⃣ Envoi fetch vers", url);
        console.log(" URL complète:", new URL(url, window.location.href).href);
        console.log(" FormData contient:", {
            draftId: formData.get('draftId')
        });
        
        try {
            console.log(" Début fetch...");
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            console.log(" Fetch terminé");

            console.log(" Statut de la réponse:", response.status);
            console.log(" Headers:", {
                contentType: response.headers.get('content-type'),
                status: response.statusText
            });

            const responseText = await response.text();
            console.log(" Réponse brute:", responseText);

            if (!response.ok) {
                console.error(" ❌ Erreur HTTP:", response.status, responseText);
                throw new Error(`Erreur lors de la publication: ${response.status}`);
            }

            try {
                const data = JSON.parse(responseText);
                console.log(" Réponse parsée:", data);
                
                if (!data.success) {
                    throw new Error(data.error || "Erreur lors de la publication");
                }

                showToast('Événement publié avec succès !', 'success');
                setTimeout(() => {
                    window.location.href = '/events/';
                }, 2000);

            } catch (e) {
                console.error(" ❌ Erreur parsing JSON:", e);
                throw new Error("La réponse n'est pas au format JSON");
            }
        } catch (error) {
            console.error(" ❌ Erreur lors de l'envoi de la requête:", error);
            throw error;
        }

    } catch (error) {
        console.error(" ❌ Erreur dans submitEvent:", error);
        showToast(error.message, 'error');
    } finally {
        // Réactiver le bouton
        if (publishButton) {
            publishButton.disabled = false;
            publishButton.innerHTML = 'Publier';
        }
    }
}

// Fonction pour valider les fichiers GPX
function validateGpxFiles() {
    const routes = document.querySelectorAll('.route-field');
    let totalSize = 0;
    const maxSize = 10 * 1024 * 1024; // 10 MB max par fichier

    for (const route of routes) {
        const gpxInput = route.querySelector('[name^="route_gpx"]');
        if (gpxInput && gpxInput.files[0]) {
            const file = gpxInput.files[0];
            
            // Vérifier l'extension
            if (!file.name.toLowerCase().endsWith('.gpx')) {
                throw new Error('Le fichier doit être au format GPX');
            }

            // Vérifier la taille
            if (file.size > maxSize) {
                throw new Error('Le fichier GPX ne doit pas dépasser 10 MB');
            }

            totalSize += file.size;
        }
    }

    // Vérifier la taille totale
    if (totalSize > maxSize * 3) {
        throw new Error('La taille totale des fichiers GPX ne doit pas dépasser 30 MB');
    }

    return true;
}
