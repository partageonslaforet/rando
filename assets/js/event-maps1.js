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
        console.log('📍 Géocodage inverse pour:', lat, lng);
        
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
        console.log('📍 Résultat:', data);
        
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
        console.log('✅ Adresse mise à jour:', address);
    } else {
        console.error('❌ Champ adresse non trouvé');
    }
}

// Initialisation de la carte des événements
async function initializeMap() {
    console.log('🗺️ Initialisation de la carte événement...');
    
    // Vérifier si la carte existe déjà
    if (eventMap) {
        console.log('Carte déjà initialisée, réinitialisation...');
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

        eventMap = L.map('eventMap').setView([50.8503, 4.3517], 13);
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
            console.log('✅ Carte événement rafraîchie');
        }, 100);
        
        console.log('✅ Carte événement initialisée');
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
        console.log('📍 Marqueur déplacé vers:', latlng);
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
    console.log('🗺️ Initialisation de la carte GPX...');
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

        globalGpxMap = L.map('gpxMap').setView([50.8503, 4.3517], 8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(globalGpxMap);

        // Forcer le rafraîchissement de la carte
        setTimeout(() => {
            globalGpxMap.invalidateSize();
            console.log('✅ Carte GPX rafraîchie');
        }, 100);

        console.log('✅ Carte GPX initialisée');
        return true;
    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation de la carte GPX:', error);
        return false;
    }
}

// Fonction pour gérer le chargement des fichiers GPX
function handleGpxFileSelect(input, routeIndex) {
    const file = input.files[0];
    const alertInfo = document.getElementById('gpxAlert');
    const legend = document.getElementById('gpxLegend');
    const legendItems = document.getElementById('gpxLegendItems');
    const gpxMapContainer = document.getElementById('gpxMap');

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

            // Générer une couleur aléatoire pour le tracé
            const colors = ['#FF4136', '#2ECC40', '#0074D9', '#B10DC9', '#FF851B', '#7FDBFF', '#F012BE'];
            const color = colors[routeIndex % colors.length];

            // Supprimer l'ancien tracé s'il existe
            if (globalGpxLayers[routeIndex]) {
                globalGpxMap.removeLayer(globalGpxLayers[routeIndex]);
            }

            try {
                // Créer une nouvelle couche GPX avec la couleur assignée
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
                        alertInfo.style.display = 'none';
                        legend.style.display = 'block';

                        // Obtenir le nom du parcours
                        const routeNameInput = document.querySelector(`input[name="routes[${routeIndex}][name]"]`);
                        const routeName = routeNameInput.value || `Parcours ${routeIndex + 1}`;

                        // Mettre à jour la légende
                        updateGpxLegend(routeIndex, color, routeName);

                        // Mettre à jour le dénivelé si le champ est vide
                        const elevationInput = document.querySelector(`input[name="routes[${routeIndex}][elevationGain]"]`);
                        if (elevationInput && !elevationInput.value) {
                            const elevation = Math.round(gpxLayer.get_elevation_gain());
                            elevationInput.value = elevation;
                        }

                        // Mettre à jour la distance si le champ est vide
                        const distanceInput = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
                        if (distanceInput && !distanceInput.value) {
                            const distance = Math.round(gpxLayer.get_distance() / 100) / 10;
                            distanceInput.value = distance;
                        }

                        // Ajuster la vue de la carte
                        const bounds = gpxLayer.getBounds();
                        globalGpxMap.fitBounds(bounds, {
                            padding: [30, 30],
                            maxZoom: 15
                        });

                        // Forcer un rafraîchissement de la carte
                        globalGpxMap.invalidateSize();

                        // Écouter les changements du nom du parcours pour mettre à jour la légende
                        routeNameInput.addEventListener('input', function() {
                            updateGpxLegend(routeIndex, color, this.value || `Parcours ${routeIndex + 1}`);
                        });
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
            const legendItem = document.getElementById(`gpxLegendItem${routeIndex}`);
            if (legendItem) {
                legendItem.remove();
            }

            // Masquer la carte et afficher l'alerte si plus aucun tracé
            if (globalGpxLayers.every(layer => !layer)) {
                gpxMapContainer.style.display = 'none';
                alertInfo.style.display = 'block';
                legend.style.display = 'none';
            }
        }
    }
}

