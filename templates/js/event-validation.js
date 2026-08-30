// Variables globales
let currentStep = 1;
let totalSteps = 5; // Mis à jour pour inclure toutes les étapes
let mapInitialized = false;
let currentGpxLayers = {};

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
    
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    if (!preview) {
        console.error('❌ Élément de prévisualisation non trouvé:', previewId);
        return;
    }
    
    const container = preview.parentElement;
    const uploadButton = container.querySelector('.image-upload-button');
    
    
    const errorContainer = container.querySelector('.invalid-feedback') || document.createElement('div');
    
    if (!errorContainer.classList.contains('invalid-feedback')) {
        errorContainer.className = 'invalid-feedback';
        container.appendChild(errorContainer);
    }

    if (file) {
            name: file.name,
            type: file.type,
            size: `${(file.size / 1024 / 1024).toFixed(2)}MB`
        });

        // Vérifier la taille du fichier (en MB)
        if (file.size > maxSize * 1024 * 1024) {
            console.error('❌ Fichier trop volumineux');
            input.value = '';
            errorContainer.textContent = `L'image ne doit pas dépasser ${maxSize}MB`;
            errorContainer.style.display = 'block';
            return;
        }

        // Vérifier le type de fichier
        if (!file.type.startsWith('image/')) {
            console.error('❌ Type de fichier non valide');
            input.value = '';
            errorContainer.textContent = 'Veuillez sélectionner une image';
            errorContainer.style.display = 'block';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
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
            console.error('❌ Erreur lors de la lecture du fichier:', e);
            errorContainer.textContent = 'Erreur lors de la lecture du fichier';
            errorContainer.style.display = 'block';
            preview.src = '';
            preview.style.display = 'none';
            if (uploadButton) {
                uploadButton.style.display = 'flex';
            }
        };
        
        reader.readAsDataURL(file);
    } else {
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
    
    if (!input.files || !input.files[0]) {
        console.error('Aucun fichier logo sélectionné');
        return;
    }

    const file = input.files[0];
    const reader = new FileReader();
    const preview = document.getElementById('logoPreview');
    const previewWrapper = document.getElementById('logoPreviewWrapper');

    reader.onload = function(e) {
        preview.src = e.target.result;
        previewWrapper.style.display = 'block';
        
        // Réinitialiser l'input pour éviter les doublons
        input.value = '';
    };

    reader.readAsDataURL(file);
}

