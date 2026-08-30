// Variables globales
const currentFilters = {
    period: 'upcoming',
    type: 'all',
    search: '',
    proximity: null
};

// Image par défaut pour les événements
const defaultImage = '/assets/images/events/default-event.jpg';

// Fonction pour formater une date
function formatDate(dateString) {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('fr-FR', options);
}

// Fonction pour obtenir la classe du badge en fonction de la catégorie
function getCategoryBadgeClass(category) {
    return 'badge-category';
}

// Fonction pour obtenir le libellé de la catégorie
function getCategoryLabel(category) {
    switch (category) {
        case 'running':
            return 'Course à pied';
        case 'hiking':
            return 'Randonnée';
        case 'cycling':
            return 'Vélo';
        default:
            return category;
    }
}

// Fonction pour filtrer les événements
function filterEvents(filterType, filterValue) {
    // Mettre à jour les filtres si spécifiés
    if (filterType && filterValue !== undefined) {
        currentFilters[filterType] = filterValue;
    }
    
    // Commencer avec tous les événements
    let filteredEvents = allEvents || [];
    
    // Appliquer le filtre de recherche si présent
    if (currentFilters.search) {
        const searchTerm = currentFilters.search.toLowerCase();
        filteredEvents = filteredEvents.filter(event => 
            event.title?.toLowerCase().includes(searchTerm) ||
            event.description?.toLowerCase().includes(searchTerm) ||
            event.organisation?.toLowerCase().includes(searchTerm) ||
            event.location?.toLowerCase().includes(searchTerm) ||
            event.venue?.toLowerCase().includes(searchTerm)
        );
    }
    
    // Appliquer le filtre de type si ce n'est pas 'all'
    if (currentFilters.type && currentFilters.type !== 'all') {
        filteredEvents = filteredEvents.filter(event => event.category === currentFilters.type);
    }
    
    // Appliquer le filtre de période
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    
    const today = new Date(now.getTime() - (now.getTimezoneOffset() * 60000))
        .toISOString()
        .split('T')[0];
    
    if (currentFilters.period === 'upcoming') {
        filteredEvents = filteredEvents.filter(event => {
            const eventDate = new Date(event.date);
            eventDate.setHours(0, 0, 0, 0);
            return eventDate > now;
        });
    } else if (currentFilters.period === 'today') {
        filteredEvents = filteredEvents.filter(event => event.date === today);
    } else if (currentFilters.period === 'past') {
        filteredEvents = filteredEvents.filter(event => {
            const eventDate = new Date(event.date);
            eventDate.setHours(0, 0, 0, 0);
            return eventDate < now;
        });
    }
    
    // Appliquer le filtre de proximité si activé
    if (currentFilters.proximity !== null) {
        // À implémenter selon vos besoins
    }
    
    // Mettre à jour l'interface
    updateEventsList(filteredEvents);
    updateFilterButtons(filteredEvents);
    
    // Mettre à jour la carte avec les événements filtrés
    if (mapFunctions && mapFunctions.updateMapMarkers) {
        mapFunctions.updateMapMarkers(filteredEvents);
    }
    
    // Mettre à jour le calendrier avec tous les événements
    generateCalendar(new Date().getFullYear(), new Date().getMonth(), allEvents);
}

// Fonction pour réinitialiser les filtres
function resetFilters() {
    currentFilters.period = 'upcoming';
    currentFilters.type = 'all';
    currentFilters.search = '';
    currentFilters.proximity = null;
    
    // Réinitialiser l'interface
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Réinitialiser le filtre de proximité
    const proximityToggle = document.getElementById('toggleProximity');
    const proximitySection = document.getElementById('proximitySection');
    if (proximityToggle) {
        proximityToggle.checked = false;
    }
    if (proximitySection) {
        proximitySection.classList.remove('active');
        proximitySection.classList.add('disabled');
    }
    
    // Appliquer les filtres réinitialisés
    filterEvents();
}

