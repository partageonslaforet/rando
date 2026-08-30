// Variables globales pour les cartes
let eventMap = null;
let eventMarker = null;
let globalGpxMap = null;
let globalGpxLayers = [];
let geocoder = null;
window.addressModified = false;

// Fonction pour faire la géocodification inverse
async function reverseGeocode(lat, lng) {
    try {
        
        // Ajouter un délai pour respecter les limites de l'API
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?` + 
            `format=json&` +
            `lat=${lat}&` +
            `lon=${lng}&` +
            `zoom=18&` +
            `addressdetails=1&` +
            `accept-language=fr`
        );
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }

        return data.display_name;
    } catch (error) {
        console.error('❌ Erreur lors de la géocodification inverse:', error);
        return null;
    }
}

// Fonction pour mettre à jour l'adresse
function updateAddress(address) {
    const addressInput = document.getElementById('eventAddressInput');
    if (addressInput) {
        addressInput.value = address;
        addressInput.classList.add('is-valid');
        addressInput.classList.remove('is-invalid');
        window.addressModified = true;
    } else {
        console.error('❌ Champ adresse non trouvé');
    }
}

// Initialisation de la carte des événements
async function initializeMap() {
    
    // Vérifier si la carte existe déjà
    if (eventMap) {
        eventMap.remove();
    }
    
    try {
        const eventMapContainer = document.getElementById('eventMap');
        if (!eventMapContainer) {
            console.error('❌ Conteneur eventMap non trouvé');
            return false;
        }

        // Attendre que Leaflet soit chargé
        if (typeof L === 'undefined') {
            console.error('❌ Leaflet n\'est pas chargé');
            return false;
        }

        // Attendre que le contrôle de géocodage soit chargé
        if (typeof L.Control.Geocoder === 'undefined') {
            console.error('❌ Le contrôle de géocodage n\'est pas chargé');
            return false;
        }

        eventMap = L.map('eventMap').setView([49.95, 5.437], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(eventMap);
        
        // Ajouter le contrôle de géocodage
        geocoder = L.Control.Geocoder.nominatim({
            geocoder: geocoder,
            geocodingQueryParams: {
                'accept-language': 'fr'
            }
        });
        
        const control = L.Control.geocoder({
            geocoder: geocoder,
            defaultMarkGeocode: false,
            placeholder: 'Rechercher une adresse...',
            errorMessage: 'Adresse non trouvée.',
            showResultIcons: true,
            suggestMinLength: 3,
            suggestTimeout: 250,
            queryMinLength: 3
        }).addTo(eventMap);

        // Gérer la sélection d'une adresse
        control.on('markgeocode', function(e) {
            const latlng = e.geocode.center;
            if (eventMarker) {
                eventMarker.setLatLng(latlng);
            } else {
                eventMarker = L.marker(latlng, { draggable: true }).addTo(eventMap);
                // Ajouter l'événement dragend au nouveau marqueur
                eventMarker.on('dragend', handleMarkerDragEnd);
            }
            eventMap.setView(latlng, 16);

            // Mettre à jour le champ d'adresse
            updateAddress(e.geocode.name);
        });

        // Ajouter le marqueur initial
        eventMarker = L.marker([50.8503, 4.3517], { draggable: true }).addTo(eventMap);
        
        // Gérer le déplacement du marqueur
        eventMarker.on('dragend', handleMarkerDragEnd);
        
        // Forcer le rafraîchissement de la carte
        setTimeout(() => {
            eventMap.invalidateSize();
        }, 100);
        
        return true;
    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation de la carte:', error);
        return false;
    }
}

// Fonction pour gérer le déplacement du marqueur
async function handleMarkerDragEnd(e) {
    try {
        const latlng = e.target.getLatLng();
        const address = await reverseGeocode(latlng.lat, latlng.lng);
        if (address) {
            updateAddress(address);
        }
    } catch (error) {
        console.error('❌ Erreur lors de la mise à jour de l\'adresse:', error);
    }
}

// Fonction de validation de l'adresse
function validateAddress() {
    const addressInput = document.getElementById('eventAddressInput');
    if (!addressInput) return false;
    
    const defaultAddress = "Sélectionnez une adresse sur la carte";
    const currentAddress = addressInput.value;
    
    if (!window.addressModified || currentAddress === defaultAddress || !currentAddress.trim()) {
        addressInput.classList.add('is-invalid');
        addressInput.classList.remove('is-valid');
        return false;
    }
    
    return true;
}

// Ajouter la validation de l'adresse au formulaire
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('createEventForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateAddress()) {
                e.preventDefault();
                const addressInput = document.getElementById('eventAddressInput');
                addressInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Afficher un message d'erreur
                const errorContainer = document.getElementById('globalErrorContainer');
                const errorList = document.getElementById('globalErrorList');
                if (errorContainer && errorList) {
                    errorContainer.style.display = 'block';
                    errorList.innerHTML = '<li>Veuillez sélectionner une adresse valide sur la carte</li>';
                }
            }
        });
    }
});

// Initialisation de la carte GPX
async function initGlobalGpxMap() {
    try {
        const gpxMapContainer = document.getElementById('gpxMap');
        if (!gpxMapContainer) {
            console.error('❌ Conteneur gpxMap non trouvé');
            return false;
        }

        // Attendre que Leaflet soit chargé
        if (typeof L === 'undefined') {
            console.error('❌ Leaflet n\'est pas chargé');
            return false;
        }

        globalGpxMap = L.map('gpxMap').setView([49.95, 5.437], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(globalGpxMap);

        // Forcer le rafraîchissement de la carte
        setTimeout(() => {
            globalGpxMap.invalidateSize();
        }, 100);

        return true;
    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation de la carte GPX:', error);
        return false;
    }
}

// Fonction pour gérer le chargement des fichiers GPX
function handleGpxMapDisplay(input, routeIndex) {
    const file = input.files[0];
    const alertInfo = document.getElementById('gpxAlert');
    const legend = document.getElementById('gpxLegend');
    const legendContent = document.getElementById('gpx-legend-content');
    const gpxMapContainer = document.getElementById('gpxMap');

        file: !!file,
        alertInfo: !!alertInfo,
        legend: !!legend,
        legendContent: !!legendContent,
        gpxMapContainer: !!gpxMapContainer
    });

    if (!gpxMapContainer) {
        console.error('❌ Conteneur de carte GPX non trouvé');
        return;
    }

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const gpxContent = e.target.result;
            
            // S'assurer que la carte est visible
            gpxMapContainer.style.display = 'block';
            if (globalGpxMap) {
                globalGpxMap.invalidateSize();
            }

            // Générer une couleur pour le tracé
            const colors = ['#2ecc71', '#e74c3c', '#3498db', '#f1c40f', '#9b59b6'];
            const color = colors[routeIndex % colors.length];

            // Supprimer l'ancien tracé s'il existe
            if (globalGpxLayers[routeIndex]) {
                globalGpxMap.removeLayer(globalGpxLayers[routeIndex]);
            }

            try {
                // Créer une nouvelle couche GPX
                const gpxLayer = new L.GPX(gpxContent, {
                    async: true,
                    polyline_options: {
                        color: color,
                        weight: 3,
                        opacity: 0.8
                    },
                    marker_options: {
                        startIconUrl: '/assets/img/pin-icon-start.png',
                        endIconUrl: '/assets/img/pin-icon-end.png',
                        shadowUrl: '/assets/img/pin-shadow.png',
                        wptIconUrls: {
                            '': '/assets/img/pin-icon-wpt.png'
                        }
                    }
                });


                gpxLayer.on('loaded', function(e) {
                    try {
                        
                        // Stocker la couche
                        globalGpxLayers[routeIndex] = gpxLayer;

                        // Afficher la carte et la légende
                        gpxMapContainer.style.display = 'block';
                        if (alertInfo) alertInfo.style.display = 'none';
                        if (legend) {
                            legend.style.display = 'block';
                        }

                        // Obtenir le nom du parcours
                        const routeNameInput = document.querySelector(`input[name="routes[${routeIndex}][name]"]`);
                        const routeName = routeNameInput?.value || `Parcours ${routeIndex + 1}`;

                        // Mettre à jour la légende
                        if (legendContent) {
                            let legendItem = document.getElementById(`gpx-legend-item-${routeIndex}`);
                            if (!legendItem) {
                                legendItem = document.createElement('div');
                                legendItem.id = `gpx-legend-item-${routeIndex}`;
                                legendItem.className = 'route-legend-entry';
                                legendContent.appendChild(legendItem);
                            }

                            // Mettre à jour les champs du formulaire
                            const distance = Math.round(gpxLayer.get_distance() / 100) / 10;
                            const elevation = Math.round(gpxLayer.get_elevation_gain());

                            const distanceInput = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
                            const elevationInput = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);
                            if (distanceInput) distanceInput.value = distance;
                            if (elevationInput) elevationInput.value = elevation;

                            // Mettre à jour le contenu de la légende
                            legendItem.innerHTML = `
                                <div class="d-flex align-items-center">
                                    <div class="route-color-indicator me-2" style="background-color: ${color};"></div>
                                    <span class="route-name">${routeName}</span>
                                </div>
                                <div class="route-info small text-muted ms-4">
                                    ${distance} km | ${elevation} D+
                                </div>
                            `;

                            // Écouter les changements du nom du parcours
                            if (routeNameInput) {
                                routeNameInput.addEventListener('input', function() {
                                    const nameSpan = legendItem.querySelector('.route-name');
                                    if (nameSpan) {
                                        nameSpan.textContent = this.value || `Parcours ${routeIndex + 1}`;
                                    }
                                });
                            }
                        } else {
                            console.error('❌ Conteneur de légende non trouvé (gpx-legend-content)');
                        }

                        // Ajuster la vue de la carte
                        const bounds = gpxLayer.getBounds();
                        globalGpxMap.fitBounds(bounds, {
                            padding: [30, 30],
                            maxZoom: 15
                        });

                        // Forcer un rafraîchissement de la carte
                        globalGpxMap.invalidateSize();
                    } catch (error) {
                        console.error('❌ Erreur lors du traitement du GPX:', error);
                    }
                });

                gpxLayer.addTo(globalGpxMap);
            } catch (error) {
                console.error('❌ Erreur lors de la création de la couche GPX:', error);
            }
        };
        
        reader.readAsText(file);
    } else {
        // Supprimer le tracé si le fichier est retiré
        if (globalGpxLayers[routeIndex]) {
            globalGpxMap.removeLayer(globalGpxLayers[routeIndex]);
            globalGpxLayers[routeIndex] = null;
            
            // Supprimer l'élément de légende
            const legendItem = document.getElementById(`gpx-legend-item-${routeIndex}`);
            if (legendItem) {
                legendItem.remove();
            }

            // Masquer la carte et afficher l'alerte si plus aucun tracé
            if (globalGpxLayers.every(layer => !layer)) {
                gpxMapContainer.style.display = 'none';
                if (alertInfo) alertInfo.style.display = 'block';
                if (legend) legend.style.display = 'none';
            }
        }
    }
}

// Export des fonctions
window.initializeMap = initializeMap;
window.initGlobalGpxMap = initGlobalGpxMap;
window.handleGpxMapDisplay = handleGpxMapDisplay;

// Initialisation de la carte au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('eventMap');
    
    if (mapElement) {
        // Attendre un peu pour s'assurer que le DOM est complètement chargé
        setTimeout(initializeMap, 100);
    } else {
        console.error('❌ Élément de carte non trouvé dans le DOM');
    }
});
