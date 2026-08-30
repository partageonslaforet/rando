document.addEventListener('DOMContentLoaded', function() {
    console.log(' Initialisation du calendrier...');

    // État du calendrier
    let currentDate = new Date();
    const calendarContainer = document.querySelector('.calendar-container');

    if (!calendarContainer) {
        console.error(' Conteneur du calendrier non trouvé');
        return;
    }

    // Fonction pour mettre à jour le calendrier
    window.updateCalendar = function(providedEvents = null) {
        console.group(' Mise à jour du calendrier');
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        // Calcul des paramètres du calendrier
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startingDay = firstDay.getDay() || 7; // Convertir 0 (dimanche) en 7
        const totalDays = lastDay.getDate();
        
        console.log('Paramètres:', { year, month, startingDay, totalDays });

        // Générer le HTML du calendrier
        const monthNames = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
                          "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
        
        let calendarHTML = '<table class="calendar"><thead>';
        
        // Titre et navigation
        calendarHTML += `
            <tr class="calendar-header">
                <th colspan="7">
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-link text-decoration-none" id="prevMonth">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <h2 class="mb-0" id="currentMonth">${monthNames[month]} ${year}</h2>
                        <button class="btn btn-link text-decoration-none" id="nextMonth">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </th>
            </tr>`;

        // En-tête des jours
        calendarHTML += '<tr>';
        const days = ['Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'];
        days.forEach(day => {
            calendarHTML += `<th>${day}</th>`;
        });
        calendarHTML += '</tr></thead><tbody>';

        // Fonction pour déterminer si un événement est passé
        const isPastEvent = (date) => {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return date < today;
        };

        // Remplir les jours
        let dayCount = 1;
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Calculer le nombre de semaines nécessaires
        const totalCells = startingDay - 1 + totalDays;
        const totalWeeks = Math.ceil(totalCells / 7);
        const totalDaysToShow = totalWeeks * 7;

        for (let i = 0; i < totalDaysToShow; i++) {
            if (i % 7 === 0) calendarHTML += '<tr>';
            
            if (i < startingDay - 1 || dayCount > totalDays) {
                calendarHTML += '<td class="calendar-day empty"></td>';
            } else {
                const date = new Date(year, month, dayCount);
                const isToday = isCurrentDay(date);
                
                // Trouver les événements du jour
                const dayEvents = events.filter(event => {
                    const eventDate = new Date(event.date);
                    return eventDate.getDate() === dayCount &&
                           eventDate.getMonth() === month &&
                           eventDate.getFullYear() === year;
                });

                // Déterminer si tous les événements sont passés
                const allEventsPast = dayEvents.length > 0 && 
                    dayEvents.every(event => isPastEvent(new Date(event.date)));

                // Construire les classes CSS
                const classes = ['calendar-day'];
                if (isToday) classes.push('current-day');
                if (dayEvents.length > 0) {
                    classes.push('has-events');
                    if (allEventsPast) {
                        classes.push('past-events');
                    } else {
                        classes.push('future-events');
                    }
                }

                calendarHTML += `
                    <td class="${classes.join(' ')}" data-date="${date.toISOString()}">
                        <span class="day-number">${dayCount}</span>
                        ${dayEvents.length > 0 ? 
                            `<span class="event-badge ${allEventsPast ? 'past' : ''}">${dayEvents.length}</span>` 
                            : ''}
                    </td>`;
                dayCount++;
            }
            if ((i + 1) % 7 === 0) calendarHTML += '</tr>';
        }

        calendarHTML += '</tbody></table>';
        
        // Mettre à jour le conteneur
        calendarContainer.innerHTML = calendarHTML;
        
        // Ajouter les gestionnaires d'événements pour la navigation
        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            updateCalendar();
        });

        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            updateCalendar();
        });

        // Si des événements sont fournis, les utiliser directement
        if (providedEvents) {
            updateCalendarWithEvents(providedEvents);
            return;
        }

        // Sinon, charger les événements depuis l'API
        fetch(`/api/events.php?month=${month + 1}&year=${year}&calendar=true`)
            .then(response => response.json())
            .then(response => {
                console.log('Réponse de l\'API:', response);
                const events = response.data || [];
                updateCalendarWithEvents(events);
            })
            .catch(error => {
                console.error('Erreur lors du chargement des événements:', error);
                updateCalendarWithEvents([]);
            });
    };

    // Fonction utilitaire pour vérifier si une date est aujourd'hui
    function isCurrentDay(date) {
        const today = new Date();
        return date.getDate() === today.getDate() &&
               date.getMonth() === today.getMonth() &&
               date.getFullYear() === today.getFullYear();
    }
});
document.addEventListener('DOMContentLoaded', function() {
    console.log(' Initialisation du calendrier...');

    // État du calendrier
    let currentDate = new Date();
    const calendarContainer = document.querySelector('.calendar-container');

    if (!calendarContainer) {
        console.error(' Conteneur du calendrier non trouvé');
        return;
    }

    // Fonction pour mettre à jour le calendrier
    window.updateCalendar = function(providedEvents = null) {
        console.group(' Mise à jour du calendrier');
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        // Calcul des paramètres du calendrier
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startingDay = firstDay.getDay() || 7; // Convertir 0 (dimanche) en 7
        const totalDays = lastDay.getDate();
        
        console.log('Paramètres:', { year, month, startingDay, totalDays });

        // Générer le HTML du calendrier
        const monthNames = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
                          "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
        
        let calendarHTML = '<table class="calendar"><thead>';
        
        // Titre et navigation
        calendarHTML += `
            <tr class="calendar-header">
                <th colspan="7">
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-link text-decoration-none" id="prevMonth">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <h2 class="mb-0" id="currentMonth">${monthNames[month]} ${year}</h2>
                        <button class="btn btn-link text-decoration-none" id="nextMonth">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </th>
            </tr>`;

        // En-tête des jours
        calendarHTML += '<tr>';
        const days = ['Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'];
        days.forEach(day => {
            calendarHTML += `<th>${day}</th>`;
        });
        calendarHTML += '</tr></thead><tbody>';

        // Fonction pour déterminer si un événement est passé
        const isPastEvent = (date) => {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return date < today;
        };

        // Fonction pour mettre à jour le calendrier avec les événements
        const updateCalendarWithEvents = (events) => {
            console.group(' Mise à jour du calendrier avec les événements');
            console.log('Events reçus:', events);
            
            try {
                if (!Array.isArray(events)) {
                    console.warn(' Les événements ne sont pas un tableau, conversion...');
                    events = events ? [events] : [];
                }
                
                // Remplir les jours
                let dayCount = 1;
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                // Calculer le nombre de semaines nécessaires
                const totalCells = startingDay - 1 + totalDays;
                const totalWeeks = Math.ceil(totalCells / 7);
                const totalDaysToShow = totalWeeks * 7;

                for (let i = 0; i < totalDaysToShow; i++) {
                    if (i % 7 === 0) calendarHTML += '<tr>';
                    
                    if (i < startingDay - 1 || dayCount > totalDays) {
                        calendarHTML += '<td class="calendar-day empty"></td>';
                    } else {
                        const date = new Date(year, month, dayCount);
                        const isToday = isCurrentDay(date);
                        
                        // Trouver les événements du jour
                        const dayEvents = events.filter(event => {
                            const eventDate = new Date(event.date);
                            return eventDate.getDate() === dayCount &&
                                   eventDate.getMonth() === month &&
                                   eventDate.getFullYear() === year;
                        });

                        // Déterminer si tous les événements sont passés
                        const allEventsPast = dayEvents.length > 0 && 
                            dayEvents.every(event => isPastEvent(new Date(event.date)));

                        // Vérifier si ce jour est sélectionné
                        const isSelected = window.currentFilters && 
                                 window.currentFilters.selectedDate &&
                                 isSameDay(new Date(window.currentFilters.selectedDate), date);

                        // Construire les classes CSS
                        const classes = ['calendar-day'];
                        if (isToday) classes.push('current-day');
                        if (dayEvents.length > 0) {
                            classes.push('has-events');
                            if (allEventsPast) {
                                classes.push('past-events');
                            } else {
                                classes.push('future-events');
                            }
                        }
                        if (isSelected) classes.push('selected');

                        calendarHTML += `
                            <td class="${classes.join(' ')}" data-date="${date.toISOString()}">
                                <span class="day-number">${dayCount}</span>
                                ${dayEvents.length > 0 ? 
                                    `<span class="event-badge ${allEventsPast ? 'past' : ''}">${dayEvents.length}</span>` 
                                    : ''}
                            </td>`;
                        dayCount++;
                    }
                    if ((i + 1) % 7 === 0) calendarHTML += '</tr>';
                }

                calendarHTML += '</tbody></table>';
                
                // Mettre à jour le conteneur
                calendarContainer.innerHTML = calendarHTML;
                
                // Ajouter les gestionnaires d'événements pour la navigation
                document.getElementById('prevMonth').addEventListener('click', () => {
                    currentDate.setMonth(currentDate.getMonth() - 1);
                    updateCalendar();
                });

                document.getElementById('nextMonth').addEventListener('click', () => {
                    currentDate.setMonth(currentDate.getMonth() + 1);
                    updateCalendar();
                });

                // Ajouter les gestionnaires d'événements pour les jours avec événements
                document.querySelectorAll('.calendar-day.has-events').forEach(day => {
                    day.addEventListener('click', function() {
                        // Retirer la classe selected de l'ancien jour sélectionné
                        const previousSelected = document.querySelector('.calendar-day.selected');
                        if (previousSelected) {
                            previousSelected.classList.remove('selected');
                        }

                        // Ajouter la classe selected au jour cliqué
                        this.classList.add('selected');

                        const dateStr = this.dataset.date;
                        
                        // Mettre à jour les filtres et filtrer les événements
                        if (window.currentFilters && window.filterEvents) {
                            window.currentFilters.selectedDate = dateStr;
                            window.currentFilters.period = 'all'; // Désactiver le filtre de période
                            window.filterEvents();
                        }
                    });
                });

                console.groupEnd();
            } catch (error) {
                console.error(' Erreur lors de la mise à jour du calendrier:', error);
                calendarContainer.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement du calendrier</div>';
                console.groupEnd();
            }
        };

        // Si des événements sont fournis, les utiliser directement
        if (providedEvents) {
            updateCalendarWithEvents(providedEvents);
            return;
        }

        // Sinon, charger les événements depuis l'API
        fetch(`/api/events.php?month=${month + 1}&year=${year}&calendar=true`)
            .then(response => response.json())
            .then(response => {
                console.log('Réponse de l\'API:', response);
                const events = response.data || [];
                updateCalendarWithEvents(events);
            })
            .catch(error => {
                console.error('Erreur lors du chargement des événements:', error);
                updateCalendarWithEvents([]);
            });
    };

    // Fonction utilitaire pour vérifier si une date est aujourd'hui
    function isCurrentDay(date) {
        const today = new Date();
        return date.getDate() === today.getDate() &&
               date.getMonth() === today.getMonth() &&
               date.getFullYear() === today.getFullYear();
    }

    // Fonction utilitaire pour vérifier si deux dates sont le même jour
    function isSameDay(date1, date2) {
        return date1.getDate() === date2.getDate() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getFullYear() === date2.getFullYear();
    }

    // Initialiser le calendrier
    updateCalendar();
});
