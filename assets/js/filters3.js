// Gestion des filtres d'événements
console.log('🔄 Chargement de filters.js...');

// Variables globales pour les filtres
let currentFilters = {
    period: 'upcoming',  // Par défaut : événements à venir
    category: 'all',     // Par défaut : toutes les catégories
    radius: 10,         // Par défaut : 10km
    search: '',         // Terme de recherche
    lastPeriod: null,   // Période avant recherche
    selectedDate: null  // Date sélectionnée dans le calendrier
};

// Fonction pour mettre à jour la liste des événements
window.updateEventsList = function(events) {
    console.log('\n=== MISE À JOUR LISTE ÉVÉNEMENTS ===');
    console.log('Nombre d\'événements à afficher:', events.length);
    
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

    

    // Mettre à jour les marqueurs sur la carte
    if (window.mapFunctions && window.mapFunctions.updateMapMarkers) {
        window.mapFunctions.updateMapMarkers(events);
    }
}

// Fonction pour filtrer les événements
window.filterEvents = function() {
    console.log('\n=== FILTRAGE DES ÉVÉNEMENTS ===');
    
    // Récupérer tous les événements
    let events = window.allEvents || [];
    console.log('Nombre total d\'événements:', events.length);
    
    let filteredEvents = events;
    const hasSearchTerm = currentFilters.search.trim() !== '';
    
    if (hasSearchTerm) {
        // Si une recherche est active, on cherche dans tous les événements
        const searchTerm = currentFilters.search.toLowerCase().trim();
        filteredEvents = events.filter(e => 
            e.title.toLowerCase().includes(searchTerm) ||
            (e.organizer && e.organizer.toLowerCase().includes(searchTerm))
        );
        console.log('Après recherche:', filteredEvents.length, 'événements trouvés');
    } else if (currentFilters.selectedDate) {
        // Si une date est sélectionnée dans le calendrier
        filteredEvents = events.filter(e => {
            const eventDate = new Date(e.date);
            const selectedDate = new Date(currentFilters.selectedDate);
            return eventDate.getDate() === selectedDate.getDate() &&
                   eventDate.getMonth() === selectedDate.getMonth() &&
                   eventDate.getFullYear() === selectedDate.getFullYear();
        });
        console.log('Après filtre date:', filteredEvents.length, 'événements');
    } else {
        // Sans recherche ni date, on applique d'abord le filtre temporel
        if (currentFilters.period !== 'all') {
            filteredEvents = filteredEvents.filter(e => getPeriodForEvent(e) === currentFilters.period);
            console.log('Après filtre période:', filteredEvents.length, 'événements');
        }
    }
    
    // Appliquer le filtre de catégorie dans tous les cas
    if (currentFilters.category !== 'all') {
        filteredEvents = filteredEvents.filter(e => e.category === currentFilters.category);
        console.log('Après filtre catégorie:', filteredEvents.length, 'événements');
    }
    
    // Mettre à jour l'interface
    updateEventsList(filteredEvents);
    updateFilterButtonsState(filteredEvents, hasSearchTerm || currentFilters.selectedDate !== null);
    
    // Mettre à jour le calendrier si nécessaire
    if (window.updateCalendar) {
        window.updateCalendar(filteredEvents);
    }
}

// Fonction pour mettre à jour l'apparence des boutons de filtre
function updateFilterButtonsState(filteredEvents, isSearchActive) {
    console.log('\n=== MISE À JOUR DES BOUTONS ===');
    
    // Pour les badges des périodes, on utilise les résultats de recherche si une recherche est active
    const eventsForPeriods = isSearchActive ? filteredEvents : window.allEvents || [];
    
    // Compter les événements par période
    const periodCounts = {
        'all': eventsForPeriods.length,
        'upcoming': eventsForPeriods.filter(e => getPeriodForEvent(e) === 'upcoming').length,
        'today': eventsForPeriods.filter(e => getPeriodForEvent(e) === 'today').length,
        'past': eventsForPeriods.filter(e => getPeriodForEvent(e) === 'past').length
    };
    
    console.log('Comptage des événements par période:', periodCounts);
    
    // Mettre à jour les boutons de période
    document.querySelectorAll('[data-period]').forEach(button => {
        const period = button.dataset.period;
        const count = periodCounts[period] || 0;
        const badge = button.querySelector('.badge');
        if (badge) {
            badge.textContent = count;
        }
        
        // Mise à jour du style
        if (!isSearchActive && period === currentFilters.period) {
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-primary');
        } else {
            button.classList.remove('btn-primary');
            button.classList.add('btn-outline-primary');
        }
        
        // Griser si count = 0
        if (count === 0) {
            button.classList.add('disabled');
            button.classList.add('text-muted');
        } else {
            button.classList.remove('disabled');
            button.classList.remove('text-muted');
        }
    });

    // Pour les catégories, on utilise les événements filtrés
    const categoryCounts = {
        'all': filteredEvents.length,
        'running': filteredEvents.filter(e => e.category === 'running').length,
        'hiking': filteredEvents.filter(e => e.category === 'hiking').length,
        'cycling': filteredEvents.filter(e => e.category === 'cycling').length
    };
    
    console.log('Comptage des événements par catégorie:', categoryCounts);
    
    // Mettre à jour les boutons de catégorie
    document.querySelectorAll('[data-type]').forEach(button => {
        const category = button.dataset.type;
        const count = categoryCounts[category] || 0;
        
        const badge = button.querySelector('.badge');
        if (badge) {
            badge.textContent = count;
        }
        
        // Mise à jour du style
        if (category === currentFilters.category) {
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-primary');
        } else {
            button.classList.remove('btn-primary');
            button.classList.add('btn-outline-primary');
        }
        
        // Griser si count = 0
        if (count === 0) {
            button.classList.add('disabled');
            button.classList.add('text-muted');
        } else {
            button.classList.remove('disabled');
            button.classList.remove('text-muted');
        }
    });

    // Mettre à jour le compteur total d'événements
    const totalCount = document.querySelector('.events-count');
    if (totalCount) {
        totalCount.textContent = filteredEvents.length + ' événements trouvés';
    }
}

