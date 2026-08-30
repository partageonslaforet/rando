// Variables globales pour la carte
if (typeof window.mainMap === 'undefined') {
    window.mainMap = null;
}
if (typeof window.markersLayer === 'undefined') {
    window.markersLayer = null;
}
if (typeof window.geocodeCache === 'undefined') {
    window.geocodeCache = new Map();
}

// Initialisation de la carte
console.log(' map.js chargé.');

document.addEventListener('DOMContentLoaded', function() {
    // Vérifier si le conteneur de carte existe
    const mapContainer = document.getElementById('map');
    if (!mapContainer) {
        return;
    }

    initMap();
});

function initMap() {
    if (window.mainMap) {
        return;
    }
    try {
        // Création de la carte centrée sur la Belgique
        window.mainMap = L.map('map').setView([50.5039, 4.4699], 8);

        // Ajout de la couche OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(window.mainMap);

        // Initialiser la couche des marqueurs
        window.markersLayer = L.layerGroup().addTo(window.mainMap);
    } catch (error) {
        console.error('Erreur lors de l\'initialisation de la carte:', error);
    }
}

// Cache pour les résultats de géocodage
if (typeof window.geocodeCache === 'undefined') {
    window.geocodeCache = new Map();
}

// Fonction pour géocoder une adresse avec cache
async function geocodeAddress(address) {
    console.log(' [geocodeAddress] Début du géocodage pour:', address);
    try {
        // Vérifier le cache
        if (window.geocodeCache.has(address)) {
            console.log(' [geocodeAddress] Résultat trouvé dans le cache');
            return window.geocodeCache.get(address);
        }

        const response = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&limit=5&format=json&addressdetails=1`);
        const results = await response.json();

        if (results && results.length > 0) {
            const coords = {
                lat: parseFloat(results[0].lat),
                lng: parseFloat(results[0].lon)
            };
            window.geocodeCache.set(address, coords);
            return coords;
        } else {
            throw new Error('Aucune coordonnée trouvée pour cette adresse');
        }
    } catch (error) {
        console.error(' [geocodeAddress] Erreur:', error);
        throw error;
    }
}

// Fonction pour le géocodage inverse
async function reverseGeocode(lat, lng) {
    console.log(' [reverseGeocode] Début du géocodage inverse pour:', lat, lng);
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&zoom=18&format=json`);
        const result = await response.json();

        if (result && result.display_name) {
            const parts = [];
            if (result.address) {
                const addr = result.address;
                if (addr.house_number) parts.push(addr.house_number);
                if (addr.road) parts.push(addr.road);
                if (addr.suburb) parts.push(addr.suburb);
                if (addr.city || addr.town || addr.village) parts.push(addr.city || addr.town || addr.village);
                if (addr.postcode) parts.push(addr.postcode);
                if (addr.country) parts.push(addr.country);
            }
            
            return parts.length > 0 ? parts.join(', ') : result.display_name;
        } else {
            throw new Error('Aucune adresse trouvée pour ces coordonnées');
        }
    } catch (error) {
        console.error(' [reverseGeocode] Erreur:', error);
        throw error;
    }
}

// Fonction pour extraire les coordonnées
function getCoordinates(event) {
    if (event.coordinates) {
        try {
            const [lat, lng] = event.coordinates.split(',');
            return {
                lat: parseFloat(lat),
                lng: parseFloat(lng)
            };
        } catch (e) {
            console.warn('Erreur lors du parsing des coordonnées:', e);
        }
    }
    return null;
}

// Fonction pour mettre à jour les marqueurs sur la carte
async function updateMapMarkers(events) {
    try {
        if (!window.markersLayer) {
            window.markersLayer = L.layerGroup().addTo(window.mainMap);
        }
        
        window.markersLayer.clearLayers();
        
        if (!events || events.length === 0) {
            return;
        }

        const bounds = L.latLngBounds();

        for (const event of events) {
            try {
                let coordinates = getCoordinates(event);

                if (coordinates && coordinates.lat && coordinates.lng) {
                    const popupContent = `
                        <div class="event-popup">
                            <div class="event-popup-image">
                                <img src="${event.main_image_path || '/assets/images/events/default-event.jpg'}" 
                                     alt="${event.title}"
                                     style="width: 100%; height: 120px; object-fit: cover;">
                                <span class="badge-category position-absolute top-0 end-0 m-2">
                                    ${getCategoryLabel(event.category)}
                                </span>
                            </div>
                            <div class="event-popup-content p-3">
                                <h5 class="mb-2">${event.title}</h5>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-calendar-event me-2"></i>
                                    <span>${new Date(event.date).toLocaleDateString()}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-geo-alt me-2"></i>
                                    <span>${event.location}</span>
                                </div>
                            </div>
                        </div>`;

                    const marker = L.marker([coordinates.lat, coordinates.lng], {
                        title: "Cliquez pour plus d'informations"
                    }).bindPopup(popupContent);
                    
                    marker.addTo(window.markersLayer);
                    bounds.extend([coordinates.lat, coordinates.lng]);
                }
            } catch (error) {
                console.error(`Erreur lors du traitement de l'événement:`, error);
            }
        }

        if (bounds.isValid()) {
            window.mainMap.fitBounds(bounds, { padding: [50, 50] });
        }
    } catch (error) {
        console.error('Erreur générale dans updateMapMarkers:', error);
    }
}

// Fonction pour obtenir la classe de badge pour une catégorie
function getCategoryBadgeClass(category) {
    switch (category) {
        case 'running':
            return 'bg-primary';
        case 'hiking':
            return 'bg-success';
        case 'cycling':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Fonction pour obtenir le libellé pour une catégorie
function getCategoryLabel(category) {
    switch (category) {
        case 'running':
            return 'Course à pied';
        case 'hiking':
            return 'Randonnée';
        case 'cycling':
            return 'Vélo';
        default:
            return 'Autre';
    }
}

// Exporter les fonctions
window.mapFunctions = {
    updateMapMarkers,
    getCategoryBadgeClass,
    getCategoryLabel,
    reverseGeocode,
    geocodeAddress,
    getCoordinates,
    getFilteredEvents: function() {
        const markers = window.markersLayer ? Array.from(window.markersLayer.getLayers()) : [];
        return markers.map(marker => {
            const popup = marker.getPopup();
            const content = popup?.getContent() || '';
            const match = content.match(/event\.php\?id=(\d+)/);
            return match ? { id: match[1] } : null;
        }).filter(Boolean);
    }
};

console.log(' map.js chargé.');