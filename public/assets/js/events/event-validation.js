/**
 * Fichier: /assets/js/events/event-validation.js
 * Rôle: Formulaire multi‑étapes de création/édition (validations, auto‑save, cartes GPX, preview/publish).
 * Utilisation: modales/pages de création/édition d’événement; expose utilitaires globaux pour la carte.
 * Dépendances: Fetch API (/api/events/*), Leaflet/leaflet-gpx, DOM (containers/form inputs), debounce.
 */
// Export des fonctions pour les cartes
window.initializeLocationMap = initLocationMap;
window.initializeGpxMap = initGpxMap;
window.initializeMaps = initializeMaps;
window.updateGpxLegend = updateGpxLegend;
window.handleGpxUpload = handleGpxUpload;

// Variables globales
let currentStep = 1;
let totalSteps = 3; 
let mapInitialized = false;
window.currentGpxLayers = window.currentGpxLayers || {}; 

// Palette de couleurs pour les parcours
const routeColors = [
    '#3A8A3D', // Vert
    '#990047', // Rouge
    '#3498db', // Bleu
    '#f1c40f', // Jaune
    '#9b59b6', // Violet
    '#1abc9c', // Turquoise
    '#d35400', // Orange
    '#34495e'  // Bleu foncé
];

// Debounce utilitaire pour les opérations asynchrones (scope global)
function debounceAsync(fn, delay = 800) {
    let timer = null;
    let lastPromise = Promise.resolve();
    return (...args) => {
        clearTimeout(timer);
        return new Promise((resolve) => {
            timer = setTimeout(() => {
                lastPromise = Promise.resolve(fn(...args)).then(resolve).catch(resolve);
            }, delay);
        });
    };
}

// Auto-save brouillon (silencieux) — scope global
const _saveDraftRaw = async (reason = 'auto') => {
    try {
        const formData = buildEnrichedFormData(false);
        if (!formData) return;
        formData.set('saveReason', reason);

        const res = await fetch('/api/events/save_draft.php', { method: 'POST', body: formData });
        const data = await res.json().catch(() => ({}));

        if (data && data.success && data.draftId) {
            const draftIdInput = document.querySelector('input[name="draftId"]');
            if (draftIdInput) draftIdInput.value = data.draftId;
            window.draftId = data.draftId;
            updateRouteFieldsFromServer(data);
        }
        return data;
    } catch (e) {
        console.warn('Auto-save échoué:', e);
        return null;
    }
};

const maybeSaveDraft = debounceAsync(_saveDraftRaw, 800);

// Construction du FormData enrichi (contacts, parcours, organisateur)
function buildEnrichedFormData(includeFiles = true) {
    const form = document.getElementById('createEventForm');
    if (!form) return null;

    const formData = new FormData(form);

    const draftIdInput = document.querySelector('input[name="draftId"]');
    if (draftIdInput && draftIdInput.value) {
        formData.set('draftId', draftIdInput.value);
    }

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    if (latInput && lngInput && latInput.value && lngInput.value) {
        formData.set('coordinates', `${latInput.value},${lngInput.value}`);
    }

    const contactsContainer = document.getElementById('contacts-container');
    if (contactsContainer) {
        const contacts = [];
        const contactDivs = contactsContainer.getElementsByClassName('contact-field');
        Array.from(contactDivs).forEach((div) => {
            const name = div.querySelector('input[name$="[name]"]')?.value || '';
            const email = div.querySelector('input[name$="[email]"]')?.value || '';
            const phone = div.querySelector('input[name$="[phone]"]')?.value || '';
            if (name || email || phone) {
                contacts.push({ name, email, phone });
            }
        });
        if (contacts.length > 0) {
            formData.set('contacts', JSON.stringify(contacts));
        }
    }

    const routesContainer = document.getElementById('routes-container');
    if (routesContainer) {
        const routes = [];
        const routeDivs = routesContainer.querySelectorAll(':scope > .mb-4');
        routeDivs.forEach((div) => {
            const name = div.querySelector('input[name$="[name]"]')?.value || '';
            const category_id = div.querySelector('select[name$="[category_id]"]')?.value || '';
            const distance = div.querySelector('input[name$="[distance]"]')?.value || '';
            const elevation = div.querySelector('input[name$="[elevation]"]')?.value || '';
            const price = div.querySelector('input[name$="[price]"]')?.value || '';
            const description = div.querySelector('textarea[name$="[description]"]')?.value || '';
            const gpxInput = div.querySelector('input[type="file"][name$="[gpx]"]');
            const gpxFileInput = div.querySelector('input[type="hidden"][name$="[gpx_file]"]');
            const downloadable = div.querySelector('input[type="checkbox"][name$="[gpx_downloadable]"]')?.checked || false;
            if (name || distance || elevation || (gpxFileInput && gpxFileInput.value) || (gpxInput && gpxInput.files[0])) {
                routes.push({
                    name,
                    category_id,
                    distance,
                    elevation,
                    price,
                    description,
                    gpx_file: gpxFileInput ? gpxFileInput.value : '',
                    gpx_downloadable: downloadable
                });
            }
        });
        if (routes.length > 0) {
            formData.set('routes', JSON.stringify(routes));
        }
    }

    const organizerSelect = document.querySelector('select[name="organizerId"]');
    const organizerId = organizerSelect ? organizerSelect.value : null;
    if (organizerId && organizerId !== '' && organizerId !== 'new') {
        formData.set('organizerId', organizerId);
    } else {
        const organizerName = document.getElementById('organizerName');
        const organizerEmail = document.getElementById('organizerEmail');
        const organizerAddress = document.getElementById('organizerAddress');
        const organizerDescription = document.getElementById('organizerDescription');
        const organizerWebsite = document.getElementById('organizerWebsite');
        const organizerPhone = document.getElementById('organizerPhone');

        if (organizerName) formData.set('organizerName', organizerName.value);
        if (organizerEmail) formData.set('organizerEmail', organizerEmail.value);
        if (organizerAddress) formData.set('organizerAddress', organizerAddress.value);
        if (organizerDescription) formData.set('organizerDescription', organizerDescription.value);
        if (organizerWebsite) formData.set('organizerWebsite', organizerWebsite.value);
        if (organizerPhone) formData.set('organizerPhone', organizerPhone.value);
    }

    const logoInput = document.getElementById('organizerLogo');
    if (logoInput && logoInput.files[0]) {
        formData.append('organizerLogo', logoInput.files[0]);
    }

    // L'auto-save ne doit pas renvoyer les fichiers déjà uploadés
    if (!includeFiles) {
        const fileKeys = new Set();
        for (const [key, value] of formData.entries()) {
            if (value instanceof File) {
                fileKeys.add(key);
            }
        }
        fileKeys.forEach(key => formData.delete(key));
    }

    return formData;
}

