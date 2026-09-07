// Variables globales pour les boutons
let nextButton = null;
let prevButton = null;
let step1Text = null;
let step2Text = null;
let isInitialized = false;

// Les gestionnaires de boutons sont définis plus bas dans le fichier.

// Fonction pour sauvegarder l'étape 2
async function saveEvent() {
    try {
        hideGlobalErrors();
        
        // Valider l'étape 2
        if (!validateStep(2)) {
            return;
        }

        // Préparer les données du formulaire
        const form = document.getElementById('createEventForm');
        const formData = new FormData(form);
        
        // Ajouter l'ID de l'événement
        formData.append('eventId', window.eventId);

        // Ajouter les coordonnées
        const latitude = document.querySelector('input[name="latitude"]').value;
        const longitude = document.querySelector('input[name="longitude"]').value;
        if (latitude && longitude) {
            formData.append('coordinates', `${latitude},${longitude}`);
        }
        
        // Envoyer vers l'endpoint de mise à jour
        const response = await fetch('../../api/events/update.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }

        // Lire la réponse
        const responseText = await response.text();
        console.log('Réponse brute:', responseText);

        // Vérifier si la réponse est vide
        if (!responseText.trim()) {
            console.error('Réponse vide du serveur');
            throw new Error('Le serveur n\'a pas renvoyé de données');
        }

        // Si la réponse contient une erreur PHP
        const phpError = extractPhpErrorMessage(responseText);
        if (phpError) {
            console.error('Erreur PHP détectée:', phpError);
            throw new Error(`Erreur serveur: ${phpError}`);
        }

        // Tenter de parser le JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Erreur de parsing JSON:', e);
            console.error('Contenu reçu:', responseText);
            throw new Error('La réponse du serveur n\'est pas au format JSON valide');
        }
        
        if (!data.success) {
            throw new Error(data.message || 'Erreur lors de la sauvegarde de l\'événement');
        }

        // Redirection après mise à jour
        try {
            if (sessionStorage.getItem('adminEdit') === '1') {
                sessionStorage.removeItem('adminEdit');
                window.location.href = '/pages/admin';
            } else {
                window.location.href = '/event?id=' + encodeURIComponent(window.eventId);
            }
        } catch (_) {
            window.location.href = '/event?id=' + encodeURIComponent(window.eventId);
        }

    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        showGlobalErrors([error.message]);
    }
}

// Fonction pour changer d'étape
function switchStep(direction) {
    console.log('⏩ switchStep - Direction:', direction);
    
    const currentStep = getCurrentStep();
    console.log('⏩ switchStep - Étape actuelle:', currentStep);
    
    const newStep = currentStep + direction;
    console.log('⏩ switchStep - Nouvelle étape:', newStep);
    
    // Vérifier si l'étape est valide
    if (newStep < 1 || newStep > 2) {
        console.log('❌ switchStep - Étape invalide:', newStep);
        return;
    }

    // Validation avant de passer à l'étape suivante
    if (direction > 0) {
        console.log('🔍 switchStep - Validation de l\'étape actuelle...');
        if (!validateCurrentStep(currentStep)) {
            console.log('❌ switchStep - Validation échouée pour l\'étape', currentStep);
            return;
        }
        console.log('✅ switchStep - Validation réussie pour l\'étape', currentStep);
    }

    // Changer l'étape active
    console.log('🔄 switchStep - Changement vers l\'étape:', newStep);
    setStep(newStep);
}

// Fonction pour définir l'étape active
function setStep(stepNumber) {
    console.log('Définition de l\'étape active:', stepNumber);
    
    // Masquer toutes les étapes
    document.querySelectorAll('.step-content').forEach(content => {
        content.classList.add('d-none');
    });
    
    // Afficher l'étape demandée
    const stepContent = document.getElementById(`step${stepNumber}`);
    if (stepContent) {
        stepContent.classList.remove('d-none');
    }
    
    // Mettre à jour la barre de progression
    const progress = ((stepNumber - 1) / 1) * 100;
    const progressBar = document.getElementById('progressBar');
    if (progressBar) {
        progressBar.style.width = `${progress}%`;
        progressBar.setAttribute('aria-valuenow', progress);
    }
    
    // Mettre à jour les boutons de navigation
    updateNavigationButtons(stepNumber);
    
    console.log('Étape active mise à jour:', stepNumber);
}

// Fonction pour obtenir l'étape courante
function getCurrentStep() {
    const step1Content = document.getElementById('step1');
    const step2Content = document.getElementById('step2');
    
    console.log('getCurrentStep - Elements:', {
        step1: step1Content,
        step2: step2Content,
        step2Hidden: step2Content ? step2Content.classList.contains('d-none') : 'N/A'
    });
    
    if (step2Content && !step2Content.classList.contains('d-none')) {
        console.log('getCurrentStep - Returning step 2');
        return 2;
    }
    console.log('getCurrentStep - Returning step 1');
    return 1;
}

// Fonction pour valider l'étape courante
function validateCurrentStep(step) {
    console.log('Validation de l\'étape:', step);
    
    if (!step) {
        console.error('Étape non définie');
        return false;
    }

    const errors = [];
    
    switch (step) {
        case 1:
            // Validation des informations de base
            const titleInput = document.querySelector('input[name="title"]');
            const dateInput = document.querySelector('input[name="date"]');
            const startTimeInput = document.querySelector('input[name="startTime"]');
            const locationInput = document.querySelector('input[name="location"]');
            const categoryInput = document.querySelector('select[name="category_id"]');

            if (!titleInput || !titleInput.value.trim()) {
                showFieldError(titleInput, 'Le titre est requis');
                errors.push('Le titre est requis');
            }

            if (!dateInput || !dateInput.value) {
                showFieldError(dateInput, 'La date est requise');
                errors.push('La date est requise');
            }

            if (!startTimeInput || !startTimeInput.value) {
                showFieldError(startTimeInput, 'L\'heure de début est requise');
                errors.push('L\'heure de début est requise');
            }

            if (!locationInput || !locationInput.value.trim()) {
                showFieldError(locationInput, 'Le lieu est requis');
                errors.push('Le lieu est requis');
            }

            if (!categoryInput || !categoryInput.value) {
                showFieldError(categoryInput, 'La catégorie est requise');
                errors.push('La catégorie est requise');
            }
            break;

        case 2:
            // Validation des informations de l'organisateur
            const organizerNameInput = document.querySelector('input[name="organizerName"]');
            const contactEmailInput = document.querySelector('input[name="contactEmail"]');
            const contactPhoneInput = document.querySelector('input[name="contactPhone"]');
            const websiteInput = document.querySelector('input[name="website"]');

            const organizerName = organizerNameInput?.value || '';
            const contactEmail = contactEmailInput?.value || '';
            const contactPhone = contactPhoneInput?.value || '';
            const website = websiteInput?.value || '';

            if (!organizerName) {
                showFieldError(organizerNameInput, 'Le nom de l\'organisateur est requis');
                errors.push('Le nom de l\'organisateur est requis');
            }

            if (!contactEmail && !contactPhone) {
                if (contactEmailInput) showFieldError(contactEmailInput, 'Au moins un moyen de contact est requis');
                if (contactPhoneInput) showFieldError(contactPhoneInput, 'Au moins un moyen de contact est requis');
                errors.push('Au moins un moyen de contact (email ou téléphone) est requis');
            }

            if (contactEmail && !isValidEmail(contactEmail)) {
                showFieldError(contactEmailInput, 'L\'email n\'est pas valide');
                errors.push('L\'email n\'est pas valide');
            }

            if (website && !isValidUrl(website)) {
                showFieldError(websiteInput, 'L\'URL du site web n\'est pas valide');
                errors.push('L\'URL du site web n\'est pas valide');
            }
            break;

        default:
            console.error('Étape invalide:', step);
            return false;
    }

    if (errors.length > 0) {
        showGlobalErrors(errors);
        return false;
    }

    hideGlobalErrors();
    return true;
}

