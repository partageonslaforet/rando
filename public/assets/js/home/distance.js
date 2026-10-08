/**
 * Fichier: /assets/js/home/distance.js
 * Rôle: Filtre distance — lieu de référence (saisie, géoloc, clic carte) + rayon 5-100 km.
 *       Dessine un cercle Leaflet autour du lieu; la liste des événements est filtrée
 *       via window.currentFilters.distance, les marqueurs hors rayon restent actifs.
 * Dépendances: Leaflet, window.mainMap, window.currentFilters (filters.js),
 *              geocodeAddress/reverseGeocode/getCoordinates (map.js ou event-maps.js).
 */
(function () {
    // État local du filtre distance
    const state = {
        origin: null,      // { lat, lng, label }
        pickMode: false,   // mode "cliquer sur la carte" armé
        layer: null        // L.layerGroup du cercle + marqueur de référence
    };

    // Expose l'état pour filters.js (applyFilters lit window.currentFilters.distance)
    window.currentFilters = window.currentFilters || {};
    window.currentFilters.distance = null;

    // Distance haversine en km entre deux points
    function haversineKm(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.sqrt(a));
    }

    // Test de filtre appelé par filters.js : l'événement est-il dans le rayon ?
    function eventWithinRadius(event, distanceFilter) {
        if (!distanceFilter || !distanceFilter.origin) return true;
        const coords = (typeof getCoordinates === 'function') ? getCoordinates(event) : null;
        if (!coords || !coords.lat || !coords.lng) return false;
        const km = haversineKm(distanceFilter.origin.lat, distanceFilter.origin.lng, coords.lat, coords.lng);
        return km <= distanceFilter.radius;
    }

    function els() {
        return {
            origin: document.getElementById('distanceOrigin'),
            geoloc: document.getElementById('distanceGeoloc'),
            clear: document.getElementById('distanceClear'),
            radius: document.getElementById('distanceRadius'),
            radiusLabel: document.getElementById('distanceRadiusLabel'),
            error: document.getElementById('distanceError')
        };
    }

    function showError(msg) {
        const { error } = els();
        if (!error) return;
        error.textContent = msg || '';
        error.hidden = !msg;
    }

    function updateRadiusUI() {
        const { radius, radiusLabel, clear } = els();
        if (!radius || !radiusLabel) return;
        radius.disabled = !state.origin;
        radiusLabel.textContent = `${radius.value} km`;
        if (clear) clear.hidden = !state.origin;
    }

    // Synchronise window.currentFilters.distance et relance le filtrage global
    function syncFilter() {
        window.currentFilters.distance = state.origin
            ? { origin: state.origin, radius: parseInt(els().radius.value, 10) }
            : null;
        if (typeof window.filterEvents === 'function') {
            window.filterEvents();
        }
    }

    // Dessine (ou supprime) le cercle + marqueur de référence sur la carte principale
    function renderRadius() {
        if (!window.mainMap) return;

        if (!state.layer) {
            state.layer = L.layerGroup().addTo(window.mainMap);
        }
        state.layer.clearLayers();

        if (!state.origin) return;

        const radiusKm = parseInt(els().radius.value, 10);

        L.circle([state.origin.lat, state.origin.lng], {
            radius: radiusKm * 1000,
            color: getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#4a7c59',
            weight: 2,
            fillOpacity: 0.08
        }).addTo(state.layer);

        L.circleMarker([state.origin.lat, state.origin.lng], {
            radius: 7,
            color: getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#4a7c59',
            weight: 3,
            fillColor: '#ffffff',
            fillOpacity: 1
        }).bindTooltip(`${state.origin.label} — rayon ${radiusKm} km`).addTo(state.layer);

        const circle = state.layer.getLayers()[0];
        if (circle) window.mainMap.fitBounds(circle.getBounds(), { padding: [40, 40] });
    }

    // Définit le lieu de référence puis met à jour filtre + carte
    function setOrigin(lat, lng, label) {
        state.origin = { lat, lng, label: label || `${lat.toFixed(5)}, ${lng.toFixed(5)}` };
        const { origin } = els();
        if (origin && label) origin.value = label;
        showError('');
        updateRadiusUI();
        syncFilter();
        renderRadius();
    }

    function clearOrigin() {
        state.origin = null;
        const { origin } = els();
        if (origin) origin.value = '';
        showError('');
        updateRadiusUI();
        syncFilter();
        renderRadius();
    }

    // Géocode la saisie libre (Enter ou sortie du champ)
    async function resolveTypedAddress() {
        const { origin } = els();
        const q = (origin?.value || '').trim();
        if (!q) { clearOrigin(); return; }
        try {
            if (typeof geocodeAddress !== 'function') throw new Error('geocode indisponible');
            const coords = await geocodeAddress(q);
            setOrigin(coords.lat, coords.lng, q);
        } catch (e) {
            showError('Lieu introuvable — essayez une adresse plus précise ou cliquez sur la carte.');
        }
    }

    // Géolocalisation navigateur
    function useGeolocation() {
        if (!navigator.geolocation) {
            showError('Géolocalisation non supportée par ce navigateur.');
            return;
        }
        navigator.geolocation.getCurrentPosition(async (pos) => {
            const { latitude, longitude } = pos.coords;
            let label = `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
            try {
                if (typeof reverseGeocode === 'function') {
                    label = await reverseGeocode(latitude, longitude);
                }
            } catch (e) { /* garder les coordonnées comme label */ }
            setOrigin(latitude, longitude, label);
        }, () => {
            showError('Position indisponible — autorisez la géolocalisation ou saisissez un lieu.');
        });
    }

    // Clic sur la carte : n'agit qu'en mode "pick" (armé au focus de l'input)
    function bindMapClick() {
        if (!window.mainMap) return;
        window.mainMap.on('click', async (e) => {
            if (!state.pickMode) return;
            state.pickMode = false;
            document.getElementById('distanceFilter')?.classList.remove('picking');
            const { lat, lng } = e.latlng;
            let label = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
            try {
                if (typeof reverseGeocode === 'function') {
                    label = await reverseGeocode(lat, lng);
                }
            } catch (err) { /* garder les coordonnées */ }
            setOrigin(lat, lng, label);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const { origin, geoloc, clear, radius, radiusLabel } = els();
        if (!origin || !radius) return;

        // Saisie libre : Enter ou blur → géocode
        origin.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); resolveTypedAddress(); }
        });
        origin.addEventListener('blur', () => {
            // Ne pas géocoder si le contenu correspond déjà au lieu courant
            if (state.origin && origin.value === state.origin.label) return;
            if (origin.value.trim()) resolveTypedAddress();
        });

        // Focus de l'input = mode "cliquer sur la carte" armé
        origin.addEventListener('focus', () => {
            state.pickMode = true;
            document.getElementById('distanceFilter')?.classList.add('picking');
        });
        origin.addEventListener('blur', () => {
            // Léger délai : un clic carte peut suivre immédiatement le focus
            setTimeout(() => {
                if (document.activeElement !== origin) {
                    state.pickMode = false;
                    document.getElementById('distanceFilter')?.classList.remove('picking');
                }
            }, 150);
        });

        geoloc?.addEventListener('click', useGeolocation);
        clear?.addEventListener('click', clearOrigin);

        radius.addEventListener('input', () => {
            if (radiusLabel) radiusLabel.textContent = `${radius.value} km`;
            if (state.origin) { syncFilter(); renderRadius(); }
        });

        updateRadiusUI();

        // La carte peut être initialisée après ce script : attendre window.mainMap
        const waitMap = setInterval(() => {
            if (window.mainMap) {
                clearInterval(waitMap);
                bindMapClick();
            }
        }, 250);
        setTimeout(() => clearInterval(waitMap), 15000);
    });

    // Exposé pour filters.js
    window.distanceFilter = { eventWithinRadius, haversineKm };
})();
