// Gestion des filtres d'événements

// Variables globales pour les filtres
let currentFilters = {
    period: 'upcoming',  // Par défaut : événements à venir
    type: 'all',        // Par défaut : toutes les catégories
    radius: 10          // Par défaut : 10km
};

// Fonction pour mettre à jour la liste des événements
window.updateEventsList = function(events) {
    
    // Mettre à jour le compteur total d'événements
    const totalCount = document.querySelector('.events-count');
    if (totalCount) {
        totalCount.textContent = events.length + ' événements trouvés';
    }
    
    // Trouver le container des événements
    const container = document.querySelector('.row.g-4');
    if (!container) {
        console.error('Container des événements non trouvé !');
        return;
    }
    
    // Vider la liste
    container.innerHTML = '';
    
    if (events.length === 0) {
        container.innerHTML = '<div class="col-12"><div class="alert alert-info">Aucun événement trouvé</div></div>';
        return;
    }

    // Créer les cartes d'événements
    events.forEach((event, index) => {
        const col = document.createElement('div');
        col.className = 'col-md-6 col-lg-4';
        
        col.innerHTML = `
            <div class="event-card card h-100">
                <img src="${event.image || '/assets/images/events/default-event.jpg'}" 
                     class="card-img-top" 
                     alt="${event.title}">
                <div class="card-body">
                    <h5 class="card-title">${event.title}</h5>
                    <p class="card-text">
                        <i class="bi bi-calendar"></i> ${new Date(event.date).toLocaleDateString('fr-FR')}<br>
                        <i class="bi bi-geo-alt"></i> ${event.location}
                    </p>
                    <a href="/templates/events/event-detail.php?id=${event.id}" 
                       class="btn btn-primary w-100">Voir les détails</a>
                </div>
            </div>
        `;
        
        container.appendChild(col);
    });

    // Mettre à jour les marqueurs sur la carte
    if (window.mapFunctions && window.mapFunctions.updateMapMarkers) {
        window.mapFunctions.updateMapMarkers(events);
    }
}

// Fonction pour filtrer les événements
window.filterEvents = function() {
    
    // Récupérer tous les événements
    let events = window.allEvents || [];
    
    // Filtrer par période
    let filteredEvents = events.filter(e => getPeriodForEvent(e) === currentFilters.period);
    
    // Filtrer par type si nécessaire
    if (currentFilters.type !== 'all') {
        filteredEvents = filteredEvents.filter(e => e.type === currentFilters.type);
    }
    
    // Mettre à jour l'interface
    updateEventsList(filteredEvents);
    updateFilterButtonsState();
}

// Fonction pour mettre à jour l'apparence des boutons de filtre
function updateFilterButtonsState() {
    
    // Récupérer tous les événements
    let events = window.allEvents || [];
    
    // Calculer les compteurs pour chaque période
    let periodCounts = {
        upcoming: events.filter(e => getPeriodForEvent(e) === 'upcoming').length,
        today: events.filter(e => getPeriodForEvent(e) === 'today').length,
        past: events.filter(e => getPeriodForEvent(e) === 'past').length
    };
    
    // Calculer les compteurs pour chaque type dans la période actuelle
    let currentPeriodEvents = events.filter(e => getPeriodForEvent(e) === currentFilters.period) || [];
    let typeCounts = {
        all: currentPeriodEvents.length || 0,
        running: currentPeriodEvents.filter(e => e.type === 'running').length || 0,
        hiking: currentPeriodEvents.filter(e => e.type === 'hiking').length || 0,
        cycling: currentPeriodEvents.filter(e => e.type === 'cycling').length || 0
    };
    
    // Mettre à jour les boutons
    document.querySelectorAll('[data-period], [data-type]').forEach(button => {
        const period = button.dataset.period;
        const type = button.dataset.type;
        let count = 0;
        
        if (period) {
            count = periodCounts[period] || 0;
        } else if (type) {
            count = typeCounts[type] || 0;
        }
        
        // Mettre à jour le compteur
        const countBadge = button.querySelector('.badge');
        if (countBadge) {
            countBadge.textContent = count;
        }
        
        // Gérer l'état du bouton
        button.classList.remove('btn-primary', 'btn-light', 'btn-secondary');
        
        if (count === 0 && type !== 'all') {
            button.classList.add('btn-secondary');
            button.disabled = true;
        } else {
            button.disabled = false;
            if ((period && currentFilters.period === period) || 
                (type && currentFilters.type === type)) {
                button.classList.add('btn-primary');
            } else {
                button.classList.add('btn-light');
            }
        }
    });
}

// Fonction pour réinitialiser les filtres
function resetFilters() {
    
    // Réinitialiser les filtres aux valeurs par défaut
    currentFilters = {
        period: 'upcoming',
        type: 'all',
        radius: 10
    };
    
    // Réinitialiser l'interface
    document.getElementById('searchInput').value = '';
    document.getElementById('proximity').value = 10;
    document.getElementById('proximityValue').textContent = '10';
    
    // Appliquer les filtres
    filterEvents();
}

// Fonction pour déterminer la période d'un événement
function getPeriodForEvent(event) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const eventDate = new Date(event.date);
    eventDate.setHours(0, 0, 0, 0);
    
    if (eventDate < today) {
        return 'past';
    } else if (eventDate.getTime() === today.getTime()) {
        return 'today';
    } else {
        return 'upcoming';
    }
}

// Initialisation des filtres
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les écouteurs d'événements pour les filtres temporels
    document.querySelectorAll('[data-period]').forEach(button => {
        button.addEventListener('click', function() {
            currentFilters.period = this.dataset.period;
            filterEvents();
        });
    });
    
    // Initialiser les écouteurs d'événements pour les filtres de type
    document.querySelectorAll('[data-type]').forEach(button => {
        button.addEventListener('click', function() {
            currentFilters.type = this.dataset.type;
            filterEvents();
        });
    });
    
    // Initialiser le bouton de réinitialisation
    document.getElementById('resetFilters').addEventListener('click', resetFilters);
    
    // Initialiser la recherche
    document.getElementById('searchInput').addEventListener('input', filterEvents);
    
    // Appliquer les filtres initiaux
    filterEvents();
});
