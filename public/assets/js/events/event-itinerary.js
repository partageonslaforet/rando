/**
 * Fichier: /assets/js/events/event-itinerary.js
 * Rôle: Card « Itinéraire » de la page détail — calcule un trajet vers le lieu de RDV
 *       via OSRM (leaflet-routing-machine, chargé dynamiquement) et l'affiche sur une
 *       mini-carte dédiée avec profils voiture/vélo/pied et instructions en français.
 * Utilisation: #itineraryMap + #itineraryStart + #itineraryGeoloc dans .itinerary-card
 *              (templates/events/event-display.php); coords lues sur <article class="event-display">.
 * Dépendances: Leaflet (obligatoire), leaflet-routing-machine (chargé à la demande),
 *              Nominatim (géocodage départ), Geolocation API (clic « Votre position »).
 */
(function () {
    'use strict';

    const LRM_JS = 'https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js';
    // Instances OSRM publiques (FOSSGIS, celles d'openstreetmap.org) : une par profil,
    // le serveur démo router.project-osrm.org ne gère que 'driving'.
    const OSRM_PROFILE_URLS = {
        driving: 'https://routing.openstreetmap.de/routed-car/route/v1',
        cycling: 'https://routing.openstreetmap.de/routed-bike/route/v1',
        walking: 'https://routing.openstreetmap.de/routed-foot/route/v1'
    };
    const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
    const GEOCODE_DELAY_MS = 1100; // respecte la limite Nominatim (1 req/s)

    const geocodeCache = new Map();
    let lastGeocodeAt = 0;

    // ---------------------------------------------------------------- utils

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            const s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = function () { reject(new Error('Chargement impossible : ' + src)); };
            document.head.appendChild(s);
        });
    }

    async function ensureRoutingLib() {
        if (window.L && L.Routing) return;
        try {
            await loadScript(LRM_JS);
        } catch (e) {
            console.error('[event-itinerary] Leaflet Routing Machine indisponible :', e);
            throw e;
        }
        if (!L.Routing) throw new Error('L.Routing absent après chargement');
    }

    function setError(el, msg) {
        if (!el) return;
        if (msg) {
            el.textContent = msg;
            el.hidden = false;
        } else {
            el.hidden = true;
            el.textContent = '';
        }
    }

    function formatDistance(m) {
        if (m >= 1000) return (m / 1000).toFixed(1).replace('.', ',') + ' km';
        return Math.round(m) + ' m';
    }

    function formatDuration(s) {
        const min = Math.round(s / 60);
        if (min < 60) return min + ' min';
        const h = Math.floor(min / 60);
        const m = min % 60;
        return m ? h + ' h ' + String(m).padStart(2, '0') : h + ' h';
    }

    async function geocode(query) {
        const q = String(query || '').trim();
        if (!q) throw new Error('Adresse vide');
        if (geocodeCache.has(q)) return geocodeCache.get(q);

        const wait = GEOCODE_DELAY_MS - (Date.now() - lastGeocodeAt);
        if (wait > 0) await new Promise(function (r) { setTimeout(r, wait); });
        lastGeocodeAt = Date.now();

        const url = NOMINATIM_URL + '?q=' + encodeURIComponent(q) + '&limit=1&format=json&addressdetails=1';
        const res = await fetch(url);
        if (!res.ok) throw new Error('Géocodage impossible (HTTP ' + res.status + ')');
        const data = await res.json();
        if (!data || !data.length) throw new Error('Adresse introuvable : « ' + q + ' »');
        const coords = { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon) };
        geocodeCache.set(q, coords);
        return coords;
    }

    // ------------------------------------------------------------ carte / rendu

    function ensurePinIcons() {
        if (!window.eventPinIcon) {
            window.eventPinIcon = L.divIcon({
                className: 'event-pin',
                html: '<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>',
                iconSize: [30, 42],
                iconAnchor: [15, 42],
                popupAnchor: [0, -38]
            });
        }
        if (!window.itineraryStartIcon) {
            window.itineraryStartIcon = L.divIcon({
                className: 'itinerary-start-pin',
                html: '<i class="bi bi-record-circle-fill" aria-hidden="true"></i>',
                iconSize: [22, 22],
                iconAnchor: [11, 11],
                popupAnchor: [0, -12]
            });
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ------------------------------------------------------------- initialisation

    function init() {
        const mapEl = document.getElementById('itineraryMap');
        const article = mapEl ? mapEl.closest('.event-display') : null;
        if (!mapEl || !article || !window.L) return;

        const lat = parseFloat(article.dataset.lat);
        const lng = parseFloat(article.dataset.lng);
        if (isNaN(lat) || isNaN(lng)) {
            mapEl.closest('.itinerary-card')?.setAttribute('hidden', '');
            return;
        }
        const destination = L.latLng(lat, lng);

        const ui = {
            input: document.getElementById('itineraryStart'),
            geoloc: document.getElementById('itineraryGeoloc'),
            reset: document.getElementById('itineraryReset'),
            profiles: document.querySelectorAll('.itinerary-profile'),
            summary: document.getElementById('itinerarySummary'),
            error: document.getElementById('itineraryError')
        };

        ensurePinIcons();

        // Carte créée paresseusement au premier calcul : L.map sur un élément
        // display:none ne calcule pas sa taille correctement.
        let map = null;
        let routeLayers = null;

        function ensureMap() {
            if (map) return;
            mapEl.hidden = false;
            map = L.map(mapEl, { scrollWheelZoom: false }).setView(destination, 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: ' OpenStreetMap contributors'
            }).addTo(map);
            map.attributionControl.addAttribution('Route par <a href="https://project-osrm.org" target="_blank" rel="noopener">OSRM</a>');
            L.marker(destination, { icon: window.eventPinIcon })
                .addTo(map)
                .bindPopup(escapeHtml(article.dataset.venue || article.dataset.location || 'Lieu de rendez-vous'));
            routeLayers = L.layerGroup().addTo(map);
            map.invalidateSize();
        }

        let currentProfile = 'driving';
        let startLatLng = null;
        let routing = false;

        function updateReset() {
            if (!ui.reset) return;
            ui.reset.hidden = !(startLatLng || (ui.input && ui.input.value.trim()));
        }

        function resetItinerary() {
            startLatLng = null;
            if (ui.input) ui.input.value = '';
            if (routeLayers) routeLayers.clearLayers();
            if (ui.summary) ui.summary.textContent = '';
            setError(ui.error, '');
            updateReset();
        }

        async function computeRoute() {
            if (!startLatLng || routing) return;
            routing = true;
            setError(ui.error, '');
            ui.summary && (ui.summary.textContent = 'Calcul…');
            try {
                ensureMap();
                await ensureRoutingLib();
                const router = L.Routing.osrmv1({
                    serviceUrl: OSRM_PROFILE_URLS[currentProfile] || OSRM_PROFILE_URLS.driving,
                    profile: 'driving',
                    language: 'fr'
                });
                router.route(
                    [L.Routing.waypoint(startLatLng), L.Routing.waypoint(destination)],
                    function (err, routes) {
                        routing = false;
                        if (err || !routes || !routes.length) {
                            setError(ui.error, "Itinéraire introuvable pour ce mode de déplacement.");
                            ui.summary && (ui.summary.textContent = '');
                            return;
                        }
                        const r = routes[0];
                        routeLayers.clearLayers();
                        L.polyline(r.coordinates, {
                            color: '#3A8A3D', // = var(--primary-color); var() non résolue en attribut SVG
                            weight: 5,
                            opacity: 0.85
                        }).addTo(routeLayers);
                        L.marker(startLatLng, { icon: window.itineraryStartIcon }).addTo(routeLayers);
                        map.fitBounds(L.polyline(r.coordinates).getBounds(), { padding: [30, 30] });

                        if (ui.summary) {
                            ui.summary.textContent = formatDistance(r.summary.totalDistance) +
                                ' · ' + formatDuration(r.summary.totalTime);
                        }
                    }
                );
            } catch (e) {
                routing = false;
                console.error('[event-itinerary] Erreur de routage :', e);
                setError(ui.error, "Le service d'itinéraire est indisponible. Réessayez plus tard.");
                ui.summary && (ui.summary.textContent = '');
            }
        }

        // --- départ saisi (Entrée) : géocodage Nominatim puis routage
        ui.input && ui.input.addEventListener('keydown', async function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            setError(ui.error, '');
            try {
                const coords = await geocode(ui.input.value);
                startLatLng = L.latLng(coords.lat, coords.lng);
                updateReset();
                computeRoute();
            } catch (err) {
                setError(ui.error, err.message);
            }
        });
        ui.input && ui.input.addEventListener('input', updateReset);

        // --- « Effacer » : vide le départ, le tracé et le résumé
        ui.reset && ui.reset.addEventListener('click', resetItinerary);

        // --- « Votre position » : géolocalisation au clic
        ui.geoloc && ui.geoloc.addEventListener('click', function () {
            setError(ui.error, '');
            if (!navigator.geolocation) {
                setError(ui.error, 'La géolocalisation n’est pas supportée par ce navigateur.');
                return;
            }
            ui.geoloc.classList.add('loading');
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    ui.geoloc.classList.remove('loading');
                    startLatLng = L.latLng(pos.coords.latitude, pos.coords.longitude);
                    if (ui.input) ui.input.value = 'Votre position';
                    updateReset();
                    computeRoute();
                },
                function (err) {
                    ui.geoloc.classList.remove('loading');
                    const msg = err.code === 1
                        ? 'Géolocalisation refusée — saisissez une adresse de départ.'
                        : 'Position introuvable — saisissez une adresse de départ.';
                    setError(ui.error, msg);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });

        // --- bascule de profil : recalcule si un départ est déjà défini
        ui.profiles.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.dataset.profile === currentProfile) return;
                ui.profiles.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                currentProfile = btn.dataset.profile;
                computeRoute();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