// Fonction pour mettre à jour les boutons de filtre
function updateFilterButtons(filteredEvents) {
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    
    const today = new Date(now.getTime() - (now.getTimezoneOffset() * 60000))
        .toISOString()
        .split('T')[0];
    
    // Calculer les compteurs pour chaque période
    const periodCounts = {
        upcoming: 0,
        today: 0,
        past: 0
    };
    
    // Calculer les compteurs pour chaque type
    const typeCounts = {
        all: 0,
        running: 0,
        hiking: 0,
        cycling: 0
    };
    
    allEvents?.forEach(event => {
        const eventDate = new Date(event.date);
        eventDate.setHours(0, 0, 0, 0);
        
        // Incrémenter les compteurs de période
        if (event.date === today) {
            periodCounts.today++;
        } else if (eventDate > now) {
            periodCounts.upcoming++;
        } else {
            periodCounts.past++;
        }
        
        // Incrémenter les compteurs de type
        typeCounts.all++;
        if (event.category) {
            typeCounts[event.category]++;
        }
    });
    
    // Mettre à jour les boutons de période
    const periodButtons = {
        upcoming: document.getElementById('upcomingEventsBtn'),
        today: document.getElementById('todayEventsBtn'),
        past: document.getElementById('pastEventsBtn')
    };
    
    Object.entries(periodButtons).forEach(([period, button]) => {
        if (button) {
            const count = periodCounts[period];
            const badge = button.querySelector('.badge');
            if (badge) {
                badge.textContent = count;
                
                if (count === 0) {
                    badge.classList.add('badge-light');
                    badge.classList.remove('badge-primary');
                } else {
                    badge.classList.add('badge-primary');
                    badge.classList.remove('badge-light');
                }
            }
            
            if (currentFilters.period === period) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
            
            button.disabled = count === 0;
        }
    });
    
    // Mettre à jour les boutons de type
    const typeButtons = document.querySelectorAll('[data-filter-type]');
    typeButtons.forEach(button => {
        const type = button.dataset.filterType;
        const count = type === 'all' ? allEvents?.length : (typeCounts[type] || 0);
        
        const badge = button.querySelector('.badge');
        if (badge) {
            badge.textContent = count;
            
            if (count === 0) {
                badge.classList.add('badge-light');
                badge.classList.remove('badge-primary');
            } else {
                badge.classList.add('badge-primary');
                badge.classList.remove('badge-light');
            }
        }
        
        if (currentFilters.type === type) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
        
        if (type !== 'all') {
            button.disabled = count === 0;
        }
    });
}

