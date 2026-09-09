// Fonction pour extraire le message d'erreur PHP
function extractPhpErrorMessage(html) {
    // Chercher un message d'erreur PHP dans le HTML
    const errorMatch = html.match(/Fatal error: (.+?) in/);
    if (errorMatch) {
        return errorMatch[1];
    }
    
    // Si pas d'erreur fatale, chercher une autre erreur
    const otherErrorMatch = html.match(/Error: (.+?) in/);
    if (otherErrorMatch) {
        return otherErrorMatch[1];
    }
    
    return null;
}

// Fonction pour mettre à jour l'événement
async function updateEvent() {
    try {
        hideGlobalErrors();
        
        // Valider le formulaire
        if (!validateForm()) {
            return;
        }

        const form = document.getElementById('createEventForm');
        const formData = new FormData(form);
        
        // Debug: afficher les données envoyées
        for (let pair of formData.entries()) {
        }
        
        // Ajouter l'ID de l'événement depuis l'URL
        const urlParams = new URLSearchParams(window.location.search);
        const eventId = urlParams.get('id');
        formData.append('eventId', eventId);

        const response = await fetch('/api/events/update.php', {
            method: 'POST',
            body: formData
        });


        // Lire la réponse
        const responseText = await response.text();

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
            throw new Error(data.message || 'Erreur lors de la mise à jour de l\'événement');
        }

        // Rediriger vers la page de l'événement
        window.location.href = '/pages/user/my-events.php?success=update';

    } catch (error) {
        console.error('Erreur lors de la mise à jour:', error);
        showGlobalErrors([error.message]);
    }
}

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

        // Passer à l'étape suivante
        switchStep(1);

    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        showGlobalErrors([error.message]);
    }
}

// Fonction pour changer d'étape
function switchStep(direction) {
    
    const currentStep = getCurrentStep();
    
    const newStep = currentStep + direction;
    
    // Vérifier si l'étape est valide
    if (newStep < 1 || newStep > 2) {
        return;
    }

    // Validation avant de passer à l'étape suivante
    if (direction > 0) {
        if (!validateCurrentStep(currentStep)) {
            return;
        }
    }

    // Changer l'étape active
    setStep(newStep);
}

// Fonction pour définir l'étape active
function setStep(stepNumber) {
    
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
    
}

// Fonction pour obtenir l'étape courante
function getCurrentStep() {
    const step1Content = document.getElementById('step1');
    const step2Content = document.getElementById('step2');
    
        step1: step1Content,
        step2: step2Content,
        step2Hidden: step2Content ? step2Content.classList.contains('d-none') : 'N/A'
    });
    
    if (step2Content && !step2Content.classList.contains('d-none')) {
        return 2;
    }
    return 1;
}