// Met à jour les champs du formulaire après une sauvegarde réussie
function updateRouteFieldsFromServer(data) {
    if (!data || !data.routes || !Array.isArray(data.routes)) return;
    data.routes.forEach((route) => {
        const index = route.index;
        const hiddenInput = document.querySelector(`input[type="hidden"][name="routes[${index}][gpx_file]"]`);
        const checkbox = document.querySelector(`input[type="checkbox"][name="routes[${index}][gpx_downloadable]"]`);
        const label = document.getElementById(`gpxFileLabel${index}`);

        if (hiddenInput) hiddenInput.value = route.gpx_file || '';
        if (checkbox) checkbox.checked = !!route.gpx_downloadable;
        if (label && route.gpx_file) {
            const parts = (route.gpx_file || '').split('/');
            label.textContent = 'Fichier : ' + parts[parts.length - 1];
        } else if (label) {
            label.textContent = '';
        }
        updateGpxDownloadable(index, !!route.gpx_file);
    });
}

function updateGpxDownloadable(routeIndex, hasGpx) {
    const checkbox = document.querySelector(`input[name="routes[${routeIndex}][gpx_downloadable]"]`);
    if (!checkbox) return;
    checkbox.disabled = !hasGpx;
    if (!hasGpx) {
        checkbox.checked = false;
    }
}

function initGpxDownloadableStates() {
    document.querySelectorAll('input[type="hidden"][name$="[gpx_file]"]').forEach(input => {
        const match = input.name.match(/routes\[(\d+)\]\[gpx_file\]/);
        if (!match) return;
        const index = match[1];
        updateGpxDownloadable(index, !!input.value);
    });
}

// Fonction pour initialiser la modale de prévisualisation
function initPreviewModal() {
    const previewModal = document.getElementById('previewModal');
    
    if (!previewModal) {
        console.warn(" Élément #previewModal non trouvé dans le DOM");
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
            return existingModal;
        }

        // Créer une nouvelle instance
        const modalInstance = new bootstrap.Modal(previewModal, {
            backdrop: 'static',
            keyboard: false,
            focus: true
        });

        return modalInstance;
    } catch (error) {
        console.error(" Erreur lors de l'initialisation de la modale:", error);
        showToast("Impossible d'ouvrir la prévisualisation", "error");
        return null;
    }
}