// Fonction pour gérer l'upload des images secondaires
function handleSecondaryImagesUpload(event) {
    
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

    Array.from(files).forEach((file, index) => {
        if (currentImages + index >= maxImages) return;

        const container = document.createElement('div');
        container.className = 'secondary-image-container';
        
        const img = document.createElement('img');
        const removeButton = document.createElement('button');
        removeButton.className = 'remove-image';
        removeButton.innerHTML = '<i class="bi bi-x"></i>';
        
        removeButton.onclick = function() {
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

    // Initialiser la carte de localisation
    const locationMapElement = document.getElementById('locationMap');

    if (locationMapElement) {
        initLocationMap();
    }

    // Initialiser la carte GPX
    const gpxMapElement = document.getElementById('gpxMap');

    if (gpxMapElement) {
        initGpxMap();
    } else {
    }
}

// Fonction pour gérer le téléchargement d'un fichier GPX
function handleGpxUpload(input, routeIndex) {
    
    const file = input.files[0];
    if (!file) {
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

        } catch (error) {
            console.error('❌ Erreur lors du traitement du fichier GPX:', error);
            showToast('Erreur lors du traitement du fichier GPX. Vérifiez que le fichier est valide.', 'error');
        }
    };

    reader.onerror = function() {
        console.error('❌ Erreur lors de la lecture du fichier');
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

// Fonction pour passer à l'étape suivante
async function nextStep() {
    
    if (currentStep < totalSteps) {
        if (validateStep(currentStep)) {
            // Si on passe à l'étape 3 (prévisualisation)
            if (currentStep === 2) {
                await showPreview();
            }
            
            // Cacher l'étape actuelle
            document.getElementById(`step${currentStep}`).classList.add('d-none');
            
            // Incrémenter et afficher la nouvelle étape
            currentStep++;
            document.getElementById(`step${currentStep}`).classList.remove('d-none');
            
            // Mettre à jour la barre de progression
            updateProgress();
            
            // Mettre à jour l'affichage des boutons
            updateButtons();
        }
    }
}

// Fonction pour revenir à l'étape précédente
function prevStep() {
    if (currentStep <= 1) return;
    
    // Fermer la modal de prévisualisation si elle est ouverte
    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
        previewModal.classList.remove('show');
        previewModal.style.display = 'none';
        document.body.classList.remove('modal-open');
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.remove();
    }
    
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
    
    // Mettre à jour la barre de progression
    updateProgress();
    
    // Mettre à jour l'affichage des boutons
    updateButtons();
    
    // Réinitialiser les erreurs si nécessaire
    hideGlobalErrors();
}

// Fonction pour afficher la prévisualisation
async function showPreview() {

    try {
        // Collecter toutes les données du formulaire
        const formData = new FormData(document.getElementById('createEventForm'));
        
        // Envoyer les données au serveur
        const response = await fetch('/api/events/preview.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Erreur lors de la création de la prévisualisation');
        }
        
        if (data.success) {
            const previewUrl = `/templates/events/event-detail.php?id=${data.data.draft_id}&preview=true`;
            
            // Récupérer la modal
            const previewModal = document.getElementById('previewModal');
            if (!previewModal) {
                throw new Error('Modal de prévisualisation non trouvée');
            }

            // Mettre à jour l'URL de l'iframe
            const previewFrame = document.getElementById('previewFrame');
            if (!previewFrame) {
                throw new Error('Iframe de prévisualisation non trouvée');
            }

            // Charger l'URL dans l'iframe
            previewFrame.src = previewUrl;

            // Configurer le bouton de publication
            const publishButton = previewModal.querySelector('.modal-footer .btn-primary');
            if (publishButton) {
                publishButton.onclick = () => {
                    submitForm(data.data.draft_id);
                };
            }

            // Gérer la fermeture de la modal
            previewModal.addEventListener('hidden.bs.modal', function () {
                if (previewFrame) {
                    previewFrame.src = 'about:blank';
                }
                prevStep();
            }, { once: true });

            // Afficher la modal
            const modal = new bootstrap.Modal(previewModal);
            modal.show();

        } else {
            throw new Error(data.error || "Erreur lors de la création de la prévisualisation");
        }
    } catch (error) {
        console.error("❌ Erreur:", error);
        showToast("Une erreur est survenue lors de la prévisualisation", "error");
        throw error;
    }
}

// Fonction pour soumettre le formulaire
async function submitForm(draftId) {

    try {
        // Récupérer toutes les données du formulaire
        const formData = new FormData(document.getElementById('createEventForm'));
        const formDataObject = {};
        formData.forEach((value, key) => {
            formDataObject[key] = value;
        });

        // Ajouter le draftId
        formDataObject.draftId = draftId;

        // Ajouter les parcours
        const routes = [];
        const routeContainers = document.querySelectorAll('.route-container');
        
        // Upload des fichiers GPX d'abord
        for (let i = 0; i < routeContainers.length; i++) {
            const container = routeContainers[i];
            const gpxInput = container.querySelector('[name^="route_gpx"]');
            const gpxFile = gpxInput?.files[0];
            
            if (gpxFile) {
                try {
                    // Uploader le fichier GPX
                    const gpxPath = await handleGpxUpload(gpxInput, i);
                    
                    // Créer l'objet route avec le chemin du GPX
                    const route = {
                        name: container.querySelector('[name^="route_name"]').value,
                        distance: container.querySelector('[name^="route_distance"]').value,
                        elevation_gain: container.querySelector('[name^="route_elevation"]').value,
                        description: container.querySelector('[name^="route_description"]').value,
                        price: container.querySelector('[name^="route_price"]').value,
                        gpx_file: gpxPath // Utiliser le chemin retourné par l'upload
                    };
                    routes.push(route);
                } catch (error) {
                    console.error("❌ Erreur lors de l'upload du GPX:", error);
                    throw new Error("Erreur lors de l'upload du GPX: " + error.message);
                }
            } else {
                // Pas de fichier GPX, ajouter juste les données du parcours
                const route = {
                    name: container.querySelector('[name^="route_name"]').value,
                    distance: container.querySelector('[name^="route_distance"]').value,
                    elevation_gain: container.querySelector('[name^="route_elevation"]').value,
                    description: container.querySelector('[name^="route_description"]').value,
                    price: container.querySelector('[name^="route_price"]').value
                };
                routes.push(route);
            }
        }
        
        formDataObject.routes = routes;


        const response = await fetch('/api/events/publish.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formDataObject)
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || data.message || "Erreur lors de la publication");
        }

        if (data.success) {
            showToast("Événement créé avec succès", "success");
            
            // Fermer la modal si elle est ouverte
            const modal = document.getElementById('previewModal');
            if (modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }

            // Rediriger vers la page de l'événement
            if (data.eventId) {
                window.location.href = `/event.php?id=${data.eventId}`;
            }
        } else {
            throw new Error(data.error || "Erreur lors de la publication");
        }
    } catch (error) {
        console.error("❌ Erreur dans submitForm:", error);
        showToast(error.message || "Une erreur est survenue lors de la publication", "error");
    }
}

// Fonction pour valider tout le formulaire
function validateForm() {
    // Réinitialiser les erreurs
    hideGlobalErrors();
    
    let isValid = true;
    const errors = [];

    // Valider les champs requis
    const requiredFields = {
        'title': 'Titre',
        'date': 'Date',
        'start_time': 'Heure de début',
        'location': 'Lieu',
        'category': 'Catégorie',
        'organisation': 'Organisation',
        'organizerName': 'Nom de l\'organisateur',
        'organizerEmail': 'Email de l\'organisateur'
    };

    for (const [fieldId, fieldName] of Object.entries(requiredFields)) {
        const field = document.getElementById(fieldId) || document.querySelector(`[name="${fieldId}"]`);
        
        if (!field || !field.value.trim()) {
            isValid = false;
            const error = `Le champ "${fieldName}" est requis`;
            errors.push(error);
            if (field) {
                showFieldError(field, error);
            } else {
                console.warn(`⚠️ Champ non trouvé: ${fieldId}`);
            }
        } else {
            if (field) {
                hideFieldError(field);
            }
        }
    }

    // Valider la date
    const dateField = document.getElementById('date');
    if (dateField && dateField.value) {
        const selectedDate = new Date(dateField.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            isValid = false;
            errors.push('La date doit être future');
            showFieldError(dateField, 'La date doit être future');
        }
    }

    // Valider l'heure de fin si renseignée
    const startTimeField = document.getElementById('start_time');
    const endTimeField = document.getElementById('end_time');
    if (startTimeField && endTimeField && startTimeField.value && endTimeField.value) {
        if (endTimeField.value <= startTimeField.value) {
            isValid = false;
            errors.push('L\'heure de fin doit être après l\'heure de début');
            showFieldError(endTimeField, 'L\'heure de fin doit être après l\'heure de début');
        }
    }

    // Valider le nombre maximum de participants
    const maxParticipantsField = document.getElementById('max_participants');
    if (maxParticipantsField && maxParticipantsField.value) {
        const maxParticipants = parseInt(maxParticipantsField.value);
        if (isNaN(maxParticipants) || maxParticipants < 1) {
            isValid = false;
            errors.push('Le nombre maximum de participants doit être un nombre positif');
            showFieldError(maxParticipantsField, 'Le nombre maximum de participants doit être un nombre positif');
        }
    }

    if (!isValid) {
        showGlobalErrors(errors);
    } else {
    }

    return isValid;
}

// Fonction pour valider une étape
function validateStep(stepNumber) {
    
    const stepContent = document.querySelector(`#step${stepNumber}`);
    
    if (!stepContent) {
        console.error('❌ Étape non trouvée');
        return false;
    }

    const requiredFields = stepContent.querySelectorAll('[required]');
    
    let isValid = true;
    let errors = [];
    let invalidFields = [];

    requiredFields.forEach((field, index) => {
        
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
    const nextButton = document.getElementById('nextButton');
    const prevButton = document.getElementById('prevButton');
    
    if (nextButton && prevButton) {
        if (currentStep === 1) {
            prevButton.style.display = 'none';
        } else {
            prevButton.style.display = 'block';
        }
        
        if (currentStep === totalSteps) {
            nextButton.style.display = 'none';
        } else {
            nextButton.style.display = 'block';
        }
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
function showToast(message, type) {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.classList.remove('bg-success', 'bg-danger', 'bg-primary');
        toast.classList.add(`bg-${type}`);
        toast.querySelector('.toast-body').textContent = message;
        const toastInstance = new bootstrap.Toast(toast);
        toastInstance.show();
    }
}

// Fonction pour initialiser les gestionnaires d'upload d'images
function initializeImageUploads() {

    // Image principale
    const mainImageInput = document.getElementById('mainImage');
    if (mainImageInput) {
        mainImageInput.addEventListener('change', function() {
            handleImageUpload(this, 'mainImagePreview');
        });
    }

    // Image secondaire
    const secondaryImageInput = document.getElementById('secondaryImages');
    if (secondaryImageInput) {
        secondaryImageInput.addEventListener('change', handleSecondaryImagesUpload);
    }

    // Logo de l'organisateur
    const logoInput = document.getElementById('organizerLogo');
    if (logoInput) {
        logoInput.addEventListener('change', function() {
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
    
    // Initialiser les boutons suivant
    const nextButtons = document.querySelectorAll('.btn-next');
    nextButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
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
        const fields = organizerFields.querySelectorAll('input, textarea');
        fields.forEach(field => {
            field.disabled = !isNewOrganizer || useProfile;
            if (!isNewOrganizer || useProfile) {
                field.classList.remove('is-invalid');
            }
        });

        // Si un organisateur est sélectionné, charger ses informations
        if (organizerSelect && organizerSelect.value !== '') {
            loadOrganizerInfo(organizerSelect.value);
        } else if (useProfile) {
            // Si on utilise le profil utilisateur, charger les informations par défaut
            loadDefaultProfile();
        }
    }

    // Charger les informations d'un organisateur
    async function loadOrganizerInfo(organizerId) {
        try {
            const response = await fetch(`/api/organizer/get_profile.php?id=${organizerId}`);
            const data = await response.json();
            
            if (data.success && data.profile) {
                fillOrganizerFields(data.profile);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des informations de l\'organisateur:', error);
        }
    }

    // Charger le profil par défaut de l'utilisateur
    async function loadDefaultProfile() {
        try {
            const response = await fetch('/api/organizer/get_profile.php');
            const data = await response.json();
            
            if (data.success && data.profile) {
                fillOrganizerFields(data.profile);
            }
        } catch (error) {
            console.error('Erreur lors du chargement du profil par défaut:', error);
        }
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

// Fonction pour initialiser la carte de localisation
function initLocationMap() {
    
    const mapContainer = document.getElementById('locationMap');
    if (!mapContainer) {
        console.error('❌ Container de carte non trouvé');
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

    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation de la carte:', error);
    }
}

// Fonction pour configurer la recherche d'adresse
function setupAddressSearch() {
    const searchButton = document.getElementById('searchAddressBtn');
    const addressInput = document.getElementById('address');

    if (!searchButton || !addressInput) {
        console.error('❌ Éléments de recherche non trouvés');
        return;
    }

    // Fonction de recherche
    const searchAddress = () => {
        const address = addressInput.value.trim();
        if (!address) return;


        // Utiliser l'API Nominatim pour la recherche
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    const result = data[0];
                    const latlng = { lat: parseFloat(result.lat), lng: parseFloat(result.lon) };
                    
                    
                    // Mettre à jour le marqueur et la carte
                    window.locationMarker.setLatLng(latlng);
                    window.locationMap.flyTo(latlng, 16);
                    updateAddress(latlng);
                } else {
                    console.error('❌ Aucun résultat trouvé');
                    alert('Aucune adresse trouvée');
                }
            })
            .catch(error => {
                console.error('❌ Erreur lors de la recherche:', error);
                alert('Erreur lors de la recherche de l\'adresse');
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
    
    // Vérifier si les champs existent
    const latitudeField = document.getElementById('latitude');
    const longitudeField = document.getElementById('longitude');
    const addressField = document.getElementById('address');

    if (!latitudeField || !longitudeField || !addressField) {
        console.error('❌ Champs non trouvés');
        return;
    }

    // Mettre à jour les champs de latitude et longitude
    latitudeField.value = latlng.lat.toFixed(6);
    longitudeField.value = latlng.lng.toFixed(6);

    // Utiliser le geocoder pour obtenir l'adresse
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&addressdetails=1`)
        .then(response => response.json())
        .then(data => {
            
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
                addressField.value = fullAddress || data.display_name;
            } else {
                console.error('❌ Données d\'adresse invalides');
                addressField.value = '';
            }
        })
        .catch(error => {
            console.error('❌ Erreur lors de la récupération de l\'adresse:', error);
        });
}

// Fonction pour initialiser la carte GPX
function initGpxMap() {
    
    const mapContainer = document.getElementById('gpxMap');
    if (!mapContainer) {
        console.error('❌ Container de la carte GPX non trouvé');
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

    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation de la carte GPX:', error);
    }
}

// Fonction pour traiter le fichier GPX
function handleGpxUpload(input, routeIndex) {
    
    const file = input.files[0];
    if (!file) {
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

        } catch (error) {
            console.error('❌ Erreur lors du traitement du fichier GPX:', error);
            showToast('Erreur lors du traitement du fichier GPX. Vérifiez que le fichier est valide.', 'error');
        }
    };

    reader.onerror = function() {
        console.error('❌ Erreur lors de la lecture du fichier');
        showToast('Erreur lors de la lecture du fichier', 'error');
    };

    reader.readAsText(file);
}

// Fonction pour calculer la distance d'un parcours GPX
function calculateDistance(gpxData) {
    
    try {
        const parser = new DOMParser();
        const gpx = parser.parseFromString(gpxData, 'text/xml');
        const points = Array.from(gpx.getElementsByTagName('trkpt'));
        
        if (points.length < 2) {
            console.error('❌ Pas assez de points pour calculer la distance');
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
        
        return distance;
    } catch (error) {
        console.error('❌ Erreur lors du calcul de la distance:', error);
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
        console.warn('⚠️ Tentative d\'afficher une erreur sur un champ null:', message);
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

document.addEventListener('DOMContentLoaded', function() {
    // Ne s'exécuter que sur la page de création d'événement
    const form = document.getElementById('createEventForm');
    if (!form) {
        return;
    }

    
    // Validation à la soumission
    form.addEventListener('submit', function(event) {
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
    
    // Ajouter l'écouteur pour le bouton de soumission finale
    const submitButton = document.getElementById('submitButton');
    if (submitButton) {
        submitButton.addEventListener('click', function(e) {
            e.preventDefault();
            submitForm();
        });
    }
});