function updateGpxLegend(routeIndex, color, name, distance, elevation) {
    console.log('🔄 Mise à jour de la légende GPX:', { routeIndex, color, name, distance, elevation });
    
    const legendContainer = document.getElementById('gpx-legend');
    if (!legendContainer) {
        console.error('❌ Conteneur de légende non trouvé');
        return;
    }

    // Créer ou mettre à jour l'entrée de légende pour ce parcours
    let legendEntry = document.getElementById(`route-legend-${routeIndex}`);
    if (!legendEntry) {
        legendEntry = document.createElement('div');
        legendEntry.id = `route-legend-${routeIndex}`;
        legendEntry.classList.add('route-legend-entry', 'mb-2');
        legendContainer.appendChild(legendEntry);
    }

    // Créer le contenu HTML avec les informations du parcours
    const distanceText = distance ? `${distance} km` : '';
    const elevationText = elevation ? `${elevation} D+` : '';
    const infoText = [distanceText, elevationText].filter(Boolean).join(' | ');
    
    legendEntry.innerHTML = `
        <div class="d-flex align-items-center">
            <span class="route-color-indicator me-2" style="background-color: ${color};"></span>
            <span class="route-name">${name || `Parcours ${routeIndex + 1}`}</span>
        </div>
        ${infoText ? `<div class="route-info small text-muted ms-4">${infoText}</div>` : ''}
    `;
}

// Fonction pour mettre à jour la légende lors du chargement du GPX
function handleGpxFileSelect(input, routeIndex) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const gpxContent = e.target.result;
            const parser = new DOMParser();
            const gpxDoc = parser.parseFromString(gpxContent, "text/xml");
            
            // Extraire les informations du parcours
            const tracks = gpxDoc.getElementsByTagName('trk');
            if (tracks.length > 0) {
                const track = tracks[0];
                const name = track.getElementsByTagName('name')[0]?.textContent || `Parcours ${routeIndex + 1}`;
                
                // Calculer la distance totale
                let distance = 0;
                let elevation = 0;
                const points = gpxDoc.getElementsByTagName('trkpt');
                let prevPoint = null;
                
                for (let i = 0; i < points.length; i++) {
                    const point = points[i];
                    const lat = parseFloat(point.getAttribute('lat'));
                    const lon = parseFloat(point.getAttribute('lon'));
                    const ele = parseFloat(point.getElementsByTagName('ele')[0]?.textContent || 0);
                    
                    if (prevPoint) {
                        // Calculer la distance entre les points
                        const d = calculateDistance(
                            prevPoint.lat,
                            prevPoint.lon,
                            lat,
                            lon
                        );
                        distance += d;
                        
                        // Calculer le dénivelé positif
                        if (ele > prevPoint.ele) {
                            elevation += ele - prevPoint.ele;
                        }
                    }
                    
                    prevPoint = { lat, lon, ele };
                }
                
                // Convertir la distance en kilomètres et arrondir
                distance = Math.round(distance / 1000 * 10) / 10;
                elevation = Math.round(elevation);

                // Mettre à jour les champs du formulaire
                const distanceInput = document.querySelector(`input[name="routes[${routeIndex}][distance]"]`);
                const elevationInput = document.querySelector(`input[name="routes[${routeIndex}][elevation]"]`);
                if (distanceInput) distanceInput.value = distance;
                if (elevationInput) elevationInput.value = elevation;

                // Générer une couleur pour le parcours
                const colors = ['#2ecc71', '#e74c3c', '#3498db', '#f1c40f', '#9b59b6'];
                const color = colors[routeIndex % colors.length];

                // Mettre à jour la légende avec toutes les informations
                updateGpxLegend(routeIndex, color, name, distance, elevation);
                
                // Ajouter le tracé à la carte
                const geoJson = toGeoJSON.gpx(gpxDoc);
                const layer = L.geoJSON(geoJson, {
                    style: {
                        color: color,
                        weight: 3,
                        opacity: 0.8
                    }
                }).addTo(globalGpxMap);

                // Stocker la couche pour pouvoir la supprimer plus tard
                globalGpxLayers[routeIndex] = layer;

                // Ajuster la vue de la carte
                globalGpxMap.fitBounds(layer.getBounds());
            }
        } catch (error) {
            console.error('❌ Erreur lors du traitement du fichier GPX:', error);
        }
    };
    reader.readAsText(file);
}

// Fonction utilitaire pour calculer la distance entre deux points
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000; // Rayon de la Terre en mètres
    const φ1 = lat1 * Math.PI/180;
    const φ2 = lat2 * Math.PI/180;
    const Δφ = (lat2-lat1) * Math.PI/180;
    const Δλ = (lon2-lon1) * Math.PI/180;

    const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
            Math.cos(φ1) * Math.cos(φ2) *
            Math.sin(Δλ/2) * Math.sin(Δλ/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    return R * c; // Distance en mètres
}

// Export des fonctions
window.initializeMap = initializeMap;
window.initGlobalGpxMap = initGlobalGpxMap;
window.handleGpxFileSelect = handleGpxFileSelect;
window.updateGpxLegend = updateGpxLegend;

// Initialisation de la carte au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded - Démarrage de l\'initialisation de la carte');
    const mapElement = document.getElementById('eventMap');
    
    if (mapElement) {
        // Attendre un peu pour s'assurer que le DOM est complètement chargé
        setTimeout(initializeMap, 100);
    } else {
        console.error('❌ Élément de carte non trouvé dans le DOM');
    }
});