// Fonction pour afficher la prévisualisation
async function showPreview() {
    try {
        hideGlobalErrors();
        
        const formData = buildEnrichedFormData(false);
        if (!formData) {
            throw new Error("Formulaire introuvable");
        }
        formData.set('saveReason', 'preview');

        // Déterminer l'URL en fonction du mode
        let apiUrl;
        if (window.isEditMode && window.eventId) {
            apiUrl = `../../templates/events/edit-event.php?id=${window.eventId}`;
        } else {
            apiUrl = '../../api/events/save_draft.php';
            
            // Ajouter l'ID du brouillon si disponible
            const hiddenDraftId = document.getElementById('draftId');
            if (hiddenDraftId && hiddenDraftId.value) {
                formData.append('draftId', hiddenDraftId.value);
            }
        }
        
        const saveResponse = await fetch(apiUrl, {
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
        
        if (!saveData.success) {
            throw new Error(saveData.message || "Erreur lors de l'enregistrement");
        }

        // En mode création, gérer l'ID du brouillon
        if (!window.isEditMode) {
            const draftId = saveData.draftId;
            if (!draftId) {
                console.error(" ❌ Pas de draftId dans la réponse:", saveData);
                throw new Error("ID du brouillon manquant dans la réponse");
            }
            
            
            // Mettre à jour le champ caché
            let hiddenDraftId = document.getElementById('draftId');
            if (hiddenDraftId) {
                hiddenDraftId.value = draftId;
                updateRouteFieldsFromServer(saveData);
            } else {
                hiddenDraftId = document.createElement('input');
                hiddenDraftId.type = 'hidden';
                hiddenDraftId.id = 'draftId';
                hiddenDraftId.name = 'draftId';
                hiddenDraftId.value = draftId;
                document.getElementById('createEventForm').appendChild(hiddenDraftId);
            }
        }
        
        // 2. Récupérer la prévisualisation
        let requestData;
        
        if (window.isEditMode && window.eventId) {
            requestData = { eventId: window.eventId };
        } else {
            requestData = { draftId: saveData.draftId };
        }
        
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
        
        if (!previewData.success) {
            throw new Error(previewData.message || "Erreur lors de la prévisualisation");
        }

        // Mettre à jour le contenu de la prévisualisation
        const previewContent = document.getElementById('eventPreview');
        if (!previewContent) {
            throw new Error("L'élément de prévisualisation n'existe pas");
        }

        previewContent.innerHTML = previewData.data.html;

        // Initialiser la carte et les interactions du nouveau template
        if (typeof window.initEventDisplay === 'function') {
            window.initEventDisplay(previewContent);
        }


    } catch (error) {
        console.error(" Erreur:", error);
        showToast(error.message || "Une erreur est survenue", "error");
    }
}

// Remonter le contenu de la modale en haut à chaque changement d'étape
function scrollStepToTop() {
    const modalBody = document.querySelector('#createEventModal .modal-body');
    if (modalBody) {
        modalBody.scrollTop = 0;
    }
    window.scrollTo(0, 0);
}

// S'assurer que la carte GPX est initialisée et correctement redimensionnée
function ensureGpxMapVisible() {
    initGpxMap();
    if (window.gpxMap && typeof window.gpxMap.invalidateSize === 'function') {
        setTimeout(() => window.gpxMap.invalidateSize(), 100);
    }
}

// S'assurer que la carte du rendez-vous est bien affichée et recentrée
function ensureMeetingMapVisible() {
    if (window.meetingMap && window.meetingMarker) {
        setTimeout(function() {
            const pos = window.meetingMarker.getLatLng();
            window.meetingMap.invalidateSize();
            window.meetingMap.setView(pos, 15);
        }, 100);
    }
}

// Fonction pour passer à l'étape suivante
async function nextStep() {

    if (currentStep < totalSteps) {
        if (validateStep(currentStep)) {
            const fromStep = currentStep;
            try {
                // Auto-save avant de quitter l'étape courante
                await maybeSaveDraft('step-next');
                // Si on passe à l'étape 3 (prévisualisation)
                if (currentStep === 2) {
                    
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
                    
                    // Mettre à jour l'interface
                    updateProgress();
                    updateButtons();
                    hideGlobalErrors();
                    scrollStepToTop();
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
                scrollStepToTop();
                if (currentStep === 2) {
                    ensureGpxMapVisible();
                }
            } catch (error) {
                console.error('❌ Erreur lors de la navigation:', error);
                showToast(`Erreur passage étape ${fromStep} → ${fromStep + 1} : ${error.message || 'problème inconnu'}`, "error");
            }
        } else {
            console.error('❌ Validation échouée pour l\'étape', currentStep);
            const stepContent = document.querySelector(`#step${currentStep}`);
            const firstInvalidField = stepContent ? stepContent.querySelector('.is-invalid') : null;
            const label = firstInvalidField
                ? (firstInvalidField.closest('.mb-2, .mb-3, .form-group, .row')?.querySelector('label')?.textContent?.trim()
                   || firstInvalidField.getAttribute('placeholder')
                   || firstInvalidField.name)
                : 'requis';
            showToast(`Erreur sur le champ ${label}`, 'error');
        }
    } else {
    }
}

// Fonction pour revenir à l'étape précédente
function prevStep() {
    if (currentStep <= 1) return;
    
    const fromStep = currentStep;
    try {
        // Auto-save avant de revenir en arrière
        maybeSaveDraft('step-prev');
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
        scrollStepToTop();
        if (currentStep === 2) {
            ensureGpxMapVisible();
        }
        if (currentStep === 1) {
            ensureMeetingMapVisible();
        }
    } catch (error) {
        console.error(' Erreur lors du retour à l\'étape précédente:', error);
        showToast(`Erreur retour étape ${fromStep} → ${fromStep - 1} : ${error.message || 'problème inconnu'}`, "error");
    }
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    
    // Vérifier que nous sommes sur la bonne page
    const createEventForm = document.getElementById('createEventForm');
    if (!createEventForm) {
        return;
    }

    
    initGpxDownloadableStates();

    // Pré-initialiser la modale
    previewModalInstance = initPreviewModal();
    if (previewModalInstance) {
    }

    // Initialiser le bouton de publication
    const publishButton = document.getElementById('publishButton');
    if (publishButton) {
        
        // Supprimer les anciens listeners pour éviter les doublons
        publishButton.replaceWith(publishButton.cloneNode(true));
        const newPublishButton = document.getElementById('publishButton');
        
        // Ajouter le nouveau listener
        newPublishButton.addEventListener('click', async function(e) {
            
            // Empêcher tout comportement par défaut
            e.preventDefault();
            e.stopPropagation();
            
            // Désactiver le bouton pendant la publication et afficher le spinner
            const btn = this;
            const spinner = btn.querySelector('.spinner-border');
            const label = btn.querySelector('.btn-label');
            btn.disabled = true;
            btn.setAttribute('aria-busy','true');
            if (spinner) spinner.classList.remove('d-none');
            if (label) label.textContent = 'Publication...';
            
            try {
                // Appel direct de submitEvent
                await submitEvent();
            } catch (error) {
                console.error(" ❌ Erreur attrapée dans le handler de clic:", error);
                showToast(error.message || "Erreur lors de la publication", "error");
            } finally {
                // Restaurer l'état du bouton si on est toujours sur cette page
                btn.disabled = false;
                btn.removeAttribute('aria-busy');
                if (spinner) spinner.classList.add('d-none');
                if (label) label.textContent = 'Publier';
            }
        });
        
    }

    // Validation à la soumission
    createEventForm.addEventListener('submit', function(event) {
        if (!validateForm()) {
            event.preventDefault();
            event.stopPropagation();
        }
        createEventForm.classList.add('was-validated');
    });
    
    // Validation en temps réel et auto-save différé
    createEventForm.addEventListener('input', function(e) {
        const field = e.target;
        if (field.checkValidity && field.checkValidity()) {
            field.classList.remove('is-invalid');
        }
        if (field.type !== 'file' && !field.name.startsWith('secondaryImages')) {
            maybeSaveDraft('input');
        }
    });
    
    // Initialiser les gestionnaires d'événements
    initializeFormEvents();
    initializeProfileToggle();
    initializeNewOrganizerToggle();
    initializeImageUploads();
    // initLocationMap, setupAddressSearch et initGpxMap sont gérés par create-event.php (meetingMap/gpxMap)
    updateProgress();
    updateButtons();
    updateStepButtons();

    // Navigation directe via les ancres des étapes
    document.querySelectorAll('.step').forEach(stepLink => {
        stepLink.addEventListener('click', async function(e) {
            e.preventDefault();
            const step = parseInt(this.dataset.step, 10);
            if (!isNaN(step)) {
                await setStep(step);
            }
        });
    });
});

// Fonction pour gérer l'upload d'images
function handleImageUpload(input, previewId, maxSize = 5) {
    
    if (!input.files || input.files.length === 0) {
        console.error('❌ Aucun fichier sélectionné');
        return;
    }

    const file = input.files[0];
    if (file.size > maxSize * 1024 * 1024) {
        showToast(`Le fichier ne doit pas dépasser ${maxSize}Mo`, 'warning');
        input.value = '';
        return;
    }

    if (!file.type.startsWith('image/')) {
        showToast('Le fichier doit être une image', 'warning');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById(previewId);
        if (preview) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
    };
    
    reader.onerror = function() {
        showToast('Erreur lors de la lecture du fichier', 'error');
        input.value = '';
    };
    
    reader.readAsDataURL(file);
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

// Fonction pour mettre à jour la légende des GPX
function updateGpxLegend() {
    const legendContent = document.getElementById('gpx-legend-content');
    if (!legendContent) return;

    // Vider la légende
    legendContent.innerHTML = '';

    // Ajouter chaque parcours à la légende
    Object.keys(window.currentGpxLayers).forEach((index) => {
        const layer = window.currentGpxLayers[index];
        const routeNameInput = document.querySelector(`input[name="routes[${index}][name]"]`);
        const routeName = routeNameInput?.value || `Parcours ${parseInt(index) + 1}`;
        const distance = layer.get_distance() ? `${(Math.round(layer.get_distance() / 100) / 10).toFixed(1)} km` : '';
        const elevation = layer.get_elevation_gain() ? `${Math.round(layer.get_elevation_gain())} m` : '';

        const colors = ['#3A8A3D', '#990047', '#3498db', '#f1c40f', '#9b59b6'];
        const color = colors[index % colors.length];

        const legendItem = document.createElement('div');
        legendItem.className = 'legend-item d-flex align-items-center gap-2 bg-light p-2 rounded';
        legendItem.innerHTML = `
            <div class="legend-swatch" style="background-color: ${color};"></div>
            <div>
                <strong>${routeName}</strong>
                ${distance && elevation ? `<br><small class="text-muted">${distance} • ${elevation} D+</small>` : ''}
            </div>
        `;

        legendContent.appendChild(legendItem);
    });

    // Afficher ou masquer la légende selon qu'il y a des parcours ou non
    const legendContainer = document.getElementById('gpx-legend');
    if (legendContainer) {
        legendContainer.style.display = Object.keys(window.currentGpxLayers).length > 0 ? 'block' : 'none';
    }
}

// Exporter la fonction pour qu'elle soit accessible depuis event-maps.js
window.updateGpxLegend = updateGpxLegend;

// Fonction pour ajouter un parcours
function addRoute() {
    const container = document.getElementById('routes-container');
    const routeCount = container.children.length;
    const newIndex = routeCount;
    const categories = window.routeCategories || (typeof routeCategories !== 'undefined' ? routeCategories : []);
    const catOptions = categories.map(c => `<option value="${c.id}">${String(c.name).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</option>`).join('');

    const newRoute = document.createElement('div');
    newRoute.className = 'mb-4';
    newRoute.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Parcours ${newIndex + 1}</h5>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRoute(this)" data-route-index="${newIndex}">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="mb-2">
            <label class="form-label">Nom du parcours</label>
            <input type="text" class="form-control" name="routes[${newIndex}][name]">
        </div>
        <div class="mb-2">
            <label class="form-label required-field">Catégorie du parcours</label>
            <select class="form-select" name="routes[${newIndex}][category_id]" required>
                <option value="">Choisir une catégorie</option>
                ${catOptions}
            </select>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="mb-2">
                    <label class="form-label required-field">Distance (km)</label>
                    <input type="number" step="0.1" class="form-control" name="routes[${newIndex}][distance]" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-2">
                    <label class="form-label">Dénivelé (m)</label>
                    <input type="number" class="form-control" name="routes[${newIndex}][elevation]">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-2">
                    <label class="form-label">Prix (€)</label>
                    <input type="number" step="0.01" class="form-control" name="routes[${newIndex}][price]">
                </div>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label">GPX <small class="form-text text-muted">(Importer un GPX rempli automatiquement la distance et le dénivelé)</small></label>
            <div class="input-group">
                <input type="file" class="form-control" name="routes[${newIndex}][gpx]" accept=".gpx" onchange="handleGpxUpload(this, ${newIndex})">
                <button type="button" class="btn btn-outline-danger" onclick="removeGpx(${newIndex})" title="Supprimer le GPX">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <input type="hidden" name="routes[${newIndex}][gpx_file]" value="">
            <div class="gpx-file-label" id="gpxFileLabel${newIndex}"></div>
        </div>
        <div class="mb-2 form-check">
            <input type="checkbox" class="form-check-input" id="gpxDownloadable${newIndex}" name="routes[${newIndex}][gpx_downloadable]" value="1" checked>
            <label class="form-check-label" for="gpxDownloadable${newIndex}">Autoriser le téléchargement du GPX</label>
        </div>
        <div class="mb-2">
            <label class="form-label">Description du parcours</label>
            <textarea class="form-control" name="routes[${newIndex}][description]" rows="3"></textarea>
        </div>`;

    container.appendChild(newRoute);
    updateGpxDownloadable(newIndex, false);
}

// Fonction pour supprimer un parcours
function removeRoute(button) {
    const routesContainer = document.getElementById('routes-container');
    const routeContainer = button.closest('.mb-4');
    const routeIndex = button.getAttribute('data-route-index');
    
    // Supprimer le GPX de la carte s'il existe
    if (window.currentGpxLayers[routeIndex] && window.gpxMap && typeof window.gpxMap.removeLayer === 'function') {
        window.gpxMap.removeLayer(window.currentGpxLayers[routeIndex]);
        delete window.currentGpxLayers[routeIndex];
    }

    // Supprimer le conteneur du parcours
    if (routeContainer) {
        routeContainer.remove();
    }

    // Mettre à jour les numéros des parcours restants
    const routes = routesContainer.querySelectorAll('.mb-4');
    routes.forEach((route, index) => {
        // Mettre à jour le titre
        const title = route.querySelector('h5');
        if (title) {
            title.textContent = `Parcours ${index + 1}`;
        }

        // Mettre à jour les noms des champs
        const inputs = route.querySelectorAll('input, select, textarea, label');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/routes\[\d+\]/, `routes[${index}]`));
            }
            const htmlFor = input.getAttribute('for');
            if (htmlFor) {
                input.setAttribute('for', htmlFor.replace(/gpxDownloadable\d+/, `gpxDownloadable${index}`));
            }
            const id = input.getAttribute('id');
            if (id) {
                input.setAttribute('id', id.replace(/gpxDownloadable\d+|gpxFileLabel\d+/, (m) => m.replace(/\d+$/, `${index}`)));
            }
        });

        // Mettre à jour l'index du bouton de suppression
        const deleteButton = route.querySelector('button[data-route-index]');
        if (deleteButton) {
            deleteButton.setAttribute('data-route-index', index);
        }

        // Mettre à jour l'attribut onchange du champ GPX
        const gpxInput = route.querySelector('input[type="file"]');
        if (gpxInput) {
            gpxInput.setAttribute('onchange', `handleGpxUpload(this, ${index})`);
        }

        // Mettre à jour l'identifiant du libellé de fichier GPX
        const gpxFileLabel = route.querySelector('.gpx-file-label');
        if (gpxFileLabel) {
            gpxFileLabel.id = `gpxFileLabel${index}`;
        }
    });

    // Mettre à jour la légende des GPX
    updateGpxLegend();
}

// Fonction pour valider tout le formulaire
function validateForm() {
    const errors = [];
    const form = document.getElementById('createEventForm');

    // Liste des champs requis avec leurs messages d'erreur
    const requiredFields = {
        'title': 'Titre de l\'événement',
        'description': 'Description',
        'date': 'Date',
        'registrationOpens': 'Ouverture des inscriptions',
        'registrationCloses': 'Fermeture des inscriptions'
    };

    // Vérifier chaque champ requis dans le formulaire
    for (const [fieldId, fieldName] of Object.entries(requiredFields)) {
        const field = form ? form.querySelector(`[name="${fieldId}"]`) : null;
        if (!field || !field.value) {
            errors.push(`Le champ "${fieldName}" est requis`);
        }
    }

    // Vérifier qu'au moins une catégorie est cochée
    const allCategoryInputs = form ? form.querySelectorAll('input[name="categories[]"]') : [];
    const checkedCategories = form ? form.querySelectorAll('input[name="categories[]"]:checked') : [];
    if (allCategoryInputs.length > 0) {
        try {
        } catch (e) {}
    }
    if (checkedCategories.length === 0) {
        errors.push('Veuillez choisir au moins une catégorie');
    }

    if (errors.length > 0) {
        showGlobalErrors(errors);
        return false;
    }

    return true;
}

// Fonction pour valider une étape
function validateStep(stepNumber) {
    
    const stepContent = document.querySelector(`#step${stepNumber}`);
    
    if (!stepContent) {
        console.error("❌ Étape non trouvée");
        return false;
    }

    let isValid = true;
    let errors = [];

    // Validation spéciale pour les champs de parcours à l'étape 2
    if (stepNumber === 2) {
        // S'assurer que la carte GPX est initialisée et correctement dimensionnée
        initGpxMap();
        if (window.gpxMap && typeof window.gpxMap.invalidateSize === 'function') {
            window.gpxMap.invalidateSize();
        }

        // Vérifier les parcours
        const routesContainer = document.getElementById('routes-container');
        if (routesContainer) {
            const routeInputs = routesContainer.querySelectorAll('input[required]');
            
            routeInputs.forEach((input) => {
                input.classList.remove('is-invalid', 'is-valid');
                
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    const label = input.closest('.mb-2')?.querySelector('label')?.textContent || input.name;
                    showFieldError(input, `Le champ "${label}" est requis`);
                    isValid = false;
                } else {
                    input.classList.add('is-valid');
                    hideFieldError(input);
                }
            });
        }
    }

    // Validation générale des champs requis
    const requiredFields = stepContent.querySelectorAll('[required]');

    requiredFields.forEach((field) => {
        // Ne pas revalider les champs déjà vérifiés
        if (field.classList.contains('is-valid')) return;

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
            
            showFieldError(field, errorMessage);
            isValid = false;
        } else {
            field.classList.add('is-valid');
            hideFieldError(field);
        }
    });

    if (!isValid) {
        return false;
    }

    return true;
}

// Fonction pour mettre à jour la barre de progression
function updateProgress() {
    const progressBar = document.getElementById('progressBar');

    // Calculer le pourcentage de progression basé sur les 3 étapes
    let progressPercentage;
    switch (currentStep) {
        case 1: // L'événement
            progressPercentage = 33;
            break;
        case 2: // Lieu et parcours
            progressPercentage = 66;
            break;
        case 3: // Photos et aperçu
            progressPercentage = 100;
            break;
        default:
            progressPercentage = 0;
    }

    // Mettre à jour la barre de progression si elle existe
    if (progressBar) {
        progressBar.style.width = `${progressPercentage}%`;
        progressBar.setAttribute('aria-valuenow', progressPercentage);
    }

    // Mettre à jour l'état des étapes (indépendamment de la barre de progression)
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

// Navigation directe vers une étape en cliquant sur les ancres du wizard
async function setStep(step) {
    if (step < 1 || step > totalSteps || step === currentStep) return;

    // Retour arrière : navigue directement
    if (step < currentStep) {
        while (currentStep > step) {
            prevStep();
        }
        return;
    }

    // Avance progressif en validant chaque étape intermédiaire
    while (currentStep < step) {
        const before = currentStep;
        await nextStep();
        if (currentStep === before) {
            // Validation échouée ou erreur : on bloque la navigation
            break;
        }
    }
}

// Fonction pour enregistrer le brouillon
async function saveDraft() {
    try {
        const form = document.getElementById('createEventForm');
        if (!form) return;

        const formData = buildEnrichedFormData(false);
        if (!formData) return;

        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Erreur lors de l\'enregistrement du brouillon');
        }

        if (data.draftId) {
            const draftIdInput = document.querySelector('input[name="draftId"]');
            if (draftIdInput) draftIdInput.value = data.draftId;
            window.draftId = data.draftId;
        }
        // Pas de toast visible pour l'auto-save
    } catch (error) {
        console.error('Erreur enregistrement brouillon:', error);
        // Pas de toast en auto-save
    }
}