// Fonction pour sauvegarder l'événement
async function saveEvent() {
    console.log('Début saveEvent');
    try {
        const form = document.getElementById('createEventForm');
        if (!form || !validateCurrentStep(2)) {
            console.log('Formulaire non trouvé ou validation échouée');
            return;
        }

        // Désactiver le bouton pendant la sauvegarde
        const saveButton = document.getElementById('nextButton');
        const originalText = saveButton.innerHTML;
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Enregistrement...';

        // Récupérer les données du formulaire
        const formData = new FormData(form);
        
        // Ajouter les contacts
        const contacts = [];
        const contactNames = document.getElementsByName('contact_names[]');
        const contactNumbers = document.getElementsByName('contact_numbers[]');
        for (let i = 0; i < contactNames.length; i++) {
            if (contactNames[i].value || contactNumbers[i].value) {
                contacts.push({
                    name: contactNames[i].value,
                    phone: contactNumbers[i].value
                });
            }
        }
        formData.append('contacts', JSON.stringify(contacts));

        // Ajouter l'ID de l'événement
        formData.append('eventId', window.eventId);

        // Ajouter les coordonnées
        const latitude = document.querySelector('input[name="latitude"]').value;
        const longitude = document.querySelector('input[name="longitude"]').value;
        if (latitude && longitude) {
            formData.append('coordinates', `${latitude},${longitude}`);
        }
        
        // Envoyer vers l'endpoint de mise à jour
        const response = await fetch('/api/events/update.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        console.log('Réponse du serveur:', result);
        if (result.success) {
            showToast('Événement mis à jour avec succès', 'success');
            // Rediriger vers la page de l'événement après un court délai
            setTimeout(() => {
                window.location.href = `/pages/events/event.php?id=${window.eventId}`;
            }, 1500);
        } else {
            throw new Error(result.message || 'Erreur lors de la mise à jour');
        }
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        showToast(error.message || 'Erreur lors de la sauvegarde', 'error');
    } finally {
        // Réactiver le bouton
        const saveButton = document.getElementById('nextButton');
        saveButton.disabled = false;
        saveButton.innerHTML = '<span class="step2-text">Enregistrer <i class="bi bi-check-lg"></i></span>';
    }
}

// Fonctions de validation
function validateForm() {
    const currentStep = getCurrentStep();
    return validateStep(currentStep);
}

function validateStep(stepNumber) {
    hideGlobalErrors();
    
    let isValid = true;
    const requiredFields = [];
    
    if (stepNumber === 1) {
        return validateStep1();
    } else if (stepNumber === 2) {
        // Validation de l'étape 2
        const organizerName = document.getElementById('organizerName')?.value;
        const organizerEmail = document.getElementById('organizerEmail')?.value;
        const organizerPhone = document.getElementById('organizerPhone')?.value;
        
        if (!organizerName || !organizerEmail || !organizerPhone) {
            showToast('Veuillez remplir tous les champs obligatoires de l\'organisateur', 'error');
            return false;
        }
        
        if (!isValidEmail(organizerEmail)) {
            showToast('L\'adresse email de l\'organisateur n\'est pas valide', 'error');
            return false;
        }
        
        return true;
    } else if (stepNumber === 3) {
        // Validation de l'étape 3
        const termsAccepted = document.getElementById('termsAccepted')?.checked;
        
        if (!termsAccepted) {
            showToast('Vous devez accepter les conditions d\'utilisation', 'error');
            return false;
        }
        
        return true;
    }
    
    return true;
}

function validateStep1() {
    const title = document.getElementById('title').value;
    const description = document.getElementById('description').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const address = document.getElementById('address').value;
    const latitude = document.getElementById('latitude').value;
    const longitude = document.getElementById('longitude').value;
    
    // Vérifier qu'il y a au moins un GPX
    const totalGpxLayers = Object.keys(currentGpxLayers).length;
    if (totalGpxLayers === 0) {
        showToast('Au moins un parcours GPX doit être présent', 'error');
        return false;
    }

    if (!title || !description || !startDate || !endDate || !address || !latitude || !longitude) {
        showToast('Veuillez remplir tous les champs obligatoires', 'error');
        return false;
    }

    return true;
}

// Fonction pour mettre à jour la distance d'un parcours
function updateRouteDistance(routeIndex, distance) {
    console.log('updateRouteDistance appelé pour le parcours', routeIndex);
    const distanceField = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
    if (distanceField) {
        console.log('Champ distance trouvé, valeur avant:', distanceField.value);
        distanceField.value = distance;
        console.log('Nouvelle valeur:', distanceField.value);
        
        // Déclencher l'événement change manuellement
        const event = new Event('change', { bubbles: true });
        distanceField.dispatchEvent(event);
        
        hideFieldError(distanceField);
    }
}

// Fonction pour mettre à jour le dénivelé d'un parcours
function updateRouteElevation(routeIndex, elevation) {
    console.log('updateRouteElevation appelé pour le parcours', routeIndex);
    const elevationField = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);
    if (elevationField) {
        console.log('Champ dénivelé trouvé, valeur avant:', elevationField.value);
        elevationField.value = elevation;
        console.log('Nouvelle valeur:', elevationField.value);
        
        // Déclencher l'événement change manuellement
        const event = new Event('change', { bubbles: true });
        elevationField.dispatchEvent(event);
        
        hideFieldError(elevationField);
    }
}

