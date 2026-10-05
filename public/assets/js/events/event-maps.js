/**
 * Fichier: /assets/js/events/event-maps.js
 * Rôle: Initialisation et interactions des cartes (Leaflet) pour la création/édition d’événements.
 * Utilisation: pages/modales de création/édition; expose utilitaires de carte et geocoding.
 * Dépendances: Leaflet, OpenStreetMap tiles, DOM (#map, inputs coordonnées), éventuellement geocoder.
 */
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

    const mapContainer = document.getElementById('map');
    if (!mapContainer || mapContainer.clientWidth === 0 || mapContainer.clientHeight === 0) {
        return;
    }

    try {
        // Création de la carte centrée sur la Belgique
        window.mainMap = L.map(mapContainer).setView([50.5039, 4.4699], 8);

        // Ajout de la couche OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(window.mainMap);

        // Initialiser la couche des marqueurs (cluster si le plugin est chargé)
        window.markersLayer = window.createMarkersLayer().addTo(window.mainMap);
    } catch (error) {
        console.error('Erreur lors de l\'initialisation de la carte:', error);
    }
}

// Cache pour les résultats de géocodage
if (typeof window.geocodeCache === 'undefined') {
    window.geocodeCache = new Map();
}

// Icône/couche partagées avec map.js — gardes anti-redéclaration (les deux scripts peuvent cohabiter)
if (!window.eventPinIcon) {
    window.eventPinIcon = L.divIcon({
        className: 'event-pin',
        html: '<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>',
        iconSize: [30, 42],
        iconAnchor: [15, 42],
        popupAnchor: [0, -38]
    });
}
if (typeof window.eventClusterIcon !== 'function') {
    window.eventClusterIcon = function (cluster) {
        return L.divIcon({
            className: 'event-cluster',
            html: '<div><span>' + cluster.getChildCount() + '</span></div>',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });
    };
}
if (typeof window.createMarkersLayer !== 'function') {
    window.createMarkersLayer = function () {
        if (typeof L.markerClusterGroup === 'function') {
            return L.markerClusterGroup({
                showCoverageOnHover: false,
                spiderfyOnMaxZoom: true,
                iconCreateFunction: window.eventClusterIcon
            });
        }
        return L.layerGroup();
    };
}

// Nettoie une adresse pour Nominatim : retire le contenu entre parenthèses et normalise les espaces
function cleanAddressForGeocode(address) {
    return String(address || '')
        .replace(/\([^)]*\)/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

// Variantes de requête : adresse nettoyée, puis sans les premiers tokens
// (nom d'entreprise éventuel absent d'OSM), en gardant au moins 3 tokens
function geocodeQueryVariants(address) {
    const cleaned = cleanAddressForGeocode(address);
    const variants = [];
    if (cleaned) variants.push(cleaned);
    const tokens = cleaned.split(' ');
    while (tokens.length > 3) {
        tokens.shift();
        variants.push(tokens.join(' '));
    }
    return variants;
}

// Fonction pour géocoder une adresse avec cache
async function geocodeAddress(address) {
    console.log('[geocodeAddress] Appelé pour:', address);
    try {
        // Vérifier le cache
        if (window.geocodeCache.has(address)) {
            console.log('[geocodeAddress] Résultat trouvé dans le cache.');
            return window.geocodeCache.get(address);
        }

        const variants = geocodeQueryVariants(address);
        let results = null;
        for (let i = 0; i < variants.length; i++) {
            if (i > 0) {
                // Respecter la limite Nominatim (1 req/s)
                await new Promise(function (resolve) { setTimeout(resolve, 1100); });
            }
            const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(variants[i])}&limit=5&format=json&addressdetails=1`;
            console.log('[geocodeAddress] Requête Nominatim:', url);
            const response = await fetch(url);
            console.log('[geocodeAddress] Réponse HTTP:', response.status, response.statusText);
            results = await response.json();
            console.log('[geocodeAddress] Résultats Nominatim:', results);
            if (results && results.length > 0) break;
        }

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
        // S'assurer que la carte est initialisée avant d'ajouter des marqueurs
        if (!window.mainMap) {
            initMap();
        }
        if (!window.mainMap) {
            console.warn('Carte non initialisée, mise à jour des marqueurs annulée');
            return;
        }

        if (!window.markersLayer) {
            window.markersLayer = window.createMarkersLayer().addTo(window.mainMap);
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
                    const isCancelled = /^(1|t|true|yes|on)$/i.test(String(event.is_cancelled));
                    const popupContent = `
                        <div class="event-popup">
                            <div class="event-popup-image">
                                ${isCancelled ? '<span class="event-popup-cancelled">Annulé</span>' : ''}
                                <img src="${event.main_image_path || '/assets/images/events/default-event.jpg'}" 
                                     alt="${event.title}">
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
                        icon: window.eventPinIcon,
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
console.log('[event-maps.js] Exposition de window.mapFunctions');
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

