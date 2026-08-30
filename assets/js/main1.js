// Variables globales
window.currentFilters = {
    period: 'upcoming',
    type: 'all',
    search: '',
    proximity: null
};

window.defaultImage = '/assets/images/events/default-event.jpg';

// Fonction pour formater une date
window.formatDate = function(dateString) {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('fr-FR', options);
};

// Fonction pour obtenir la classe du badge en fonction de la catégorie
window.getCategoryBadgeClass = function(category) {
    return 'badge-category';
};

// Fonction pour obtenir le libellé de la catégorie
window.getCategoryLabel = function(category) {
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
};

// Fonction pour mettre à jour les boutons de filtre
window.updateFilterButtons = function(filteredEvents) {
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
    
    window.allEvents?.forEach(event => {
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
            
            if (window.currentFilters.period === period) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
            
            button.disabled = count === 0;
        }
    });
    
    // Mettre à jour les boutons de type
    const typeButtons = document.querySelectorAll('[data-type]');
    typeButtons.forEach(button => {
        const type = button.dataset.type;
        const count = type === 'all' ? window.allEvents?.length : (typeCounts[type] || 0);
        
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
        
        if (window.currentFilters.type === type) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
        
        button.disabled = count === 0;
    });
};

// Fonction pour mettre à jour la liste des événements
window.updateEventsList = function(events) {
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
            <div class="card h-100 shadow-sm border-0 event-card" 
                 data-event-id="${event.id}"
                 data-event-type="${event.category}"
                 data-event-period="${event.period}"
                 data-latitude="${event.latitude}"
                 data-longitude="${event.longitude}">
                <div class="position-relative">
                    <img src="${event.image || window.defaultImage}" 
                         class="card-img-top" 
                         alt="${event.title}"
                         style="height: 200px; object-fit: cover;">
                    <span class="position-absolute top-0 end-0 m-2 badge-category">
                        ${window.getCategoryLabel(event.category)}
                    </span>
                </div>
                <div class="card-body p-3">
                    <h5 class="card-title mb-3 event-title">${event.title}</h5>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-calendar-event me-2"></i>
                            <span>${window.formatDate(event.date)}</span>
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
};

// Fonction pour générer le calendrier
window.generateCalendar = function(year, month, events) {
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDay = firstDay.getDay() || 7; // Convertir 0 (dimanche) en 7
    
    const calendarBody = document.getElementById('calendar-body');
    if (!calendarBody) return;
    
    // Mettre à jour l'affichage du mois courant
    const monthDisplay = document.getElementById('currentMonth');
    if (monthDisplay) {
        const monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        monthDisplay.textContent = `${monthNames[month]} ${year}`;
    }
    
    let html = '';
    let day = 1;
    let dayCount = 1;
    
    // Créer les semaines
    while (day <= daysInMonth) {
        html += '<tr>';
        
        // Créer les jours de la semaine
        for (let i = 1; i <= 7; i++) {
            if (dayCount < startingDay || day > daysInMonth) {
                html += '<td></td>';
            } else {
                // Vérifier si des événements existent pour ce jour
                const currentDate = new Date(year, month, day);
                const eventsToday = events?.filter(event => {
                    const eventDate = new Date(event.date);
                    return eventDate.getDate() === day && 
                           eventDate.getMonth() === month && 
                           eventDate.getFullYear() === year;
                }) || [];
                
                const hasEvents = eventsToday.length > 0;
                const isToday = new Date().toDateString() === currentDate.toDateString();
                
                html += `
                    <td class="${hasEvents ? 'has-events' : ''} ${isToday ? 'today' : ''}"
                        data-date="${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}">
                        ${day}
                        ${hasEvents ? `<span class="event-indicator">${eventsToday.length}</span>` : ''}
                    </td>
                `;
                day++;
            }
            dayCount++;
        }
        html += '</tr>';
    }
    
    calendarBody.innerHTML = html;
    
    // Ajouter les écouteurs d'événements pour les cellules du calendrier
    const dateCells = calendarBody.querySelectorAll('td[data-date]');
    dateCells.forEach(cell => {
        cell.addEventListener('click', function() {
            const date = this.dataset.date;
            if (date) {
                // Filtrer les événements pour cette date
                const selectedEvents = events?.filter(event => event.date === date) || [];
                // Mettre à jour la carte et la liste des événements
                window.mapFunctions.updateMapMarkers(selectedEvents);
                window.updateEventsList(selectedEvents);
            }
        });
    });
};
