// Variables globales
window.currentFilters = {
    period: 'upcoming',
    type: 'all',
    search: '',
    proximity: null
};

// Définir defaultImage uniquement s'il n'existe pas déjà
if (typeof window.defaultImage === 'undefined') {
    window.defaultImage = '/assets/images/events/default-event.jpg';
}

// Déclarer filterEvents comme une fonction globale
window.filterEvents = function(filterType, filterValue) {
    // console.group('=== FILTRAGE DES ÉVÉNEMENTS ===');
    
    // Mettre à jour les filtres si spécifiés
    if (filterType && filterValue !== undefined) {
        window.currentFilters[filterType] = filterValue;
    }
    
    
    // Commencer avec tous les événements
    let filteredEvents = window.allEvents || [];
    //     id: e.id,
    //     title: e.title,
    //     date: e.date,
    //     category: e.category
    // }))); // Log de la liste complète des événements
    
    // Appliquer le filtre de recherche si présent
    if (window.currentFilters.search) {
        const searchTerm = window.currentFilters.search.toLowerCase();
        filteredEvents = filteredEvents.filter(event => 
            event.title?.toLowerCase().includes(searchTerm) ||
            event.description?.toLowerCase().includes(searchTerm) ||
            event.organisation?.toLowerCase().includes(searchTerm) ||
            event.location?.toLowerCase().includes(searchTerm) ||
            event.venue?.toLowerCase().includes(searchTerm)
        );
    }
    
    // Appliquer le filtre de type si ce n'est pas 'all'
    if (window.currentFilters.type && window.currentFilters.type !== 'all') {
        filteredEvents = filteredEvents.filter(event => {
            const matches = event.category === window.currentFilters.type;
            return matches;
        });
    }
    
    // Appliquer le filtre de période
    const now = new Date();
    now.setHours(0, 0, 0, 0); // Réinitialiser l'heure à minuit
    
    // Obtenir la date d'aujourd'hui au format YYYY-MM-DD
    const today = new Date(now.getTime() - (now.getTimezoneOffset() * 60000))
        .toISOString()
        .split('T')[0];
    
    
    if (window.currentFilters.period === 'upcoming') {
        filteredEvents = filteredEvents.filter(event => {
            const eventDate = new Date(event.date);
            eventDate.setHours(0, 0, 0, 0);
            const isUpcoming = eventDate > now;
            return isUpcoming;
        });
    } else if (window.currentFilters.period === 'today') {
        filteredEvents = filteredEvents.filter(event => {
            const isToday = event.date === today;
            return isToday;
        });
    } else if (window.currentFilters.period === 'past') {
        filteredEvents = filteredEvents.filter(event => {
            const eventDate = new Date(event.date);
            eventDate.setHours(0, 0, 0, 0);
            const isPast = eventDate < now;
            return isPast;
        });
    }
    
    // Appliquer le filtre de proximité si activé
    if (window.currentFilters.proximity !== null) {
        // À implémenter selon vos besoins
    }

    //     id: e.id,
    //     title: e.title,
    //     date: e.date,
    //     category: e.category
    // })));
    
    // Mettre à jour l'interface
    updateEventsList(filteredEvents);
    
    updateFilterButtons(filteredEvents);
    
    // Mettre à jour la carte avec les événements filtrés
    if (window.mapFunctions && window.mapFunctions.updateMapMarkers) {
        window.mapFunctions.updateMapMarkers(filteredEvents);
    }
    
    // Mettre à jour le calendrier avec tous les événements
    generateCalendar(new Date().getFullYear(), new Date().getMonth(), window.allEvents);
    
    // console.groupEnd();
};

// Fonction pour réinitialiser les filtres
window.resetFilters = function() {
    window.currentFilters = {
        period: 'upcoming',
        type: 'all',
        search: '',
        proximity: null
    };
    
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
    window.filterEvents();
};

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

// Fonction pour mettre à jour les boutons de filtre
function updateFilterButtons(filteredEvents) {
    // console.group('=== MISE À JOUR DES BOUTONS DE FILTRE ===');
    
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    
    // Compter les événements pour chaque période
    const counts = {
        upcoming: 0,
        today: 0,
        past: 0
    };
    
    const today = new Date(now.getTime() - (now.getTimezoneOffset() * 60000))
        .toISOString()
        .split('T')[0];
    
    window.allEvents?.forEach(event => {
        const eventDate = new Date(event.date);
        eventDate.setHours(0, 0, 0, 0);
        
        if (eventDate > now) {
            counts.upcoming++;
        } else if (event.date === today) {
            counts.today++;
        } else if (eventDate < now) {
            counts.past++;
        }
    });
    
    // Mettre à jour le compteur pour chaque bouton de période
    const periodButtons = {
        upcoming: document.getElementById('upcomingEventsBtn'),
        today: document.getElementById('todayEventsBtn'),
        past: document.getElementById('pastEventsBtn')
    };
    
    Object.entries(periodButtons).forEach(([period, button]) => {
        if (button) {
            const count = counts[period];
            const badge = button.querySelector('.badge');
            if (badge) {
                badge.textContent = count;
                
                // Mettre à jour les classes du badge
                if (count === 0) {
                    badge.classList.add('badge-light');
                    badge.classList.remove('badge-primary');
                } else {
                    badge.classList.add('badge-primary');
                    badge.classList.remove('badge-light');
                }
            }
            
            // Mettre à jour l'état actif du bouton
            if (window.currentFilters.period === period) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
            
            // Désactiver le bouton si aucun événement
            button.disabled = count === 0;
        }
    });
    
    // Compter les événements pour chaque type
    const typeCounts = {};
    window.allEvents?.forEach(event => {
        if (!typeCounts[event.category]) {
            typeCounts[event.category] = 0;
        }
        typeCounts[event.category]++;
    });
    
    // Mettre à jour les compteurs pour les boutons de type
    const typeButtons = document.querySelectorAll('[data-filter-type]');
    typeButtons.forEach(button => {
        const type = button.dataset.filterType;
        const count = type === 'all' ? window.allEvents?.length : (typeCounts[type] || 0);
        
        const badge = button.querySelector('.badge');
        if (badge) {
            badge.textContent = count;
            
            // Mettre à jour les classes du badge
            if (count === 0) {
                badge.classList.add('badge-light');
                badge.classList.remove('badge-primary');
            } else {
                badge.classList.add('badge-primary');
                badge.classList.remove('badge-light');
            }
        }
        
        // Mettre à jour l'état actif du bouton
        if (window.currentFilters.type === type) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
        
        // Désactiver le bouton si aucun événement (sauf le bouton "Tous")
        if (type !== 'all') {
            button.disabled = count === 0;
        }
    });
    
    // console.groupEnd();
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
                    <img src="${event.image || window.defaultImage}" 
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
            generateCalendar(currentYear, currentMonth, window.allEvents);
        });
        
        nextButton.addEventListener('click', function() {
            currentMonth = (currentMonth + 1) % 12;
            if (currentMonth === 0) {
                currentYear++;
            }
            generateCalendar(currentYear, currentMonth, window.allEvents);
        });
    }
});
