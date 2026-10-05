/**
 * Fichier: /assets/js/events/event-display.js
 * Rôle: Affichage partagé d’un événement (carte Leaflet, traces GPX, interactions du hero/topbar).
 * Utilisation: pages de détail/publication/aperçu (inline et dans modales).
 * Dépendances: Leaflet, leaflet-gpx, DOM (containers cartes et boutons), Web Share API (optionnel).
 */
(function () {
    'use strict';

    const COLORS = ['#990047', '#3A8A3D', '#0074D9', '#FF851B', '#B10DC9'];

    function isLeafletReady() {
        return typeof window.L !== 'undefined';
    }

    function initRouteMap(article) {
        const routeMapContainer = article.querySelector('#routeMap');
        if (!routeMapContainer || !isLeafletReady()) return;

        let routes = [];
        try {
            routes = JSON.parse(article.dataset.routes || '[]');
        } catch (e) {
            console.warn('Routes GPX invalides', e);
        }

        const routeMap = L.map(routeMapContainer);
        window.routeMap = routeMap;
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(routeMap);

        const bounds = L.latLngBounds();
        window._routeGpxPolylines = [];

        if (Array.isArray(routes) && routes.length > 0) {
            let remaining = routes.length;
            routes.forEach(function (gpxUrl, index) {
                if (!gpxUrl) {
                    remaining--;
                    return;
                }
                const color = COLORS[index % COLORS.length];
                fetchGpxPoints(gpxUrl).then(function (points) {
                    const poly = drawGpxLine(routeMap, points, color);
                    if (poly) {
                        bounds.extend(poly.getBounds());
                        window._routeGpxPolylines.push(poly);
                    }
                    remaining--;
                    if (remaining === 0 && window._routeGpxPolylines.length > 0) {
                        routeMap.fitBounds(bounds, { padding: [20, 20] });
                    }
                });
            });
        }

        window.showTrackOnMap = function (gpxUrl) {
            if (!gpxUrl || !window.routeMap) return;

            (window._routeGpxPolylines || []).forEach(function (layer) {
                if (layer) window.routeMap.removeLayer(layer);
            });
            window._routeGpxPolylines = [];

            fetchGpxPoints(gpxUrl).then(function (points) {
                const poly = drawGpxLine(window.routeMap, points, '#990047', 3, 0.7);
                if (poly) {
                    window._routeGpxPolylines.push(poly);
                    window.routeMap.fitBounds(poly.getBounds(), { padding: [30, 30] });
                }
            });
        };
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function fetchGpxPoints(gpxUrl) {
        try {
            const res = await fetch(gpxUrl);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const text = await res.text();
            const parser = new DOMParser();
            const xml = parser.parseFromString(text, 'text/xml');
            const points = Array.from(xml.getElementsByTagName('trkpt')).map(pt => [parseFloat(pt.getAttribute('lat')), parseFloat(pt.getAttribute('lon'))]).filter(c => !isNaN(c[0]) && !isNaN(c[1]));
            if (points.length < 2) {
                console.warn('GPX sans assez de points', gpxUrl);
                return [];
            }
            return points;
        } catch (e) {
            console.warn('Impossible de lire le GPX', gpxUrl, e);
            return [];
        }
    }

    function drawGpxLine(map, points, color, weight = 3, opacity = 0.8) {
        if (!points || points.length < 2) return null;
        const poly = L.polyline(points, { color, weight, opacity, lineCap: 'round' }).addTo(map);
        return poly;
    }

    /**
     * Initialise la carte d'un bloc .event-display.
     * @param {HTMLElement} article - élément .event-display
     */
    function initMap(article) {
        const mapContainer = article.querySelector('#eventMap');
        if (!mapContainer || !isLeafletReady()) return;

        const lat = parseFloat(article.dataset.lat);
        const lng = parseFloat(article.dataset.lng);
        const coords = !isNaN(lat) && !isNaN(lng) ? [lat, lng] : null;

        const defaultCenter = coords || [50.5039, 4.4699];
        const defaultZoom = coords ? 13 : 8;

        const map = L.map(mapContainer).setView(defaultCenter, defaultZoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        window.eventDisplayMap = map;

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

        if (coords) {
            L.marker(coords, { icon: window.eventPinIcon })
                .addTo(map)
                .bindPopup(escapeHtml(article.dataset.venue || article.dataset.location || 'Lieu de rendez-vous'));
            map.setView(coords, 13);
        }

        initRouteMap(article);
    }

    /**
     * Gère le bouton fermer / ré-ouvrir du hero.
     * @param {HTMLElement} article
     */
    function initHero(article) {
        const hero = article.querySelector('.event-hero');
        if (!hero) return;

        const closeBtn = hero.querySelector('.hero-close-btn');
        const reopenBtn = hero.querySelector('.hero-reopen-btn');
        const collapsedBar = hero.querySelector('.event-hero-collapsed');

        function setHeroOpen(isOpen) {
            hero.classList.toggle('hero-closed', !isOpen);
            if (collapsedBar) {
                collapsedBar.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
            }
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () { setHeroOpen(false); });
        }
        if (reopenBtn) {
            reopenBtn.addEventListener('click', function () { setHeroOpen(true); });
        }
    }

    /**
     * Initialise tout le contenu d'affichage d'un événement (carte, etc.).
     * @param {HTMLElement|Document|null} container
     */
    window.initEventDisplay = function (container) {
        container = container || document;
        let articles = [];

        if (container.querySelectorAll) {
            articles = Array.from(container.querySelectorAll('.event-display'));
        }

        // Cas où on passe directement l'article racine
        if (articles.length === 0 && container.nodeType === Node.ELEMENT_NODE && container.classList.contains('event-display')) {
            articles = [container];
        }

        articles.forEach(function (article) {
            if (article.dataset.initialized) return;
            initMap(article);
            initHero(article);
            article.dataset.initialized = 'true';
        });
    };

    // Initialisation automatique au chargement de la page
    document.addEventListener('DOMContentLoaded', function () {
        window.initEventDisplay(document);

        // --- Partage de l'événement ---
        function toast(msg, type='success') {
            const t = document.createElement('div');
            t.className = `ed-toast ${type}`;
            t.textContent = msg;
            Object.assign(t.style, {
                position:'fixed', bottom:'16px', left:'50%', transform:'translateX(-50%)',
                background: type==='success' ? 'rgba(46, 125, 50, .95)' : 'rgba(179, 38, 30, .95)',
                color:'#fff', padding:'10px 14px', borderRadius:'8px', zIndex:9999, fontWeight:'600'
            });
            document.body.appendChild(t);
            setTimeout(()=> t.remove(), 2200);
        }

        async function copyToClipboard(text) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch {
                try {
                    const ta = document.createElement('textarea');
                    ta.value = text; document.body.appendChild(ta);
                    ta.select(); document.execCommand('copy'); ta.remove();
                    return true;
                } catch (e) {
                    return false;
                }
            }
        }

        const shareButtons = document.querySelectorAll('[data-share="event"], #shareBtn');
        shareButtons.forEach((btn) => {
            btn.addEventListener('click', async () => {
                const url = window.location.href;
                const title = document.querySelector('.ehm-title, .event-title')?.textContent?.trim() || 'Événement';
                if (navigator.share) {
                    try { await navigator.share({ title, url }); return; } catch (e) { /* fallback*/ }
                }
                const ok = await copyToClipboard(url);
                if (ok) toast('Lien copié dans le presse‑papiers');
                else toast('Impossible de copier le lien', 'error');
            });
        });
    });
})();