// Rendre la fonction accessible globalement
window.updateRouteDistance = updateRouteDistance;
window.updateRouteElevation = updateRouteElevation;

// Fonctions de validation des fichiers
function validateImage(file) {
    const maxSize = 5 * 1024 * 1024; // 5 Mo
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    
    return file.size <= maxSize && allowedTypes.includes(file.type);
}

function validateGpxFile(file) {
    const maxSize = 10 * 1024 * 1024; // 10 Mo
    return file.size <= maxSize && file.name.toLowerCase().endsWith('.gpx');
}

// Fonctions utilitaires
function showGlobalErrors(errors) {
    const errorContainer = document.getElementById('globalErrorContainer');
    const errorList = document.getElementById('globalErrorList');
    errorList.innerHTML = errors.map(error => `<li>${error}</li>`).join('');
    errorContainer.classList.remove('d-none');
}

function hideGlobalErrors() {
    const errorContainer = document.getElementById('globalErrorContainer');
    errorContainer.classList.add('d-none');
}

function showFieldError(field, message) {
    // Vérifier que le champ existe
    if (!field) {
        console.error('Champ non trouvé pour le message:', message);
        return;
    }
    
    // Ajouter la classe is-invalid
    field.classList.add('is-invalid');
    
    // Créer ou mettre à jour le message d'erreur
    let errorDiv = field.nextElementSibling;
    if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
        errorDiv = document.createElement('div');
        errorDiv.classList.add('invalid-feedback');
        field.parentNode.insertBefore(errorDiv, field.nextSibling);
    }
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

function hideFieldError(field) {
    console.log('hideFieldError appelé pour:', field?.name);
    console.log('Classes avant:', field?.className);
    
    if (!field) {
        console.error('Champ non trouvé');
        return;
    }

    field.classList.remove('is-invalid');
    const feedback = field.parentElement.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.style.display = 'none';
    }
    
    console.log('Classes après:', field.className);
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function isValidUrl(url) {
    try {
        new URL(url);
        return true;
    } catch (e) {
        return false;
    }
}

// Navigation
window.switchStep = function(direction) {
    const currentStep = getCurrentStep();
    const newStep = currentStep + direction;
    
    // Vérifier si le changement d'étape est possible
    if (newStep >= 1 && newStep <= 3) {
        // Valider la transition d'étape
        if (!validateStepTransition(currentStep, newStep)) {
            return;
        }
        
        // Mettre à jour les classes active
        document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
        document.querySelector(`.step[data-step="${newStep}"]`).classList.add('active');
        
        // Mettre à jour l'affichage des contenus d'étapes
        document.querySelectorAll('.step-content').forEach(content => {
            content.classList.add('d-none');
        });
        document.getElementById(`step${newStep}`).classList.remove('d-none');
        
        // Mettre à jour les boutons
        const prevBtn = document.querySelector('.prev-step');
        const nextBtn = document.querySelector('.next-step');
        
        prevBtn.style.display = newStep === 1 ? 'none' : 'block';
        nextBtn.style.display = newStep === 3 ? 'none' : 'block';
        
        // Mettre à jour la barre de progression
        const progress = ((newStep - 1) / 2) * 100;
        document.getElementById('progressBar').style.width = `${progress}%`;
        document.getElementById('progressBar').setAttribute('aria-valuenow', progress);
    }
};

window.setStep = function(stepNumber) {
    const currentStep = getCurrentStep();
    const direction = stepNumber - currentStep;
    switchStep(direction);
};

// Variables globales pour la carte GPX
let locationMap = null;  // Carte pour la sélection de l'emplacement
let gpxMap = null;      // Carte pour l'affichage des GPX
let currentGpxLayers = {};

// Fonction pour initialiser la carte GPX
async function initializeGpxMap() {
    console.log('🔄 Initialisation de la carte GPX');
    
    // Vérifier si le plugin GPX est chargé, sinon le charger
    if (typeof L.GPX === 'undefined') {
        console.log('📥 Chargement du plugin GPX...');
        try {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js';
                script.onload = resolve;
                script.onerror = () => reject(new Error('Échec du chargement du plugin Leaflet-GPX'));
                document.head.appendChild(script);
            });
            console.log('✅ Plugin GPX chargé avec succès');
        } catch (error) {
            console.error('❌ Erreur lors du chargement du plugin Leaflet-GPX:', error);
            return;
        }
    }

    try {
        // Initialiser la carte si ce n'est pas déjà fait
        if (!gpxMap) {
            console.log('🗺️ Création de la carte GPX...');
            const mapElement = document.getElementById('gpxMap');
            if (!mapElement) {
                console.error('❌ Élément gpxMap non trouvé');
                return;
            }
            gpxMap = L.map('gpxMap').setView([46.603354, 1.888334], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: ' OpenStreetMap contributors'
            }).addTo(gpxMap);
            window.map = gpxMap; // Pour la compatibilité avec le code existant
        }

        // Charger les GPX existants
        const gpxInputs = document.querySelectorAll('input[type="hidden"][name$="[gpx_file]"]');
        console.log('🔍 GPX trouvés:', gpxInputs.length);
        
        for (const input of gpxInputs) {
            const routeIndex = input.name.match(/routes\[(\d+)\]/)[1];
            const gpxPath = input.value;
            
            if (gpxPath && !currentGpxLayers[routeIndex]) {
                try {
                    console.log(`📥 Chargement du fichier GPX ${routeIndex}:`, gpxPath);
                    const response = await fetch(gpxPath);
                    if (!response.ok) throw new Error('Erreur lors du chargement du GPX');
                    const gpxData = await response.text();
                    
                    const gpxLayer = new L.GPX(gpxData, {
                        async: true,
                        marker_options: {
                            startIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-start.png',
                            endIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-end.png',
                            shadowUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-shadow.png',
                            iconSize: [33, 50],
                            shadowSize: [50, 50],
                            iconAnchor: [16, 45],
                            shadowAnchor: [16, 47]
                        }
                    });

                    gpxLayer.on('loaded', function(e) {
                        console.log('✅ GPX chargé avec succès');
                        if (gpxMap) gpxMap.fitBounds(e.target.getBounds());
                        
                        // Mettre à jour les champs de distance et dénivelé
                        const distanceInput = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
                        const elevationInput = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);
                        
                        if (distanceInput) {
                            const distance = (gpxLayer.get_distance() / 1000).toFixed(1);
                            distanceInput.value = distance;
                        }
                        
                        if (elevationInput) {
                            const elevation = Math.round(gpxLayer.get_elevation_gain());
                            elevationInput.value = elevation;
                        }
                    });

                    currentGpxLayers[routeIndex] = gpxLayer;
                    gpxLayer.addTo(gpxMap);
                    console.log('✅ Couche GPX ajoutée à la carte');
                } catch (error) {
                    console.error('❌ Erreur lors du chargement du GPX:', error);
                    showToast('Erreur lors du chargement du GPX', 'error');
                }
            }
        }
    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation de la carte:', error);
        showToast('Erreur lors de l\'initialisation de la carte', 'error');
    }
}