// Fonction pour valider l'étape courante
function validateCurrentStep(step) {
    
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
    try {
        const form = document.getElementById('createEventForm');
        if (!form || !validateCurrentStep(2)) {
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
        if (result.success) {
            showToast('Événement mis à jour avec succès', 'success');
            // Rediriger vers la page de l'événement après un court délai
            setTimeout(() => {
                window.location.href = `/events/event-detail.php?id=${window.eventId}`;
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
    const distanceField = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
    if (distanceField) {
        distanceField.value = distance;
        
        // Déclencher l'événement change manuellement
        const event = new Event('change', { bubbles: true });
        distanceField.dispatchEvent(event);
        
        hideFieldError(distanceField);
    }
}

// Fonction pour mettre à jour le dénivelé d'un parcours
function updateRouteElevation(routeIndex, elevation) {
    const elevationField = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);
    if (elevationField) {
        elevationField.value = elevation;
        
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
    
    if (!field) {
        console.error('Champ non trouvé');
        return;
    }

    field.classList.remove('is-invalid');
    const feedback = field.parentElement.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.style.display = 'none';
    }
    
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
let gpxMap = null;
let currentGpxLayers = {};

// Fonction pour initialiser la carte GPX
async function initializeGpxMap() {
    
    // Vérifier si le plugin GPX est chargé, sinon le charger
    if (typeof L.GPX === 'undefined') {
        try {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js';
                script.onload = resolve;
                script.onerror = () => reject(new Error('Échec du chargement du plugin Leaflet-GPX'));
                document.head.appendChild(script);
            });
        } catch (error) {
            console.error('❌ Erreur lors du chargement du plugin Leaflet-GPX:', error);
            return;
        }
    }

    // Initialiser la carte si ce n'est pas déjà fait
    if (!window.map) {
        window.map = L.map('locationMap').setView([50.8503, 4.3517], 8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(window.map);
    }

    // Charger les GPX existants
    const gpxInputs = document.querySelectorAll('input[type="hidden"][name$="[gpx_file]"]');
    
    gpxInputs.forEach(async (input, index) => {
        const routeIndex = input.name.match(/routes\[(\d+)\]/)[1];
        const gpxPath = input.value;
            routeIndex,
            gpxPath
        });
        
        if (gpxPath) {
            try {
                const response = await fetch(gpxPath);
                if (!response.ok) throw new Error('Erreur lors du chargement du GPX');
                const gpxData = await response.text();
                
                const gpxLayer = new L.GPX(gpxData, {
                    async: true,
                    marker_options: {
                        startIconUrl: '/assets/images/pin-icon-start.png',
                        endIconUrl: '/assets/images/pin-icon-end.png',
                        shadowUrl: '/assets/images/pin-shadow.png'
                    }
                });

                gpxLayer.on('loaded', function(e) {
                    window.map.fitBounds(e.target.getBounds());
                });

                currentGpxLayers[routeIndex] = gpxLayer;
                gpxLayer.addTo(window.map);
            } catch (error) {
                console.error('❌ Erreur lors du chargement du GPX:', error);
                showToast('Erreur lors du chargement du GPX', 'error');
            }
        }
    });
}

// Fonction pour réinitialiser la carte et les informations GPX
function resetGpxMap() {
    // Supprimer l'ancienne carte si elle existe
    if (window.map) {
        window.map.remove();
        window.map = null;
    }
    
    // Réinitialiser les couches GPX
    currentGpxLayers = {};
    
    // Réinitialiser les informations de distance et dénivelé
    const routes = document.querySelectorAll('.route-container');
    routes.forEach(route => {
        const distanceInput = route.querySelector('input[name^="routes"][name$="[distance]"]');
        const elevationInput = route.querySelector('input[name^="routes"][name$="[elevation]"]');
        if (distanceInput) distanceInput.value = '';
        if (elevationInput) elevationInput.value = '';
    });
    
    // Réinitialiser la légende
    updateGpxLegend();
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
        window.map.removeLayer(currentGpxLayers[routeIndex]);
        delete currentGpxLayers[routeIndex];
    }

    // Initialiser la carte si elle n'existe pas
    if (!window.map) {
        await initializeGpxMap();
    }

    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            // Créer une nouvelle couche GPX
            const gpx = new L.GPX(e.target.result, {
                async: true,
                marker_options: {
                    startIconUrl: '/assets/images/pin-icon-start.png',
                    endIconUrl: '/assets/images/pin-icon-end.png',
                    shadowUrl: '/assets/images/pin-shadow.png'
                }
            });

            // Attendre que la couche GPX soit chargée
            gpx.on('loaded', function(e) {
                // Stocker la couche
                currentGpxLayers[routeIndex] = gpx;
                
                // Mettre à jour la distance
                const distance = calculateDistance(gpx.get_distance());
                updateRouteDistance(routeIndex, distance);
                
                // Mettre à jour le dénivelé
                const elevation = calculateElevation(gpx.get_elevation_gain());
                updateRouteElevation(routeIndex, elevation);
                
                // Ajuster la vue de la carte pour montrer tous les GPX
                const bounds = Object.values(currentGpxLayers).reduce((acc, layer) => {
                    if (acc) {
                        acc.extend(layer.getBounds());
                    } else {
                        acc = layer.getBounds();
                    }
                    return acc;
                }, null);
                
                if (bounds) {
                    window.map.fitBounds(bounds);
                }
                
                // Mettre à jour la légende
                updateGpxLegend();
            });

            // Ajouter la couche à la carte
            gpx.addTo(window.map);

        } catch (error) {
            console.error('Erreur lors du chargement du fichier GPX:', error);
            showFieldError(input, 'Erreur lors du chargement du fichier GPX');
        }
    };

    reader.readAsText(file);
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
    
    // Ajouter les event listeners pour tous les champs du parcours
    routeElement.querySelectorAll('input').forEach(input => {
        
        // Event listener pour l'input
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                hideFieldError(this);
            }
        });

        // Event listener spécial pour la distance
        if (input.name.includes('[distance]')) {
            input.addEventListener('change', function() {
                // Revalider la distance
                if (this.value && parseFloat(this.value) > 0) {
                    hideFieldError(this);
                }
            });
        }

        // Event listener spécial pour le dénivelé
        if (input.name.includes('[elevation]')) {
            input.addEventListener('change', function() {
                // Revalider le dénivelé
                if (this.value && parseFloat(this.value) > 0) {
                    hideFieldError(this);
                }
            });
        }

        // Event listener pour le GPX
        if (input.name.includes('[gpx]')) {
            const routeIndex = input.name.match(/routes\[(\d+)\]/)[1];
            input.addEventListener('change', function() {
                handleGpxUpload(this, routeIndex);
            });
        }
    });
}