// Fonction pour mettre à jour la liste des événements
function updateEventsList(events) {
    const eventsList = document.getElementById('events-list');
    if (!eventsList) return;

    if (!events || events.length === 0) {
        eventsList.innerHTML = `
            <div class="col">
                <div class="alert alert-info">Aucun événement ne correspond à vos critères de recherche.</div>
            </div>
        `;
        return;
    }

    eventsList.innerHTML = events.map(event => `
        <div class="col">
            <div class="card h-100 shadow-sm border-0 event-card">
                <div class="position-relative">
                    <img src="${event.image || defaultImage}" 
                         class="card-img-top" 
                         alt="${event.title}"
                         style="height: 200px; object-fit: cover;">
                    <span class="position-absolute top-0 end-0 m-2 badge-category">
                        ${getCategoryLabel(event.category)}
                    </span>
                </div>
                <div class="card-body p-3">
                    <h5 class="card-title mb-3">${event.title}</h5>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-calendar-event me-2"></i>
                            <span>${formatDate(event.date)}</span>
                        </div>
                        ${event.location ? `
                            <div class="d-flex align-items-center">
                                <i class="bi bi-geo-alt me-2"></i>
                                <span>${event.location}</span>
                            </div>
                        ` : ''}
                        ${event.organisation ? `
                            <div class="d-flex align-items-center">
                                <i class="bi bi-building me-2"></i>
                                <span>${event.organisation}</span>
                            </div>
                        ` : ''}
                        ${event.price ? `
                            <div class="d-flex align-items-center">
                                <i class="bi bi-tag me-2"></i>
                                <span>${event.price}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
                <div class="card-footer bg-white border-0 p-3 pt-0">
                    <a href="/event/${event.id}" class="btn btn-primary w-100">Voir les détails</a>
                </div>
            </div>
        </div>
    `).join('');
}

// Fonction pour générer le calendrier
function generateCalendar(year, month, events) {
    const calendarBody = document.getElementById('calendar-body');
    if (!calendarBody) return;
    
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const today = now.toISOString().split('T')[0];
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startingDay = firstDay.getDay() || 7; // Convertir 0 (dimanche) en 7
    const monthLength = lastDay.getDate();
    
    // Mettre à jour le titre du mois
    const monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                       'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    const currentMonthElement = document.getElementById('currentMonth');
    if (currentMonthElement) {
        currentMonthElement.textContent = `${monthNames[month]} ${year}`;
    }
    
    let calendar = '';
    let day = 1;
    
    // Créer les lignes du calendrier
    for (let i = 0; i < 6; i++) {
        let row = '<tr>';
        
        // Créer les cellules de chaque ligne
        for (let j = 1; j <= 7; j++) {
            if (i === 0 && j < startingDay) {
                // Jours du mois précédent
                const prevMonthLastDay = new Date(year, month, 0).getDate();
                const prevMonthDay = prevMonthLastDay - (startingDay - j - 1);
                row += `<td class="other-month"><div>${prevMonthDay}</div></td>`;
            } else if (day > monthLength) {
                // Jours du mois suivant
                const nextMonthDay = day - monthLength;
                row += `<td class="other-month"><div>${nextMonthDay}</div></td>`;
                day++;
            } else {
                // Jours du mois courant
                const currentDate = new Date(year, month, day);
                const isToday = currentDate.getTime() === now.getTime();
                
                // Trouver les événements pour ce jour
                const dayEvents = events?.filter(event => {
                    const eventDate = new Date(event.date);
                    return eventDate.getDate() === day && 
                           eventDate.getMonth() === month && 
                           eventDate.getFullYear() === year;
                }) || [];
                
                // Vérifier si tous les événements de ce jour sont passés
                const hasOnlyPastEvents = dayEvents.length > 0 && dayEvents.every(event => {
                    const eventDate = new Date(event.date);
                    eventDate.setHours(0, 0, 0, 0);
                    return eventDate < now;
                });
                
                // Créer les classes CSS
                const classes = [
                    isToday ? 'today' : '',
                    dayEvents.length > 0 ? 'has-events' : '',
                    hasOnlyPastEvents ? 'past-events' : ''
                ].filter(Boolean).join(' ');
                
                row += `<td${classes ? ` class="${classes}"` : ''}>
                    <div>
                        ${day}
                        ${dayEvents.length > 0 ? `<span class="event-count">${dayEvents.length}</span>` : ''}
                    </div>
                </td>`;
                
                day++;
            }
        }
        
        row += '</tr>';
        calendar += row;
        
        // Arrêter si nous avons dépassé le nombre de jours du mois
        if (day > monthLength && i < 5) break;
    }
    
    calendarBody.innerHTML = calendar;
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    
    const prevButton = document.getElementById('prevMonth');
    const nextButton = document.getElementById('nextMonth');
    
    if (prevButton && nextButton) {
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        
        prevButton.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            generateCalendar(currentYear, currentMonth, allEvents);
        });
        
        nextButton.addEventListener('click', function() {
            currentMonth = (currentMonth + 1) % 12;
            if (currentMonth === 0) {
                currentYear++;
            }
            generateCalendar(currentYear, currentMonth, allEvents);
        });
    }
    
    // Initialiser les filtres
    filterEvents();
});