// Fonction pour initialiser la carte de localisation
function initializeLocationMap() {
    if (!locationMap) {
        locationMap = L.map('locationMap').setView([46.603354, 1.888334], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(locationMap);

        // Ajouter un marqueur pour la position actuelle
        const coordinates = document.querySelector('input[name="coordinates"]').value;
        if (coordinates) {
            const [lat, lng] = coordinates.split(',').map(coord => parseFloat(coord.trim()));
            if (!isNaN(lat) && !isNaN(lng)) {
                const marker = L.marker([lat, lng], { draggable: true }).addTo(locationMap);
                locationMap.setView([lat, lng], 13);
                
                // Mettre à jour les coordonnées lors du déplacement du marqueur
                marker.on('dragend', function(e) {
                    const position = e.target.getLatLng();
                    document.querySelector('input[name="coordinates"]').value = `${position.lat},${position.lng}`;
                });
            }
        }
    }
}

// Fonction pour supprimer un GPX
function removeGpx(routeIndex) {
    console.log('🗑️ Suppression du GPX', routeIndex);
    
    // Supprimer la couche de la carte
    if (currentGpxLayers[routeIndex]) {
        // Supprimer les flèches de direction
        if (currentGpxLayers[routeIndex].arrows) {
            currentGpxLayers[routeIndex].arrows.forEach(arrow => gpxMap.removeLayer(arrow));
        }
        gpxMap.removeLayer(currentGpxLayers[routeIndex]);
        delete currentGpxLayers[routeIndex];
    }

    // Réinitialiser tous les champs du parcours
    const routesContainer = document.getElementById('routes-container');
    if (routesContainer) {
        // Réinitialiser le nom du parcours
        const nameInput = routesContainer.querySelector(`input[name="routes[${routeIndex}][name]"]`);
        if (nameInput) {
            console.log('Réinitialisation du nom:', nameInput.value);
            nameInput.value = '';
        }

        // Réinitialiser la distance
        const distanceInput = routesContainer.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
        if (distanceInput) {
            console.log('Réinitialisation de la distance:', distanceInput.value);
            distanceInput.value = '';
        }

        // Réinitialiser le dénivelé
        const elevationInput = routesContainer.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);
        if (elevationInput) {
            console.log('Réinitialisation du dénivelé:', elevationInput.value);
            elevationInput.value = '';
        }

        // Réinitialiser le prix
        const priceInput = routesContainer.querySelector(`input[name="routes[${routeIndex}][price]"]`);
        if (priceInput) {
            console.log('Réinitialisation du prix:', priceInput.value);
            priceInput.value = '';
        }

        // Réinitialiser le fichier GPX
        const gpxInput = routesContainer.querySelector(`input[name="routes[${routeIndex}][gpx_file]"]`);
        if (gpxInput) {
            console.log('Réinitialisation du fichier GPX:', gpxInput.value);
            gpxInput.value = '';
        }

        // Supprimer le conteneur du parcours
        const routeContainer = routesContainer.querySelector(`.route-container[data-index="${routeIndex}"]`);
        if (routeContainer) {
            console.log('Suppression du conteneur');
            routeContainer.remove();
        }
    }

    console.log('✅ Parcours supprimé avec succès');
}

// Fonction pour réinitialiser la carte GPX
function resetGpxMap() {
    // Supprimer toutes les couches GPX
    Object.keys(currentGpxLayers).forEach(index => {
        if (currentGpxLayers[index]) {
            gpxMap.removeLayer(currentGpxLayers[index]);
        }
    });
    
    // Réinitialiser les variables
    currentGpxLayers = {};
    
    // Réinitialiser les informations de distance et dénivelé
    const routes = document.querySelectorAll('.route-container');
    routes.forEach(route => {
        const distanceInput = route.querySelector('input[name^="routes"][name$="[distance]"]');
        const elevationInput = route.querySelector('input[name^="routes"][name$="[elevation]"]');
        if (distanceInput) distanceInput.value = '';
        if (elevationInput) elevationInput.value = '';
    });
}