// Initialiser les event listeners pour les parcours existants
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialiser les event listeners pour les parcours existants
    const existingRoutes = document.querySelectorAll('.route-item');
    existingRoutes.forEach(route => {
        initializeRouteFieldListeners(route);
    });

    // Initialiser la carte GPX
    initializeGpxMap();
});

// Fonction pour ajouter un champ de contact
function addContactField() {
    const container = document.getElementById('contacts-container');
    if (!container) return;

    const contactItem = document.createElement('div');
    contactItem.className = 'contact-item mb-3';
    contactItem.innerHTML = `
        <div class="row">
            <div class="col-md-5">
                <input type="text" class="form-control" name="contact_names[]" placeholder="Nom du contact">
            </div>
            <div class="col-md-5">
                <input type="text" class="form-control" name="contact_numbers[]" placeholder="Numéro de téléphone">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger" onclick="removeContact(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;

    container.appendChild(contactItem);
}

// Fonction pour supprimer un contact
function removeContact(button) {
    const contactItem = button.closest('.contact-item');
    if (contactItem) {
        contactItem.remove();
    }
}

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

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialiser le sélecteur d'organisateur
    initializeOrganizerSelect();
    
    // Initialiser l'upload du logo
    const logoInput = document.getElementById('organizerLogo');
    if (logoInput) {
        logoInput.addEventListener('change', function() {
            handleLogoUpload(this);
        });
    }

    // Initialiser l'étape courante
    setStep(1);
});

// Variables globales pour les boutons
let prevButton = null;
let nextButton = null;
let step1Text = null;
let step2Text = null;

// Fonction pour initialiser les références aux boutons
function initializeButtons() {
    
    // Récupérer les références des boutons
    nextButton = document.getElementById('nextButton');
    prevButton = document.getElementById('prevButton');
    step1Text = document.getElementById('step1Text');
    step2Text = document.getElementById('step2Text');

        nextButton: nextButton ? 'trouvé' : 'non trouvé',
        prevButton: prevButton ? 'trouvé' : 'non trouvé',
        step1Text: step1Text ? 'trouvé' : 'non trouvé',
        step2Text: step2Text ? 'trouvé' : 'non trouvé'
    });

    if (!nextButton || !step1Text || !step2Text) {
        console.error('❌ Erreur: Certains éléments de boutons sont manquants');
        return false;
    }

    // Configurer le gestionnaire d'événements pour le bouton suivant
    nextButton.addEventListener('click', (e) => {
        e.preventDefault();
        const currentStep = getCurrentStep();
        
        if (currentStep === 2) {
            saveEvent();
        } else {
            switchStep(1);
        }
    });

    // Configurer le gestionnaire d'événements pour le bouton précédent
    if (prevButton) {
        prevButton.addEventListener('click', (e) => {
            e.preventDefault();
            switchStep(-1);
        });
    }

    return true;
}

// Fonction pour mettre à jour les boutons de navigation
function updateNavigationButtons(stepNumber) {
        nextButton: nextButton,
        step1Text: step1Text,
        step2Text: step2Text,
        prevButton: prevButton
    });
    
    if (!nextButton || !step1Text || !step2Text) {
        console.error('Références des boutons non initialisées');
        return;
    }

    // Gérer le bouton précédent
    if (prevButton) {
        prevButton.style.display = stepNumber > 1 ? 'block' : 'none';
    }

    // Gérer le bouton suivant/enregistrer
    if (stepNumber === 2) {
        step1Text.style.display = 'none';
        step2Text.style.display = 'inline';
        nextButton.onclick = () => {
            saveEvent();
        };
    } else {
        step1Text.style.display = 'inline';
        step2Text.style.display = 'none';
        nextButton.onclick = () => {
            switchStep(1);  // Passer à l'étape suivante
        };
    }
}

// Fonction pour définir l'étape active
function setStep(stepNumber) {
    
    // Masquer toutes les étapes
    const allSteps = document.querySelectorAll('.step-content');
    allSteps.forEach(content => {
        content.classList.add('d-none');
    });
    
    // Afficher l'étape demandée
    const stepContent = document.getElementById('step' + stepNumber);
    if (stepContent) {
        stepContent.classList.remove('d-none');
    } else {
        console.error('Étape non trouvée:', `step${stepNumber}`);
    }
    
    // Mettre à jour la barre de progression
    const progressBar = document.getElementById('progressBar');
    if (progressBar) {
        const progress = (stepNumber / 3) * 100;
        progressBar.style.width = `${progress}%`;
        progressBar.setAttribute('aria-valuenow', progress);
    }
    
    // Mettre à jour les boutons de navigation
    updateNavigationButtons(stepNumber);
}

// Initialisation unique au chargement de la page
const initPage = () => {
    
    // Initialiser les boutons
    if (!initializeButtons()) {
        console.error('Échec de l\'initialisation des boutons');
        return;
    }
    
    // Initialiser le sélecteur d'organisateur
    initializeOrganizerSelect();
    
    // Initialiser l'upload du logo
    const logoInput = document.getElementById('organizerLogo');
    if (logoInput) {
        logoInput.addEventListener('change', function() {
            handleLogoUpload(this);
        });
    }

    // Initialiser l'étape courante
    setStep(1);
};

// S'assurer que l'initialisation ne se fait qu'une seule fois
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPage);
} else {
    initPage();
}

// Fonction pour sauvegarder l'événement
async function saveEvent() {
    try {
        const form = document.getElementById('createEventForm');
        if (!form) {
            showToast('Formulaire non trouvé', 'error');
            return;
        }

        // Désactiver le bouton pendant la sauvegarde
        const saveButton = document.querySelector('#nextButton');
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
                    number: contactNumbers[i].value
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
        if (result.success) {
            showToast('Événement mis à jour avec succès', 'success');
            // Rediriger vers la page de l'événement après un court délai
            setTimeout(() => {
                window.location.href = `/events/event-detail.php?id=${window.eventId}`;
            }, 1500);
        } else {
            throw new Error(result.message || 'Erreur lors de la mise à jour');
        }
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        showToast(error.message || 'Erreur lors de la sauvegarde', 'error');
    } finally {
        // Réactiver le bouton
        const saveButton = document.querySelector('#nextButton');
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

// Fonction pour supprimer un GPX
function removeGpx(routeIndex) {
    // Supprimer la couche de la carte
    if (currentGpxLayers[routeIndex]) {
        window.map.removeLayer(currentGpxLayers[routeIndex]);
        delete currentGpxLayers[routeIndex];
    }

    // Supprimer le conteneur du parcours
    const routeContainer = document.querySelector(`.route-container[data-index="${routeIndex}"]`);
    if (routeContainer) {
        routeContainer.remove();
    }
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
        window.map.removeLayer(currentGpxLayers[routeIndex]);
        delete currentGpxLayers[routeIndex];
    }

    // Initialiser la carte si elle n'existe pas
    if (!window.map) {
        await initializeGpxMap();
    }

    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            // Créer une nouvelle couche GPX
            const gpx = new L.GPX(e.target.result, {
                async: true,
                marker_options: {
                    startIconUrl: '/assets/images/pin-icon-start.png',
                    endIconUrl: '/assets/images/pin-icon-end.png',
                    shadowUrl: '/assets/images/pin-shadow.png'
                }
            });

            // Attendre que la couche GPX soit chargée
            gpx.on('loaded', function(e) {
                // Stocker la couche
                currentGpxLayers[routeIndex] = gpx;
                
                // Mettre à jour la distance
                const distance = calculateDistance(gpx.get_distance());
                updateRouteDistance(routeIndex, distance);
                
                // Mettre à jour le dénivelé
                const elevation = calculateElevation(gpx.get_elevation_gain());
                updateRouteElevation(routeIndex, elevation);
                
                // Ajuster la vue de la carte pour montrer tous les GPX
                const bounds = Object.values(currentGpxLayers).reduce((acc, layer) => {
                    if (acc) {
                        acc.extend(layer.getBounds());
                    } else {
                        acc = layer.getBounds();
                    }
                    return acc;
                }, null);
                
                if (bounds) {
                    window.map.fitBounds(bounds);
                }
                
                // Mettre à jour la légende
                updateGpxLegend();
            });

            // Ajouter la couche à la carte
            gpx.addTo(window.map);

        } catch (error) {
            console.error('Erreur lors du chargement du fichier GPX:', error);
            showFieldError(input, 'Erreur lors du chargement du fichier GPX');
        }
    };

    reader.readAsText(file);
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