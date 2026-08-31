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

    // Charger les événements initiaux
    EventsAPI.getAllEvents().then(events => {
        if (!events || !Array.isArray(events)) {
            console.warn('Format des événements invalide');
            return;
        }

        // Mettre à jour la liste des événements si la fonction existe
        if (window.eventListFunctions && typeof window.eventListFunctions.updateEventsList === 'function') {
            window.eventListFunctions.updateEventsList(events);
        } else {
            console.warn('eventListFunctions.updateEventsList n\'est pas disponible');
        }

        // Calculer les compteurs
        const now = new Date();
        const upcomingEvents = events.filter(event => new Date(event.date) > now);
        const todayEvents = events.filter(event => {
            const eventDate = new Date(event.date);
            return eventDate.toDateString() === now.toDateString();
        });
        const pastEvents = events.filter(event => new Date(event.date) < now);

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
        console.error('Erreur lors du chargement des événements:', error);
    });
});