// Fonction pour mettre à jour l'affichage des boutons
function updateButtons() {
    const nextButton = document.getElementById('nextButton');
    const prevButton = document.getElementById('prevButton');
    const publishButton = document.getElementById('publishButton');
    const saveDraftButton = document.getElementById('saveDraftButton');
    const nextStepIcon = document.getElementById('nextStepIcon');
    
    if (prevButton) {
        if (currentStep === 1) {
            prevButton.classList.add('d-none');
        } else {
            prevButton.classList.remove('d-none');
        }
    }

    if (nextButton) {
        if (currentStep === 3) {
            nextButton.classList.add('d-none');
        } else {
            nextButton.classList.remove('d-none');
            if (currentStep === 1) {
                nextButton.innerHTML = 'Continuer : lieu et parcours <i class="bi bi-arrow-right"></i>';
            } else if (currentStep === 2) {
                nextButton.innerHTML = 'Continuer : photos et aperçu <i class="bi bi-arrow-right"></i>';
            }
        }
    }

    if (saveDraftButton) {
        // Masquer définitivement le bouton enregistrer le brouillon
        saveDraftButton.classList.add('d-none');
    }

    if (nextStepIcon) {
        nextStepIcon.classList.toggle('d-none', currentStep === 3);
    }

    if (publishButton) {
        if (currentStep === 3) {
            publishButton.classList.remove('d-none');
            publishButton.className = 'btn btn-primary';
        } else {
            publishButton.classList.add('d-none');
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
    // Normaliser les types d'alerte Bootstrap
    const bootstrapType = type === 'error' ? 'danger' : type;
    const iconClass = type === 'success' ? 'bi-check-circle-fill' : (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill');
    const textClass = type === 'warning' ? 'text-dark' : 'text-white';
    // Créer l'élément toast
    const toastEl = document.createElement('div');
    toastEl.classList.add('toast', `bg-${bootstrapType}`, textClass, 'position-fixed', 'top-50', 'start-50', 'translate-middle');
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
        <div class="toast-body text-center">
            <i class="bi ${iconClass} me-2"></i>
            ${message}
        </div>
    `;
    document.body.appendChild(toastEl);
    toastEl.style.zIndex = '2000';
    
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
    // NOTE : les listeners mainImage/secondaryImages sont définis dans event-images.js
    // (single source of truth) pour éviter les doubles soumissions.
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
            const response = await fetch(`/api/organization-profil/get_profile.php?id=${organizerId}`);
            const data = await response.json();
            
            if (data.success && data.profile) {
                fillOrganizerFields(data.profile);
                if (data.profile.logo_path) {
                    displayLogo(data.profile.logo_path);
                } else {
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
            const response = await fetch('/api/organization-profil/get_profile.php');
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
        const logoPreview = document.getElementById('logoPreview');
        if (logoPreview) {
            logoPreview.src = logoPath;
            logoPreview.style.display = 'block';
        } else {
            console.error("Élément logoPreview non trouvé");
        }
    }

    // Réinitialiser le logo
    function resetLogo() {
        const logoPreview = document.getElementById('logoPreview');
        const logoInput = document.getElementById('organizerLogo');
        if (logoPreview) {
            logoPreview.src = '';
            logoPreview.style.display = 'none';
        }
        if (logoInput) {
            logoInput.value = '';
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

// Fonction pour mettre à jour l'adresse
function updateAddress(address) {
    const addressInput = document.getElementById('address');
    if (!addressInput) {
        console.error('Champ adresse non trouvé');
        return;
    }

    if (typeof address !== 'string') {
        console.error('L\'adresse doit être une chaîne de caractères:', address);
        return;
    }

    addressInput.value = address;
    addressInput.classList.add('is-valid');
    addressInput.classList.remove('is-invalid');
    window.addressModified = true;
}

// Fonction pour mettre à jour la position du marqueur
function updateMarkerPosition(lat, lng) {
    if (!window.locationMap || !window.locationMarker) {
        console.error('Carte ou marqueur non initialisé');
        return;
    }

    window.locationMarker.setLatLng([lat, lng]);
    
    // Mettre à jour les champs cachés
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    
    window.locationMap.setView([lat, lng], 13);
}

// Fonction pour configurer la recherche d'adresse
function setupAddressSearch() {
    const searchButton = document.getElementById('searchAddressBtn');
    const addressInput = document.getElementById('address');

    if (!searchButton || !addressInput) {
        console.error('Éléments de recherche non trouvés');
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
                    console.error('Aucun résultat trouvé');
                    alert('Aucune adresse trouvée');
                }
            })
            .catch(error => {
                console.error('Erreur lors de la recherche:', error);
                showToast('Erreur lors de la recherche de l\'adresse', 'error');
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

// Fonction pour traiter le fichier GPX
function handleGpxUpload(input, routeIndex) {

    const fileLabel = document.getElementById(`gpxFileLabel${routeIndex}`);
    if (input.files && input.files[0]) {
        if (fileLabel) fileLabel.textContent = 'Fichier sélectionné : ' + input.files[0].name;
    } else if (fileLabel) {
        fileLabel.textContent = '';
    }

    ensureGpxMapVisible();
    if (!window.gpxMap || typeof window.gpxMap.invalidateSize !== 'function') {
        console.error('Carte GPX non initialisée');
        return;
    }
    
    const file = input.files[0];
    if (!file) {
        return;
    }
    input.value = '';

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const gpxData = e.target.result;

            // Garde : éviter de parser du non-XML (fichier HTML/PDF renommé, etc.)
            if (!/^\s*<\?xml|^\s*<gpx[\s>]/i.test(gpxData)) {
                throw new Error('Le fichier n\'est pas un GPX valide');
            }

            // Créer un élément temporaire pour parser le GPX
            const parser = new DOMParser();
            const gpx = parser.parseFromString(gpxData, 'text/xml');
            
            // Vérifier si le document est bien un GPX
            if (gpx.documentElement.nodeName !== 'gpx') {
                throw new Error('Le fichier n\'est pas un GPX valide');
            }

            // Extraire les points du parcours (trkpt, rtept, wpt, namespaces inclus)
            const getPoints = (tag) => Array.from(gpx.getElementsByTagNameNS('*', tag));
            const trkpts = getPoints('trkpt');
            const rtepts = getPoints('rtept');
            const wpts = getPoints('wpt');
            const points = trkpts.length >= 2 ? trkpts : (rtepts.length >= 2 ? rtepts : wpts);
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
                const ele1 = prev.getElementsByTagNameNS('*', 'ele')[0];
                const ele2 = curr.getElementsByTagNameNS('*', 'ele')[0];
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
            if (window.currentGpxLayers[routeIndex] && window.gpxMap) {
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

            // Forcer le recalcul de la taille pour afficher toutes les tuiles
            if (window.gpxMap && typeof window.gpxMap.invalidateSize === 'function') {
                window.gpxMap.invalidateSize();
            }

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

            updateGpxDownloadable(routeIndex, true);
            uploadGpxToServer(file, routeIndex);
        } catch (error) {
            console.error('Erreur lors du traitement du fichier GPX:', error);
            showToast('Erreur lors du traitement du fichier GPX. Vérifiez que le fichier est valide.', 'error');
        }
    };

    reader.onerror = function() {
        console.error('Erreur lors de la lecture du fichier');
        showToast('Erreur lors de la lecture du fichier', 'error');
    };

    reader.readAsText(file);
}

async function uploadGpxToServer(file, routeIndex) {
    const hiddenInput = document.querySelector(`input[name="routes[${routeIndex}][gpx_file]"]`);
    try {
        const formData = new FormData();
        formData.append('gpx_file', file);
        formData.append('route_index', routeIndex);

        const response = await fetch('/api/events/upload-gpx.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Upload échoué');
        }

        const oldPath = hiddenInput ? hiddenInput.value : '';
        if (hiddenInput) {
            hiddenInput.value = data.gpx_path;
        }
        updateGpxDownloadable(routeIndex, true);
        if (oldPath && oldPath !== data.gpx_path) {
            deleteGpxFile(oldPath);
        }
    } catch (error) {
        showToast('La trace est affichée mais n\'a pas pu être enregistrée. Veuillez réessayer.', 'warning');
        updateGpxDownloadable(routeIndex, !!(hiddenInput && hiddenInput.value));
    }
}

// Fonction pour supprimer un GPX côté serveur
async function deleteGpxFile(gpxPath) {
    if (!gpxPath) return;
    try {
        const response = await fetch('/api/events/delete-gpx.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ gpx_path: gpxPath })
        });
        if (!response.ok) throw new Error('Erreur réseau');
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Échec');
    } catch (error) {
        showToast('Impossible de supprimer l\'ancien GPX du serveur', 'warning');
    }
}

// Fonction pour supprimer un GPX du formulaire
function removeGpx(routeIndex) {
    const hiddenInput = document.querySelector(`input[name="routes[${routeIndex}][gpx_file]"]`);
    if (hiddenInput && hiddenInput.value) {
        deleteGpxFile(hiddenInput.value);
    }

    if (window.currentGpxLayers && window.currentGpxLayers[routeIndex] && window.gpxMap) {
        window.gpxMap.removeLayer(window.currentGpxLayers[routeIndex]);
        delete window.currentGpxLayers[routeIndex];
    }

    const fileInput = document.querySelector(`input[type="file"][name="routes[${routeIndex}][gpx]"]`);
    if (fileInput) fileInput.value = '';
    if (hiddenInput) hiddenInput.value = '';

    const fileLabel = document.getElementById(`gpxFileLabel${routeIndex}`);
    if (fileLabel) fileLabel.textContent = '';

    const distanceInput = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
    const elevationInput = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);
    if (distanceInput) distanceInput.value = '';
    if (elevationInput) elevationInput.value = '';

    updateGpxDownloadable(routeIndex, false);
}

// Fonction pour calculer la distance d'un parcours GPX
function calculateDistance(gpxData) {

    try {
        // Garde : ne pas envoyer du contenu non-XML au parseur
        if (!/^\s*<\?xml|^\s*<gpx[\s>]/i.test(gpxData)) {
            console.error('Contenu non-GPX, distance non calculée');
            return 0;
        }
        const parser = new DOMParser();
        const gpx = parser.parseFromString(gpxData, 'text/xml');
        const points = Array.from(gpx.getElementsByTagName('trkpt'));
        
        if (points.length < 2) {
            console.error('Pas assez de points pour calculer la distance');
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
        console.error('Erreur lors du calcul de la distance:', error);
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
        console.warn('Tentative d\'afficher une erreur sur un champ null:', message);
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
    errorDiv.style.color = 'var(--bs-primary)';
}

// Fonction pour masquer l'erreur d'un champ
function hideFieldError(field) {
    field.classList.remove('is-invalid');
    const feedback = field.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.style.display = 'none';
    }
}

// Réinitialiser le logo
function resetLogo() {
    const logoPreview = document.getElementById('logoPreview');
    const logoInput = document.getElementById('organizerLogo');
    if (logoPreview) {
        logoPreview.src = '';
        logoPreview.style.display = 'none';
    }
    if (logoInput) {
        logoInput.value = '';
    }
}

// Réinitialiser les champs de l'organisateur
function resetOrganizerFields() {
    const organizerFields = document.getElementById('organizerFields');
    if (!organizerFields) {
        console.error('Élément organizerFields non trouvé');
        return;
    }

    // Réinitialiser tous les champs texte et textarea
    const fields = organizerFields.querySelectorAll('input:not([type="file"]), textarea');
    fields.forEach(field => {
        field.value = '';
        field.disabled = false;
    });

    // Réinitialiser le logo
    resetLogo();
    
}

// Fonction pour publier l'événement
async function submitEvent() {
    try {
        hideGlobalErrors();

        // Valider le formulaire
        if (!validateForm()) {
            return;
        }

        const form = document.getElementById('createEventForm');
        const formData = new FormData(form);

        // Sauvegarder une dernière fois tout le formulaire (incl. étape 3)
        await maybeSaveDraft('before-publish');

        // Si on est en mode édition
        if (window.isEditMode && window.eventId) {
            formData.append('eventId', window.eventId);

            // Envoyer vers l'endpoint de mise à jour
            const response = await fetch('../../api/events/update.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Erreur lors de la mise à jour de l\'événement');
            }

            // Redirection post-mise à jour
            try {
                if (sessionStorage.getItem('adminEdit') === '1') {
                    sessionStorage.removeItem('adminEdit');
                    window.location.href = '/pages/admin';
                } else {
                    window.location.href = '/event/' + encodeURIComponent(window.eventId);
                }
            } catch (_) {
                window.location.href = '/event/' + encodeURIComponent(window.eventId);
            }
            return;
        }

        // Code existant pour la création d'événement
        const draftIdInput = document.getElementById('draftId') || document.querySelector('input[name="draftId"]');
        const draftId = draftIdInput ? draftIdInput.value : null;
        if (!draftId) {
            throw new Error('ID du brouillon manquant');
        }

        formData.append('draftId', draftId);

        const response = await fetch('../../api/events/publish.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Erreur lors de la publication de l\'événement');
        }

        // Redirection après publication
        try {
            if (sessionStorage.getItem('adminEdit') === '1') {
                sessionStorage.removeItem('adminEdit');
                window.location.href = '/pages/admin';
            } else if (data.isUpdate && data.eventId) {
                window.location.href = '/event/' + encodeURIComponent(data.eventId);
            } else {
                window.location.href = '/pages/user/my-events.php?success=create';
            }
        } catch (_) {
            window.location.href = '/pages/user/my-events.php?success=create';
        }

    } catch (error) {
        console.error('Erreur lors de la soumission:', error);
        showGlobalErrors([error.message]);
        showToast(error.message || "Erreur lors de la publication", "error");
    }
}

// Fonction pour valider les fichiers GPX
function validateGpxFiles() {
    // Vérifier chaque fichier GPX
    const gpxFiles = document.querySelectorAll('input[type="file"][accept=".gpx"]');
    let totalSize = 0;
    
    for (const file of gpxFiles) {
        if (file.files.length > 0) {
            totalSize += file.files[0].size;
        }
    }

    // Vérifier la taille totale (30 MB max)
    if (totalSize > 30 * 1024 * 1024) {
        showToast('La taille totale des fichiers GPX dépasse la limite autorisée', 'error');
        return false;
    }

    return true;
}

// Export des fonctions pour les cartes
(function() {
    window.initializeLocationMap = initLocationMap;
    window.initializeGpxMap = initGpxMap;
    window.initializeMaps = initializeMaps;
    window.updateGpxLegend = updateGpxLegend;
    window.handleGpxUpload = handleGpxUpload;
})();

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

// Fonction pour initialiser la carte de localisation
async function initLocationMap() {
    try {
        const mapElement = document.getElementById('locationMap');
        if (!mapElement) {
            console.error('Élément locationMap non trouvé');
        return;
    }

        // Créer la carte
        window.locationMap = L.map('locationMap').setView([50.4, 4.4], 8);
        
        // Ajouter le fond de carte OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(window.locationMap);
        
        // Pin vert partagé (idem map.js / event-maps.js) — gardé contre la redéclaration
        if (!window.eventPinIcon) {
            window.eventPinIcon = L.divIcon({
                className: 'event-pin',
                html: '<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>',
                iconSize: [30, 42],
                iconAnchor: [15, 42],
                popupAnchor: [0, -38]
            });
        }

        // Ajouter le marqueur
        window.locationMarker = L.marker([50.4, 4.4], {
            draggable: true,
            icon: window.eventPinIcon
        }).addTo(window.locationMap);
        
        // Gérer le déplacement du marqueur
        window.locationMarker.on('dragend', async function(e) {
            const latlng = e.target.getLatLng();
    
            // Mettre à jour les champs cachés
            document.getElementById('latitude').value = latlng.lat;
            document.getElementById('longitude').value = latlng.lng;
            
            // Obtenir et mettre à jour l'adresse via reverseGeocode de event-maps.js
            const address = await window.reverseGeocode(latlng.lat, latlng.lng);
            if (address) {
                const addressInput = document.getElementById('address');
                if (addressInput) {
                    addressInput.value = address;
                    addressInput.classList.add('is-valid');
                    addressInput.classList.remove('is-invalid');
                    window.addressModified = true;
                }
            }
        });

        // Gérer le clic sur la carte
        window.locationMap.on('click', async function(e) {
            const latlng = e.latlng;
            
            // Déplacer le marqueur
            window.locationMarker.setLatLng(latlng);
            
            // Mettre à jour les champs cachés
            document.getElementById('latitude').value = latlng.lat;
            document.getElementById('longitude').value = latlng.lng;
    
            // Obtenir et mettre à jour l'adresse
            const address = await window.reverseGeocode(latlng.lat, latlng.lng);
            if (address) {
                updateAddress(address);
            }
        });

        // Configurer la recherche d'adresse
        setupAddressSearch();

    } catch (error) {
        console.error('Erreur lors de l\'initialisation de la carte:', error);
        showToast('Erreur lors de l\'initialisation de la carte', 'error');
    }
}

// Fonction pour initialiser la carte GPX
function initGpxMap() {
    
    const mapContainer = document.getElementById('gpxMap');
    if (!mapContainer) {
        console.error('Container de la carte GPX non trouvé');
        return;
    }

    if (typeof L === 'undefined') {
        console.error('Leaflet non chargé');
        return;
    }

    if (window.gpxMap && typeof window.gpxMap.invalidateSize === 'function') {
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
        window.currentGpxLayers = window.currentGpxLayers || {};

    } catch (error) {
        console.error('Erreur lors de l\'initialisation de la carte GPX:', error);
        showToast('Erreur lors de l\'initialisation de la carte GPX', 'error');
    }
}

// Gestion des tags d'activité
function initActivityTags() {
    const tagsContainer = document.getElementById('activityTags');
    const hiddenInput = document.getElementById('categories');
    if (!tagsContainer || !hiddenInput) return;

    const getSelectedIds = () => {
        const value = hiddenInput.value.trim();
        if (!value) return [];
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed.map(String) : [];
        } catch (e) {
            return [];
        }
    };

    const setSelectedIds = (ids) => {
        hiddenInput.value = ids.length > 0 ? JSON.stringify(ids) : '';
        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
    };

    tagsContainer.addEventListener('click', function(e) {
        const tag = e.target.closest('.activity-tag');
        if (!tag) return;

        const value = tag.dataset.value;
        let selectedIds = getSelectedIds();
        const index = selectedIds.indexOf(value);

        if (index >= 0) {
            selectedIds.splice(index, 1);
            tag.classList.remove('selected');
            tag.setAttribute('aria-pressed', 'false');
        } else {
            selectedIds.push(value);
            tag.classList.add('selected');
            tag.setAttribute('aria-pressed', 'true');
        }

        setSelectedIds(selectedIds);
    });
}

function initializeNewOrganizerToggle() {
    const organizerSelect = document.getElementById('organizerId');
    const organizerNameInput = document.getElementById('organizerName');
    if (!organizerSelect || !organizerNameInput) return;

    function updateOrganizerNameField() {
        if (organizerSelect.value === 'new') {
            organizerNameInput.classList.remove('d-none');
            organizerNameInput.focus();
        } else {
            organizerNameInput.classList.add('d-none');
            organizerNameInput.value = '';
        }
    }

    organizerSelect.addEventListener('change', updateOrganizerNameField);
    updateOrganizerNameField();
}