// Fonction pour gérer le téléchargement d'un fichier GPX
async function handleGpxUpload(input, routeIndex) {
    if (!input.files || !input.files[0]) {
        return;
    }

    const file = input.files[0];
    
    // Supprimer l'ancien GPX s'il existe
    if (currentGpxLayers[routeIndex]) {
        // Supprimer les flèches de direction
        if (currentGpxLayers[routeIndex].arrows) {
            currentGpxLayers[routeIndex].arrows.forEach(arrow => gpxMap.removeLayer(arrow));
        }
        gpxMap.removeLayer(currentGpxLayers[routeIndex]);
        delete currentGpxLayers[routeIndex];
    }

    // Créer un FormData pour l'upload
    const formData = new FormData();
    formData.append('gpx_file', file);
    formData.append('route_index', routeIndex);
    formData.append('event_id', window.eventId);

    try {
        // Envoyer le fichier au serveur
        const response = await fetch('/api/events/upload-gpx.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Erreur lors de l\'upload du fichier');
        }

        const result = await response.json();

        if (result.success) {
            // Charger le GPX sur la carte
            const gpxResponse = await fetch(result.gpx_url);
            if (!gpxResponse.ok) throw new Error('Erreur lors du chargement du GPX');
            const gpxData = await gpxResponse.text();
            
            // Créer une nouvelle couche GPX avec debug
            console.log('📥 Création de la couche GPX avec options:', {
                async: true,
                marker_options: {
                    startIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-start.png',
                    endIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-end.png',
                    shadowUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-shadow.png',
                    iconSize: [33, 50],
                    shadowSize: [50, 50],
                    iconAnchor: [16, 45],
                    shadowAnchor: [16, 47]
                }
            });

            const gpx = new L.GPX(gpxData, {
                async: true,
                polyline_options: {
                    color: 'blue',
                    weight: 3,
                    opacity: 0.7
                },
                marker_options: {
                    startIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-start.png',
                    endIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-end.png',
                    shadowUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-shadow.png',
                    iconSize: [33, 50],
                    shadowSize: [50, 50],
                    iconAnchor: [16, 45],
                    shadowAnchor: [16, 47]
                }
            });

            // Debug des événements GPX
            gpx.on('addpoint', function(e) {
                console.log('📍 Point GPX ajouté:', {
                    type: e.point_type,
                    point: e.point,
                    marker: e.marker
                });
            });

            gpx.on('loaded', function(e) {
                console.log('✅ GPX chargé avec succès');
                
                // Debug des couches
                const layers = e.target.getLayers();
                console.log('🗺️ Couches GPX:', layers);
                
                // Debug des marqueurs
                const markers = layers.filter(layer => layer instanceof L.Marker);
                console.log('📌 Marqueurs trouvés:', markers.length);
                
                markers.forEach((marker, index) => {
                    console.log(`📍 Marqueur ${index}:`, {
                        position: marker.getLatLng(),
                        options: marker.options
                    });
                });

                // Debug du contenu GPX
                console.log('📊 Métadonnées GPX:', {
                    name: e.target.get_name(),
                    distance: e.target.get_distance(),
                    elevation_gain: e.target.get_elevation_gain(),
                    elevation_loss: e.target.get_elevation_loss(),
                    total_time: e.target.get_total_time()
                });

                // Mettre à jour les champs
                const distance = (e.target.get_distance() / 1000).toFixed(1);
                const elevation = Math.round(e.target.get_elevation_gain());

                const distanceInput = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
                const elevationInput = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);

                if (distanceInput) distanceInput.value = distance;
                if (elevationInput) elevationInput.value = elevation;

                // Ajouter des flèches de direction
                const track = layers.find(layer => layer instanceof L.Polyline);
                if (track) {
                    console.log('🛣️ Parcours trouvé, ajout des flèches');
                    
                    const arrows = L.polylineDecorator(track, {
                        patterns: [
                            {
                                offset: '5%',
                                repeat: '15%',
                                symbol: L.Symbol.arrowHead({
                                    pixelSize: 15,
                                    pathOptions: {
                                        color: '#004de8',
                                        fillOpacity: 1,
                                        weight: 2
                                    }
                                })
                            }
                        ]
                    }).addTo(gpxMap);

                    if (!currentGpxLayers[routeIndex].arrows) {
                        currentGpxLayers[routeIndex].arrows = [];
                    }
                    currentGpxLayers[routeIndex].arrows.push(arrows);
                }

                // Centrer la carte
                const bounds = e.target.getBounds();
                if (bounds) {
                    gpxMap.fitBounds(bounds);
                }
            });

            gpx.on('error', function(e) {
                console.error('❌ Erreur lors du chargement du GPX:', e.error);
            });

            // Ajouter la couche à la carte et la stocker
            gpx.addTo(gpxMap);
            currentGpxLayers[routeIndex] = gpx;

            // Mettre à jour le champ caché avec l'URL du GPX
            const gpxInput = document.querySelector(`input[name="routes[${routeIndex}][gpx_file]"]`);
            if (gpxInput) {
                gpxInput.value = result.gpx_url;
            }

            showToast('GPX uploadé avec succès', 'success');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showToast('Erreur lors de l\'upload du GPX', 'error');
    }
}

