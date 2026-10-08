/**
 * Fichier: /assets/js/home/map.js
 * Rôle: Initialisation Leaflet de la carte d’accueil et rendu des marqueurs/popups d’événements.
 * Utilisation: page d’accueil ou composant carte; consomme `window.allEvents`.
 * Dépendances: Leaflet, EventsAPI (enrichissement optionnel), Bootstrap Icons dans HTML.
 */
// Variables globales pour la carte
if (typeof window.mainMap === 'undefined') {
    window.mainMap = null;
}

// Assurer des données enrichies depuis l'API (categories[] etc.) si nécessaire
async function ensureRichEventsData() {
    try {
        const needsUpgrade = !window.allEvents || !window.allEvents.length || !('categories' in (window.allEvents[0] || {}));
        if (typeof EventsAPI !== 'undefined' && needsUpgrade) {
            const events = await EventsAPI.getAllEvents();
            window.allEvents = events;
            if (window.mapFunctions?.updateMapMarkers) {
                window.mapFunctions.updateMapMarkers(window.allEvents);
            }
        }
    } catch (e) {
        // console.warn('Impossible d’enrichir les événements via API:', e);
    }
}
if (typeof window.markersLayer === 'undefined') {
    window.markersLayer = null;
}
if (typeof window.geocodeCache === 'undefined') {
    window.geocodeCache = new Map();
}

// Formatage de date en français court (ex: "27 sept. 2026") sans décalage de fuseau
function parseYMDToLocalDate(dateStr) {
    if (!dateStr) return new Date(NaN);
    const isoPart = String(dateStr).split(/[T ]/)[0];
    const [y, m, d] = (isoPart || '').split('-').map(Number);
    if (y && m && d) {
        return new Date(y, (m - 1), d);
    }
    // Fallback: laisser le moteur parser d'autres formats
    return new Date(dateStr);
}

function formatDateLongFR(dateStr) {
    const dt = parseYMDToLocalDate(dateStr);
    try {
        return dt.toLocaleDateString('fr-BE', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (e) {
        return dateStr || '';
    }
}

// Initialisation de la carte
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier si le conteneur de carte existe
    const mapContainer = document.getElementById('map');
    if (!mapContainer) {
        return;
    }

    initMap();
    // S'assurer que les données contiennent les catégories détaillées issues de l'API
    ensureRichEventsData();
});

function initMap() {
    if (window.mainMap) {
        return;
    }
    try {
        // Création de la carte centrée sur la Belgique
        // tap:false — évite le click synthétique Leaflet sur iOS Safari
        window.mainMap = L.map('map', { tap: false }).setView([50.5039, 4.4699], 8);
        // console.log('Carte créée:', map); // Log de la carte créée

        // Ajout de la couche OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(window.mainMap);
        // console.log('Couche de tuiles ajoutée'); // Log de l'ajout de la couche de tuiles

        // Initialiser la couche des marqueurs (cluster si le plugin est chargé)
        window.markersLayer = createMarkersLayer().addTo(window.mainMap);
        // console.log('Couche de marqueurs créée:', markersLayer); // Log de la couche de marqueurs créée

        // Initialiser les marqueurs avec les événements actuels
        const currentEvents = getCurrentEvents();
        // console.log('Événements récupérés:', currentEvents); // Log des événements récupérés
        
        if (currentEvents && currentEvents.length > 0) {
            updateMapMarkers(currentEvents);
        } else {
            // console.warn('Aucun événement trouvé lors de l\'initialisation');
        }
    } catch (error) {
        // console.error('Erreur lors de l\'initialisation de la carte:', error);
    }
    // console.log('Fin initMap');
}

// Fonction pour obtenir les événements actuels en fonction des filtres
function getCurrentEvents() {
    if (!window.allEvents || !window.currentFilters) {
        return [];
    }

    return window.allEvents;
}

