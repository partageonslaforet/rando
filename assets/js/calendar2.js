document.addEventListener('DOMContentLoaded', function() {
    const cellEvents = new Map();
    // Vérifier si nous sommes sur une page qui nécessite le calendrier
    const calendarContainer = document.querySelector('.calendar-container');
    if (!calendarContainer) {
        console.log(' Pas de calendrier sur cette page');
        return;
    }

    console.log(' Initialisation du calendrier...');

    // État du calendrier
    let currentDate = new Date();

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
            // Validation des événements
            if (!Array.isArray(events)) {
                console.warn('Events n\'est pas un tableau, conversion...');
                events = events ? (Array.isArray(events.data) ? events.data : [events]) : [];
            }
        
            // Nettoyage des événements pour éviter les problèmes de parsing
            events = events.map(event => {
                try {
                    return {
                        ...event,
                        date: event.date instanceof Date ? event.date : new Date(event.date)
                    };
                } catch (e) {
                    console.error('Erreur lors du traitement de l\'événement:', e);
                    return null;
                }
            }).filter(event => event !== null);
        
            console.log('Events après nettoyage complet:', events);
            
            // Réinitialiser la Map des événements
            cellEvents.clear();
            
            // Calculer le nombre de semaines nécessaires
            const totalCells = startingDay - 1 + totalDays;
            const totalWeeks = Math.ceil(totalCells / 7);
            const totalDaysToShow = totalWeeks * 7;
        
            let calendarContent = '';
            let dayCount = 1;
        
            for (let i = 0; i < totalDaysToShow; i++) {
                if (i % 7 === 0) calendarContent += '<tr>';
                
                if (i < startingDay - 1 || dayCount > totalDays) {
                    calendarContent += '<td class="calendar-day empty"></td>';
                } else {
                    try {
                        const date = new Date(year, month, dayCount);
                        const isToday = isCurrentDay(date);
                        
                        // Trouver les événements du jour
                        const dayEvents = events.filter(event => {
                            return event.date.getDate() === dayCount &&
                            event.date.getMonth() === month &&
                            event.date.getFullYear() === year;
                        });
                
                        // Debug Map
                        console.log(`Ajout à la Map - date: ${date.toISOString()}, events:`, dayEvents);
                        cellEvents.set(date.getTime(), dayEvents);
                
                        // Log de debug déplacé ici
                        console.log('Événements du jour:', {
                            date: date.toISOString(),
                            count: dayEvents.length,
                            events: dayEvents.map(e => ({
                                id: e.id,
                                title: e.title,
                                date: e.date.toISOString()
                            }))
                        });
        
                        // Déterminer si tous les événements sont passés
                        const now = new Date();
                        const allEventsPast = dayEvents.every(event => event.date < now);
        
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
        
                        // Construire la cellule du calendrier
                        calendarContent += `
                            <td class="${classes.join(' ')}" 
                                data-cell-id="${date.getTime()}" 
                                data-date="${date.toISOString().split('T')[0]}"
                                data-events-count="${dayEvents.length}">
                                <span class="day-number">${dayCount}</span>
                                ${dayEvents.length > 0 ? `<span class="event-count">${dayEvents.length}</span>` : ''}
                            </td>
                        `;
        
                        dayCount++;
                    } catch (error) {
                        console.error('Erreur lors du traitement du jour:', error);
                        calendarContent += '<td class="calendar-day error"><span class="day-number">!</span></td>';
                        dayCount++;
                    }
                }
                
                if (i % 7 === 6) calendarContent += '</tr>';
            }
                
           // Mettre à jour le contenu du calendrier
            const tbody = document.querySelector('#calendar tbody');
            console.log('tbody trouvé:', tbody);
            if (tbody) {
                tbody.innerHTML = calendarContent;
                console.error('tbody non trouvé dans:', document.querySelector('#calendar'));
            }

            // Ajouter les gestionnaires d'événements aux cellules
            document.querySelectorAll('.calendar-day:not(.empty)').forEach(day => {
                day.addEventListener('click', function() {
                    const cellId = this.getAttribute('data-cell-id');
                    const dayEvents = cellEvents.get(parseInt(cellId)) || [];

                    // Retirer la classe selected de l'ancien jour sélectionné
                    const previousSelected = document.querySelector('.calendar-day.selected');
                    if (previousSelected) {
                        previousSelected.classList.remove('selected');
                    }

                    // Ajouter la classe selected au jour cliqué
                    this.classList.add('selected');

                    // Mettre à jour la liste des événements
                    if (typeof window.updateEventsList === 'function') {
                        window.updateEventsList(dayEvents);
                    }

                    // Mettre à jour les filtres
                    if (window.currentFilters) {
                        window.currentFilters.selectedDate = this.getAttribute('data-date');
                    }
                });
            });

            console.groupEnd();
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
                // Simplifions les logs
                console.log('Réponse brute de l\'API:', {
                    status: response.status,
                    eventCount: response.data?.length || 0
                });

                // Vérifier la structure de la réponse
                if (response && response.data) {
                    const events = response.data.map(event => {
                        try {
                            return {
                                ...event,
                                date: new Date(event.date),
                                // Assurons-nous que les champs sensibles sont bien échappés
                                title: event.title ? String(event.title).replace(/[\u0000-\u001F\u007F-\u009F]/g, '') : '',
                                description: event.description ? String(event.description).replace(/[\u0000-\u001F\u007F-\u009F]/g, '') : ''
                            };
                        } catch (e) {
                            console.error('Erreur lors du parsing d\'un événement:', e);
                            return null;
                        }
                    }).filter(Boolean);

                    updateCalendarWithEvents(events);
                } else {
                    console.warn('Structure de réponse invalide');
                    updateCalendarWithEvents([]);
                }
            })
        .catch(error => {
            console.error('Erreur lors de la récupération des événements:', error);
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