// Fonction pour ajouter un parcours
function addRoute() {
    const routesContainer = document.getElementById('routes-container');
    const routeIndex = document.querySelectorAll('.route-container').length;
    
    const routeHtml = `
        <div class="route-container" data-index="${routeIndex}">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h5 class="card-title mb-0">Parcours ${routeIndex + 1}</h5>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeGpx(${routeIndex})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="route-name-${routeIndex}" class="form-label">Nom du parcours</label>
                            <input type="text" id="route-name-${routeIndex}" name="routes[${routeIndex}][name]" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="route-gpx-${routeIndex}" class="form-label">Fichier GPX</label>
                            <input type="file" id="route-gpx-${routeIndex}" name="routes[${routeIndex}][gpx]" class="form-control" accept=".gpx" required>
                        </div>
                        <div class="col-md-6">
                            <label for="route-distance-${routeIndex}" class="form-label">Distance (km)</label>
                            <input type="number" step="0.1" id="route-distance-${routeIndex}" name="routes[${routeIndex}][distance]" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="route-elevation-${routeIndex}" class="form-label">Dénivelé (m)</label>
                            <input type="number" id="route-elevation-${routeIndex}" name="routes[${routeIndex}][elevation]" class="form-control" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    routesContainer.insertAdjacentHTML('beforeend', routeHtml);
    initializeRouteFieldListeners(routesContainer.lastElementChild);
}

// Fonction pour valider l'étape 1
function validateStep1() {
    const title = document.getElementById('title').value;
    const description = document.getElementById('description').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const address = document.getElementById('address').value;
    const latitude = document.getElementById('latitude').value;
    const longitude = document.getElementById('longitude').value;
    
    // Vérifier qu'il y a au moins un GPX
    const totalGpxLayers = Object.keys(currentGpxLayers).length;
    if (totalGpxLayers === 0) {
        showToast('Au moins un parcours GPX doit être présent', 'error');
        return false;
    }

    if (!title || !description || !startDate || !endDate || !address || !latitude || !longitude) {
        showToast('Veuillez remplir tous les champs obligatoires', 'error');
        return false;
    }

    return true;
}

// Fonction pour initialiser les event listeners des champs de parcours
function initializeRouteFieldListeners(routeElement) {
    console.log('Initialisation des listeners pour:', routeElement);
    
    // Ajouter les event listeners pour tous les champs du parcours
    routeElement.querySelectorAll('input').forEach(input => {
        console.log('Ajout listener pour:', input.name);
        
        // Event listener pour l'input
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                hideFieldError(this);
            }
        });

        // Event listener spécial pour la distance
        if (input.name.includes('[distance]')) {
            console.log('Ajout listener distance pour:', input.name);
            input.addEventListener('change', function() {
                console.log('Distance changée:', this.value);
                // Revalider la distance
                if (this.value && parseFloat(this.value) > 0) {
                    hideFieldError(this);
                }
            });
        }

        // Event listener spécial pour le dénivelé
        if (input.name.includes('[elevation]')) {
            console.log('Ajout listener dénivelé pour:', input.name);
            input.addEventListener('change', function() {
                console.log('Dénivelé changé:', this.value);
                // Revalider le dénivelé
                if (this.value && parseFloat(this.value) > 0) {
                    hideFieldError(this);
                }
            });
        }

        // Event listener pour le GPX
        if (input.name.includes('[gpx]')) {
            console.log('Ajout listener GPX pour:', input.name);
            const routeIndex = input.name.match(/routes\[(\d+)\]/)[1];
            input.addEventListener('change', function() {
                handleGpxUpload(this, routeIndex);
            });
        }
    });
}

// Initialiser les event listeners pour les parcours existants
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initialisation de la page edit-event.js');
    
    // Initialiser les event listeners pour les parcours existants
    const existingRoutes = document.querySelectorAll('.route-item');
    existingRoutes.forEach(route => {
        initializeRouteFieldListeners(route);
    });

    // Initialiser la carte GPX
    initializeGpxMap();
});

// Fonction pour valider la transition d'étape
function validateStepTransition(currentStep, nextStep) {
    if (currentStep === 1 && nextStep === 2) {
        // Vérifier qu'il y a au moins un GPX avant de passer à l'étape 2
        const totalGpxLayers = Object.keys(currentGpxLayers).length;
        if (totalGpxLayers === 0) {
            showToast('Au moins un parcours GPX doit être présent', 'error');
            return false;
        }
    }
    return true;
}

// Fonction pour changer d'étape
function switchStep(direction) {
    const currentStep = getCurrentStep();
    const newStep = currentStep + direction;
    
    // Vérifier si le changement d'étape est possible
    if (newStep >= 1 && newStep <= 3) {
        // Valider la transition d'étape
        if (!validateStepTransition(currentStep, newStep)) {
            return;
        }
        
        // Mettre à jour les classes active
        document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
        document.querySelector(`.step[data-step="${newStep}"]`).classList.add('active');
        
        // Mettre à jour l'affichage des contenus d'étapes
        document.querySelectorAll('.step-content').forEach(content => {
            content.classList.add('d-none');
        });
        document.getElementById(`step${newStep}`).classList.remove('d-none');
        
        // Mettre à jour les boutons
        const prevBtn = document.querySelector('.prev-step');
        const nextBtn = document.querySelector('.next-step');
        
        prevBtn.style.display = newStep === 1 ? 'none' : 'block';
        nextBtn.style.display = newStep === 3 ? 'none' : 'block';
        
        // Mettre à jour la barre de progression
        const progress = ((newStep - 1) / 2) * 100;
        document.getElementById('progressBar').style.width = `${progress}%`;
        document.getElementById('progressBar').setAttribute('aria-valuenow', progress);
    }
}

// Fonction pour définir l'étape active
function setStep(stepNumber) {
    const currentStep = getCurrentStep();
    const direction = stepNumber - currentStep;
    switchStep(direction);
}

// Variables globales pour les boutons
let prevButton = null;
let nextButton = null;
let step1Text = null;
let step2Text = null;

// Variable pour suivre l'état d'initialisation
let isInitialized = false;

// Fonction d'initialisation principale
function initPage() {
    if (isInitialized) {
        console.log('🔄 La page est déjà initialisée');
        return;
    }
    
    console.log('🔵 Initialisation de la page edit-event.js');
    
    try {
        initializeButtonHandlers();
        
        // Initialiser le gestionnaire d'upload de logo
        const logoInput = document.getElementById('logoInput');
        if (logoInput) {
            console.log('⚙️ Configuration du gestionnaire d\'upload de logo');
            logoInput.addEventListener('change', function() {
                handleLogoUpload(this);
            });
        }

        // Initialiser le sélecteur d'organisateur
        initializeOrganizerSelect();
        
        // Définir l'étape initiale
        setStep(1);
        
        isInitialized = true;
        console.log('✅ Initialisation terminée avec succès');
    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation:', error);
    }
}

// Attendre que le DOM soit chargé
document.addEventListener('DOMContentLoaded', function() {
    initPage();
});

// Si le document est déjà chargé, initialiser immédiatement
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initPage();
}

// Fonction pour sauvegarder l'événement
async function saveEvent() {
    try {
        const form = document.getElementById('createEventForm');
        if (!form || !validateCurrentStep(2)) {
            console.log('Formulaire non trouvé ou validation échouée');
            return;
        }

        // Désactiver le bouton pendant la sauvegarde
        const saveButton = document.getElementById('nextButton');
        const originalText = saveButton.innerHTML;
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Enregistrement...';

        // Récupérer les données du formulaire
        const formData = new FormData(form);
        
        // Ajouter les contacts
        const contacts = [];
        const contactNames = document.getElementsByName('contact_names[]');
        const contactNumbers = document.getElementsByName('contact_numbers[]');
        for (let i = 0; i < contactNames.length; i++) {
            if (contactNames[i].value || contactNumbers[i].value) {
                contacts.push({
                    name: contactNames[i].value,
                    phone: contactNumbers[i].value
                });
            }
        }
        formData.append('contacts', JSON.stringify(contacts));

        // Ajouter l'ID de l'événement
        formData.append('eventId', window.eventId);

        // Ajouter les coordonnées
        const latitude = document.querySelector('input[name="latitude"]').value;
        const longitude = document.querySelector('input[name="longitude"]').value;
        if (latitude && longitude) {
            formData.append('coordinates', `${latitude},${longitude}`);
        }
        
        // Envoyer vers l'endpoint de mise à jour
        const response = await fetch('/api/events/update.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        console.log('Réponse du serveur:', result);
        if (result.success) {
            showToast('Événement mis à jour avec succès', 'success');
            // Rediriger vers la page de l'événement après un court délai
            setTimeout(() => {
                window.location.href = `/pages/events/event.php?id=${window.eventId}`;
            }, 1500);
        } else {
            throw new Error(result.message || 'Erreur lors de la mise à jour');
        }
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        showToast(error.message || 'Erreur lors de la sauvegarde', 'error');
    } finally {
        // Réactiver le bouton
        const saveButton = document.getElementById('nextButton');
        saveButton.disabled = false;
        saveButton.innerHTML = '<span class="step2-text">Enregistrer <i class="bi bi-check-lg"></i></span>';
    }
}

// Fonction pour afficher les notifications (à ajouter à la fin du fichier)
function showToast(message, type = 'info') {
    // Utiliser Bootstrap Toast si disponible
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        const container = document.getElementById('toast-container') || createToastContainer();
        container.appendChild(toastEl);
        
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        
        // Supprimer après la fermeture
        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    } else {
        // Fallback si Bootstrap n'est pas disponible
        alert(message);
    }
}

// Créer le conteneur de toast s'il n'existe pas
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

// Fonction pour gérer le téléchargement d'un fichier GPX
async function handleGpxUpload(input, routeIndex) {
    const file = input.files[0];
    if (!file) return;

    if (!validateGpxFile(file)) {
        showFieldError(input, 'Le fichier doit être au format GPX');
        return;
    }

    // Supprimer l'ancien GPX s'il existe
    if (currentGpxLayers[routeIndex]) {
        // Supprimer les flèches de direction
        if (currentGpxLayers[routeIndex].arrows) {
            currentGpxLayers[routeIndex].arrows.forEach(arrow => gpxMap.removeLayer(arrow));
        }
        gpxMap.removeLayer(currentGpxLayers[routeIndex]);
        delete currentGpxLayers[routeIndex];
    }

    // Créer un FormData pour l'upload
    const formData = new FormData();
    formData.append('gpx_file', file);
    formData.append('route_index', routeIndex);
    formData.append('event_id', window.eventId);

    try {
        // Envoyer le fichier au serveur
        const response = await fetch('/api/events/upload-gpx.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Erreur lors de l\'upload du fichier');
        }

        const result = await response.json();

        if (result.success) {
            // Charger le GPX sur la carte
            const gpxResponse = await fetch(result.gpx_url);
            if (!gpxResponse.ok) throw new Error('Erreur lors du chargement du GPX');
            const gpxData = await gpxResponse.text();
            
            // Créer une nouvelle couche GPX avec debug
            console.log('📥 Création de la couche GPX avec options:', {
                async: true,
                marker_options: {
                    startIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-start.png',
                    endIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-end.png',
                    shadowUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-shadow.png',
                    iconSize: [33, 50],
                    shadowSize: [50, 50],
                    iconAnchor: [16, 45],
                    shadowAnchor: [16, 47]
                }
            });
            
            const gpx = new L.GPX(gpxData, {
                async: true,
                polyline_options: {
                    color: 'blue',
                    weight: 3,
                    opacity: 0.7
                },
                marker_options: {
                    startIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-start.png',
                    endIconUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-icon-end.png',
                    shadowUrl: 'https://rando.partageonslaforet.be/assets/images/gpx/pin-shadow.png',
                    iconSize: [33, 50],
                    shadowSize: [50, 50],
                    iconAnchor: [16, 45],
                    shadowAnchor: [16, 47]
                }
            });

            // Debug des événements GPX
            gpx.on('addpoint', function(e) {
                console.log('📍 Point GPX ajouté:', {
                    type: e.point_type,
                    point: e.point,
                    marker: e.marker
                });
            });

            gpx.on('loaded', function(e) {
                console.log('✅ GPX chargé avec succès');
                
                // Debug des couches
                const layers = e.target.getLayers();
                console.log('🗺️ Couches GPX:', layers);
                
                // Debug des marqueurs
                const markers = layers.filter(layer => layer instanceof L.Marker);
                console.log('📌 Marqueurs trouvés:', markers.length);
                
                markers.forEach((marker, index) => {
                    console.log(`📍 Marqueur ${index}:`, {
                        position: marker.getLatLng(),
                        options: marker.options
                    });
                });

                // Debug du contenu GPX
                console.log('📊 Métadonnées GPX:', {
                    name: e.target.get_name(),
                    distance: e.target.get_distance(),
                    elevation_gain: e.target.get_elevation_gain(),
                    elevation_loss: e.target.get_elevation_loss(),
                    total_time: e.target.get_total_time()
                });

                // Mettre à jour les champs
                const distance = (e.target.get_distance() / 1000).toFixed(1);
                const elevation = Math.round(e.target.get_elevation_gain());

                const distanceInput = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
                const elevationInput = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);

                if (distanceInput) distanceInput.value = distance;
                if (elevationInput) elevationInput.value = elevation;

                // Ajouter des flèches de direction
                const track = layers.find(layer => layer instanceof L.Polyline);
                if (track) {
                    console.log('🛣️ Parcours trouvé, ajout des flèches');
                    
                    const arrows = L.polylineDecorator(track, {
                        patterns: [
                            {
                                offset: '5%',
                                repeat: '15%',
                                symbol: L.Symbol.arrowHead({
                                    pixelSize: 15,
                                    pathOptions: {
                                        color: '#004de8',
                                        fillOpacity: 1,
                                        weight: 2
                                    }
                                })
                            }
                        ]
                    }).addTo(gpxMap);

                    if (!currentGpxLayers[routeIndex].arrows) {
                        currentGpxLayers[routeIndex].arrows = [];
                    }
                    currentGpxLayers[routeIndex].arrows.push(arrows);
                }

                // Centrer la carte
                const bounds = e.target.getBounds();
                if (bounds) {
                    gpxMap.fitBounds(bounds);
                }
            });

            gpx.on('error', function(e) {
                console.error('❌ Erreur lors du chargement du GPX:', e.error);
            });

            // Ajouter la couche à la carte et la stocker
            gpx.addTo(gpxMap);
            currentGpxLayers[routeIndex] = gpx;

            // Mettre à jour le champ caché avec l'URL du GPX
            const gpxInput = document.querySelector(`input[name="routes[${routeIndex}][gpx_file]"]`);
            if (gpxInput) {
                gpxInput.value = result.gpx_url;
            }

            showToast('GPX uploadé avec succès', 'success');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showToast('Erreur lors de l\'upload du GPX', 'error');
    }
}

// Fonction pour ajouter un parcours
function addRoute() {
    const routesContainer = document.getElementById('routes-container');
    const routeIndex = document.querySelectorAll('.route-container').length;
    
    const routeHtml = `
        <div class="route-container" data-index="${routeIndex}">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h5 class="card-title mb-0">Parcours ${routeIndex + 1}</h5>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeGpx(${routeIndex})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="route-name-${routeIndex}" class="form-label">Nom du parcours</label>
                            <input type="text" id="route-name-${routeIndex}" name="routes[${routeIndex}][name]" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="route-gpx-${routeIndex}" class="form-label">Fichier GPX</label>
                            <input type="file" id="route-gpx-${routeIndex}" name="routes[${routeIndex}][gpx]" class="form-control" accept=".gpx" required>
                        </div>
                        <div class="col-md-6">
                            <label for="route-distance-${routeIndex}" class="form-label">Distance (km)</label>
                            <input type="number" step="0.1" id="route-distance-${routeIndex}" name="routes[${routeIndex}][distance]" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="route-elevation-${routeIndex}" class="form-label">Dénivelé (m)</label>
                            <input type="number" id="route-elevation-${routeIndex}" name="routes[${routeIndex}][elevation]" class="form-control" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    routesContainer.insertAdjacentHTML('beforeend', routeHtml);
    initializeRouteFieldListeners(routesContainer.lastElementChild);
}