document.addEventListener('DOMContentLoaded', function() {
  const lightbox = document.getElementById('image-lightbox');
  if (!lightbox) return;
  const lightboxImg = lightbox.querySelector('.lightbox-img');
  const closeBtn = lightbox.querySelector('.lightbox-close');
  const backdrop = lightbox.querySelector('.lightbox-backdrop');

  function openLightbox(src) {
    if (!src) return;
    lightboxImg.src = src;
    lightbox.style.display = 'flex';
    setTimeout(() => lightbox.classList.add('active'), 10);
  }

  // 1. Galerie : .aside-gallery-item et .gallery-item SONT les <a> (pas des parents)
  document.querySelectorAll('.aside-gallery-item, .gallery-item').forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const img = this.querySelector('img');
      openLightbox(img ? img.src : this.href);
    });
  });

  // 2. Image principale du hero : c'est un background-image CSS sur .event-hero-bg
  const hero = document.querySelector('.event-hero');
  const heroBg = document.querySelector('.event-hero-bg');
  if (hero && heroBg) {
    hero.style.cursor = 'zoom-in';
    hero.addEventListener('click', function(e) {
      // Ignorer les clics sur les boutons/liens du hero (retour, partager, fermer)
      if (e.target.closest('a, button')) return;
      const bg = getComputedStyle(heroBg).backgroundImage;
      const m = bg.match(/url\(["']?(.*?)["']?\)/);
      if (m && m[1]) openLightbox(m[1]);
    });
  }

  // 3. Logo organisateur (et toute autre <img> directe cliquable)
  document.querySelectorAll('.organizer-logo').forEach(img => {
    img.style.cursor = 'zoom-in';
    img.addEventListener('click', function(e) {
      e.preventDefault();
      openLightbox(this.src);
    });
  });

  function closeLightbox() {
    lightbox.classList.remove('active');
    setTimeout(() => { lightbox.style.display = 'none'; lightboxImg.src = ''; }, 200);
  }

  closeBtn.addEventListener('click', closeLightbox);
  backdrop.addEventListener('click', closeLightbox);
  document.addEventListener('keydown', function(e) {
    if (lightbox.style.display !== 'none' && (e.key === 'Escape' || e.key === 'Esc')) closeLightbox();
  });
});