// Icône de marqueur personnalisée (pin Bootstrap Icons en vert primaire)
const eventPinIcon = L.divIcon({
    className: 'event-pin',
    html: '<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>',
    iconSize: [30, 42],
    iconAnchor: [15, 42],
    popupAnchor: [0, -38]
});

// Icône de cluster (pastille verte avec compteur) pour markercluster
function eventClusterIcon(cluster) {
    return L.divIcon({
        className: 'event-cluster',
        html: '<div><span>' + cluster.getChildCount() + '</span></div>',
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });
}

// Couche de marqueurs : cluster si le plugin est chargé, sinon layerGroup simple
function createMarkersLayer() {
    if (typeof L.markerClusterGroup === 'function') {
        return L.markerClusterGroup({
            showCoverageOnHover: false,
            spiderfyOnMaxZoom: true,
            iconCreateFunction: eventClusterIcon,
            // L'autopan à l'ouverture du popup déclenche un moveend qui retirait
            // le marqueur hors des bounds → popup détruit aussitôt (bug mobile)
            removeOutsideVisibleBounds: false
        });
    }
    return L.layerGroup();
}

// Cache pour les résultats de géocodage
if (typeof window.geocodeCache === 'undefined') {
    window.geocodeCache = new Map();
}

// Fonction pour géocoder une adresse avec cache
async function geocodeAddress(address) {
    try {
        // Vérifier le cache
        if (window.geocodeCache.has(address)) {
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
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&zoom=18&format=json`);
        const result = await response.json();

        if (result && result.display_name) {
            // Formater l'adresse de manière plus lisible
            const parts = [];
            if (result.address) {
                const addr = result.address;
                // Construire l'adresse dans l'ordre logique
                if (addr.house_number) parts.push(addr.house_number);
                if (addr.road) parts.push(addr.road);
                if (addr.suburb) parts.push(addr.suburb);
                if (addr.city || addr.town || addr.village) parts.push(addr.city || addr.town || addr.village);
                if (addr.postcode) parts.push(addr.postcode);
                if (addr.country) parts.push(addr.country);
            }
            
            const formattedAddress = parts.length > 0 ? parts.join(', ') : result.display_name;
            return formattedAddress;
        } else {
            console.error(' [reverseGeocode] Aucun résultat trouvé');
            throw new Error('Aucune adresse trouvée pour ces coordonnées');
        }
    } catch (error) {
        console.error(' [reverseGeocode] Erreur:', error);
        throw error;
    }
}

// Fonction pour extraire les coordonnées
function getCoordinates(event) {
    // Si l'événement a des coordonnées stockées
    if (event.coordinates) {
        try {
            // Format attendu: "lat,lng"
            const [lat, lng] = event.coordinates.split(',');
            return {
                lat: parseFloat(lat),
                lng: parseFloat(lng)
            };
        } catch (e) {
            // console.warn('Erreur lors du parsing des coordonnées:', e);
        }
    }
    return null;
}

// Fonction pour mettre à jour les marqueurs sur la carte
async function updateMapMarkers(events) {
    // console.group('=== MISE À JOUR DES MARQUEURS ===');
    // console.log('Mise à jour des marqueurs avec', events?.length, 'événements');
    
    try {
        // Vérifier que markersLayer existe
        if (!window.markersLayer) {
            // console.error('markersLayer n\'existe pas!');
            window.markersLayer = createMarkersLayer().addTo(window.mainMap);
        }
        
        // Supprimer tous les marqueurs existants
        window.markersLayer.clearLayers();
        
        if (!events || events.length === 0) {
            // console.warn('Aucun événement à afficher');
            // console.groupEnd();
            return;
        }

        const bounds = L.latLngBounds();

        for (const event of events) {
            try {
                let coordinates = getCoordinates(event);
                // console.log('Traitement de l\'événement:', event.title, 'avec coordonnées:', coordinates);

                if (coordinates && coordinates.lat && coordinates.lng) {
                    const isDefaultSrc = (src) => !src || src.toLowerCase().includes('default');
                    const popupImage = !isDefaultSrc(event.main_image_path) ? event.main_image_path : (event.fallback_image || '/assets/images/events/default-event.jpg');
                    const isCancelled = /^(1|t|true|yes|on)$/i.test(String(event.is_cancelled));
                    const cancelledBadge = isCancelled ? '<span class="event-popup-cancelled">Annulé</span>' : '';

                    // Rendu des chips catégories (priorité aux categories[] de l'API, fallback sur category)
                    const renderCategoriesChips = (e) => {
                        if (Array.isArray(e.categories) && e.categories.length > 0) {
                            return `<div class="category-chips mb-2">` +
                                   e.categories.map(c => `<span class="chip" title="${c.name}">${c.name}</span>`).join('') +
                                   `</div>`;
                        }
                        const label = e.category_name || (typeof getCategoryLabel === 'function' ? getCategoryLabel(e.category) : (e.category || 'Autre'));
                        return `<div class="category-chips mb-2"><span class="chip">${label}</span></div>`;
                    };

                    // Créer le contenu du popup (entièrement cliquable)
                    const detailUrl = `/event?id=${encodeURIComponent(event.id)}`;
                    const popupViews = (event.views_total != null ? parseInt(event.views_total, 10) : (event.view_count != null ? parseInt(event.view_count, 10) : 0)) || 0;
                    const popupContent = `
                        <a href="${detailUrl}" class="event-popup-link" aria-label="Voir l'événement">
                            <div class="event-popup">
                                <div class="event-popup-image">
                                    ${cancelledBadge}
                                    <img src="${popupImage}" 
                                         alt="${event.title}">
                                </div>
                                <div class="event-popup-content">
                                    <div class="event-popup-head">
                                        <h5 class="event-popup-title">${event.title}</h5>
                                        <span class="event-popup-views"><i class="bi bi-eye"></i>${popupViews} vue${popupViews > 1 ? 's' : ''}</span>
                                    </div>
                                    ${renderCategoriesChips(event)}
                                    <div class="event-popup-meta">
                                        <div class="event-popup-meta-row">
                                            <i class="bi bi-calendar-event"></i>
                                            <span>${formatDateLongFR(event.date)}</span>
                                        </div>
                                        <div class="event-popup-meta-row">
                                            <i class="bi bi-geo-alt"></i>
                                            <span>${event.location}</span>
                                        </div>
                                    </div>
                                    <div class="event-popup-cta">Voir l'événement <i class="bi bi-arrow-right"></i></div>
                                </div>
                            </div>
                        </a>`;

                    // Créer le marqueur avec tooltip et icône pin verte
                    const marker = L.marker([coordinates.lat, coordinates.lng], {
                        icon: eventPinIcon,
                        title: "Cliquez pour plus d'informations"  // Ajoute le tooltip
                    }).bindPopup(popupContent);
                    
                    // Ajouter le marqueur à la couche
                    marker.addTo(window.markersLayer);
                    bounds.extend([coordinates.lat, coordinates.lng]);
                    // console.log('Marqueur ajouté à la carte pour:', event.title);
                }
            } catch (error) {
                // console.error(`Erreur lors du traitement de l'événement:`, error);
            }
        }

        // Ajuster la vue de la carte
        if (bounds.isValid()) {
            window.mainMap.fitBounds(bounds, { padding: [50, 50] });
            // console.log('Vue de la carte ajustée');
        }
    } catch (error) {
        // console.error('Erreur générale dans updateMapMarkers:', error);
    }
    // console.groupEnd();
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

// Exporter les fonctions pour les rendre accessibles depuis main.js
window.mapFunctions = {
    updateMapMarkers,
    getCategoryBadgeClass,
    getCategoryLabel,
    reverseGeocode,
    geocodeAddress,
    getCoordinates
};