// Fonction pour valider la transition d'étape
function validateStepTransition(currentStep, nextStep) {
    if (currentStep === 1 && nextStep === 2) {
        // Vérifier qu'il y a au moins un GPX avant de passer à l'étape 2
        const totalGpxLayers = Object.keys(currentGpxLayers).length;
        if (totalGpxLayers === 0) {
            showToast('Au moins un parcours GPX doit être présent', 'error');
            return false;
        }
    }
    return true;
}

// Fonction pour changer d'étape
function switchStep(direction) {
    const currentStep = getCurrentStep();
    const newStep = currentStep + direction;
    
    // Vérifier si le changement d'étape est possible
    if (newStep >= 1 && newStep <= 3) {
        // Valider la transition d'étape
        if (!validateStepTransition(currentStep, newStep)) {
            return;
        }
        
        // Mettre à jour les classes active
        document.querySelector(`.step[data-step="${currentStep}"]`).classList.remove('active');
        document.querySelector(`.step[data-step="${newStep}"]`).classList.add('active');
        
        // Mettre à jour l'affichage des contenus d'étapes
        document.querySelectorAll('.step-content').forEach(content => {
            content.classList.add('d-none');
        });
        document.getElementById(`step${newStep}`).classList.remove('d-none');
        
        // Mettre à jour les boutons
        const prevBtn = document.querySelector('.prev-step');
        const nextBtn = document.querySelector('.next-step');
        
        prevBtn.style.display = newStep === 1 ? 'none' : 'block';
        nextBtn.style.display = newStep === 3 ? 'none' : 'block';
        
        // Mettre à jour la barre de progression
        const progress = ((newStep - 1) / 2) * 100;
        document.getElementById('progressBar').style.width = `${progress}%`;
        document.getElementById('progressBar').setAttribute('aria-valuenow', progress);
    }
}