// Fonction pour réinitialiser les filtres
window.resetFilters = function() {
    console.log('\n=== RÉINITIALISATION DES FILTRES ===');
    
    // Réinitialiser les filtres
    currentFilters = {
        period: 'upcoming',
        category: 'all',
        radius: 10,
        search: '',
        lastPeriod: null,
        selectedDate: null
    };
    
    // Réinitialiser la barre de recherche
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Réinitialiser le curseur de distance
    const proximityInput = document.getElementById('proximity');
    if (proximityInput) {
        proximityInput.value = 10;
        document.getElementById('proximityValue').textContent = '10';
    }
    
    // Mettre à jour l'interface
    filterEvents();
}

// Fonction pour déterminer la période d'un événement
function getPeriodForEvent(event) {
    // Créer la date d'aujourd'hui dans le fuseau local
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    // Créer la date de l'événement en tenant compte du fuseau horaire
    const eventDate = new Date(event.date.replace('Z', '')); // Enlever le Z pour forcer le fuseau local
    eventDate.setHours(0, 0, 0, 0);
    
    console.log('Date aujourd\'hui:', today.toLocaleString());
    console.log('Date événement:', eventDate.toLocaleString());
    
    let period;
    if (eventDate < today) {
        period = 'past';
    } else if (eventDate.getTime() === today.getTime()) {
        period = 'today';
    } else {
        period = 'upcoming';
    }
    
    return period;
}

// Initialisation des filtres
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les écouteurs d'événements pour les filtres temporels
    document.querySelectorAll('[data-period]').forEach(button => {
        button.addEventListener('click', function() {
            if (currentFilters.search.trim() === '') {
                // Seulement changer la période s'il n'y a pas de recherche active
                currentFilters.period = this.dataset.period;
                filterEvents();
            }
        });
    });
    
    // Initialiser les écouteurs d'événements pour les filtres de catégorie
    document.querySelectorAll('[data-type]').forEach(button => {
        button.addEventListener('click', function() {
            currentFilters.category = this.dataset.type;
            filterEvents();
        });
    });
    
    // Initialiser l'écouteur d'événements pour la barre de recherche
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    
    if (searchInput && searchButton) {
        // Fonction de recherche
        const performSearch = () => {
            const newSearchTerm = searchInput.value.trim();
            const oldSearchTerm = currentFilters.search.trim();
            
            currentFilters.search = newSearchTerm;
            
            if (newSearchTerm !== '') {
                // Nouvelle recherche : réinitialiser les filtres
                if (oldSearchTerm === '') {
                    // Sauvegarder la période actuelle avant de passer en mode recherche
                    currentFilters.lastPeriod = currentFilters.period;
                }
                currentFilters.period = 'all';
                currentFilters.category = 'all';
            } else if (oldSearchTerm !== '') {
                // Fin de recherche : restaurer la période précédente
                currentFilters.period = currentFilters.lastPeriod || 'upcoming';
            }
            
            filterEvents();
        };
        
        // Recherche au clic sur le bouton
        searchButton.addEventListener('click', performSearch);
        
        // Recherche à la frappe (avec debounce)
        let searchTimeout;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 300);
        });
        
        // Recherche à l'appui sur Entrée
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }
    
    // Initialiser l'écouteur d'événements pour le bouton de réinitialisation
    const resetButton = document.getElementById('resetFilters');
    if (resetButton) {
        resetButton.addEventListener('click', resetFilters);
    }
    
    // Filtrer les événements au chargement
    filterEvents();
});
