/**
 * Fichier: /assets/js/core/init.js
 * Rôle: Initialisation globale (dropdowns, perPage, chargement initial paginé).
 * Utilisation: exécuté au DOMContentLoaded sur les pages publiques avec liste d’événements.
 * Dépendances: window.EventsAPI, Bootstrap Dropdown, localStorage.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Retirer la classe js-loading
    document.body.classList.remove('js-loading');

    // Initialiser les dropdowns Bootstrap
    var dropdowns = document.querySelectorAll('.dropdown-toggle');
    dropdowns.forEach(function(dropdown) {
        new bootstrap.Dropdown(dropdown);
    });
    
    // S'assurer que EventsAPI est défini
    if (typeof EventsAPI === 'undefined') {
        console.error('EventsAPI n\'est pas défini');
        return;
    }

    // Préférence par page (localStorage -> data-page-size -> défaut 5)
    const containerEl = document.getElementById('events-container');
    function getPerPage() {
        try {
            const stored = localStorage.getItem('events_per_page');
            if (stored && !Number.isNaN(parseInt(stored, 10))) return parseInt(stored, 10);
        } catch (e) { /* ignore */ }
        if (containerEl && containerEl.dataset.pageSize) {
            const v = parseInt(containerEl.dataset.pageSize, 10);
            if (!Number.isNaN(v) && v > 0) return v;
        }
        return 5;
    }
    let perPage = getPerPage();

    // Charger les événements initiaux avec pagination serveur
    const loadPage = (page = 1, limit = perPage) => {
        EventsAPI.getAllEventsPaged({}, page, limit).then(({ data, pagination }) => {
            if (!Array.isArray(data)) {
                console.warn('Format des événements invalide');
                return;
            }

            // Mettre à jour la liste des événements si la fonction existe
            if (window.eventListFunctions && typeof window.eventListFunctions.updateEventsList === 'function') {
                window.eventListFunctions.updateEventsList(data);
            } else {
                console.warn('eventListFunctions.updateEventsList n\'est pas disponible');
            }

            // Rendu pagination si disponible
            if (window.eventListFunctions && typeof window.eventListFunctions.renderPagination === 'function') {
                window.eventListFunctions.renderPagination(pagination, (newPage) => loadPage(newPage, perPage));
            }

            // Rendu du contrôle Par page en bas
            if (window.eventListFunctions && typeof window.eventListFunctions.renderPerPageControl === 'function') {
                window.eventListFunctions.renderPerPageControl(perPage, (newPerPage) => {
                    perPage = newPerPage;
                    try { localStorage.setItem('events_per_page', String(perPage)); } catch (e) {}
                    if (!window.__filtersDrivesList) {
        loadPage(1, perPage);
    }
                });
            }

            // Calculer les compteurs sur la page courante (en attendant un endpoint de compteurs paginés)
            const now = new Date();
            const upcomingEvents = data.filter(event => new Date(event.date) > now);
            const todayEvents = data.filter(event => {
                const eventDate = new Date(event.date);
                return eventDate.toDateString() === now.toDateString();
            });
            const pastEvents = data.filter(event => new Date(event.date) < now);

            // Mettre à jour les badges avec vérification
            const updateBadge = (selector, value) => {
                const badge = document.querySelector(selector);
                if (badge) {
                    badge.textContent = value;
                }
            };

            updateBadge('[data-period="upcoming"] .badge', upcomingEvents.length);
            updateBadge('[data-period="today"] .badge', todayEvents.length);
            updateBadge('[data-period="past"] .badge', pastEvents.length);

        }).catch(error => {
            console.error('Erreur lors du chargement paginé des événements:', error);
        });
    };

    loadPage(1, perPage);
});