// Fonction pour définir l'étape active
function setStep(stepNumber) {
    const currentStep = getCurrentStep();
    const direction = stepNumber - currentStep;
    switchStep(direction);
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Initialisation de la page edit-event.js');
    
    // Initialiser la carte de localisation
    initializeLocationMap();
    
    // Initialiser la carte GPX
    await initializeGpxMap();
    
    // Initialiser le sélecteur d'organisateur
    initializeOrganizerSelect();
    
    // Initialiser l'upload du logo
    const logoInput = document.getElementById('logoInput');
    if (logoInput) {
        logoInput.addEventListener('change', function() {
            handleLogoUpload(this);
        });
        console.log('Event handler configuré pour l\'upload du logo');
    }

    // Initialiser l'étape courante
    setStep(1);
    console.log('Étape initiale configurée');
});

// Fonction pour initialiser le sélecteur d'organisateur
function initializeOrganizerSelect() {
    const organizerSelect = document.getElementById('organizerSelect');
    const useProfileInfo = document.getElementById('useProfileInfo');
    const organizerFields = document.getElementById('organizerFields');

    if (organizerSelect) {
        organizerSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            if (selectedValue) {
                // Un organisateur existant est sélectionné
                organizerFields.style.display = 'none';
                if (useProfileInfo) {
                    useProfileInfo.checked = false;
                    useProfileInfo.disabled = true;
                }
            } else {
                // "Nouvel organisateur" est sélectionné
                organizerFields.style.display = 'block';
                if (useProfileInfo) {
                    useProfileInfo.disabled = false;
                }
            }
        });
    }

    if (useProfileInfo) {
        useProfileInfo.addEventListener('change', function() {
            if (this.checked) {
                // Remplir les champs avec les informations du profil
                fetch('/api/users/profile.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const profile = data.profile;
                            document.getElementById('organizerName').value = profile.organization_name || '';
                            document.getElementById('organizerAddress').value = profile.address || '';
                            document.getElementById('organizerDescription').value = profile.description || '';
                            document.getElementById('organizerWebsite').value = profile.website || '';
                            document.getElementById('organizerPhone').value = profile.phone || '';
                            document.getElementById('organizerEmail').value = profile.email || '';
                            
                            // Mettre à jour la prévisualisation du logo si disponible
                            const logoPreview = document.getElementById('logoPreview');
                            if (logoPreview && profile.logo) {
                                logoPreview.src = profile.logo;
                                logoPreview.style.display = 'block';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors de la récupération du profil:', error);
                        showToast('Erreur lors de la récupération de vos informations', 'error');
                    });
            }
        });
    }
}

// Fonction pour gérer l'upload du logo
function handleLogoUpload(input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    if (file.size > 2 * 1024 * 1024) { // 2Mo max
        showToast('Le logo ne doit pas dépasser 2Mo', 'warning');
        input.value = ''; // Réinitialiser l'input file
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('logoPreview');
        if (preview) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
    };
    reader.readAsDataURL(file);
}

// Fonction pour initialiser les gestionnaires de boutons
function initializeButtonHandlers() {
    console.log('🔵 Début initialisation des boutons...');
    
    // Récupérer les références des boutons
    nextButton = document.getElementById('nextButton');
    prevButton = document.getElementById('prevButton');
    step1Text = document.getElementById('step1Text');
    step2Text = document.getElementById('step2Text');

    console.log('🔍 Éléments trouvés:', {
        nextButton: nextButton ? 'trouvé' : 'non trouvé',
        prevButton: prevButton ? 'trouvé' : 'non trouvé',
        step1Text: step1Text ? 'trouvé' : 'non trouvé',
        step2Text: step2Text ? 'trouvé' : 'non trouvé'
    });

    if (!nextButton || !step1Text || !step2Text) {
        throw new Error('Certains éléments de boutons sont manquants');
    }

    // Configurer le gestionnaire d'événements pour le bouton suivant
    nextButton.addEventListener('click', (e) => {
        e.preventDefault();
        console.log('🖱️ Clic sur le bouton suivant');
        const currentStep = getCurrentStep();
        console.log('📍 Étape actuelle:', currentStep);
        
        if (currentStep === 2) {
            console.log('💾 Appel de saveEvent()');
            saveEvent();
        } else {
            console.log('➡️ Appel de switchStep(1)');
            switchStep(1);
        }
    });

    // Configurer le gestionnaire d'événements pour le bouton précédent
    if (prevButton) {
        prevButton.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('🖱️ Clic sur le bouton précédent');
            switchStep(-1);
        });
    }

    console.log('✅ Initialisation des boutons terminée');
}