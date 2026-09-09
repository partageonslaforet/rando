document.addEventListener('DOMContentLoaded', function() {

    // État du calendrier
    let currentDate = new Date();
    const calendarContainer = document.querySelector('.calendar-container');

    if (!calendarContainer) {
        console.error('❌ Conteneur du calendrier non trouvé');
        return;
    }

    // Fonction pour mettre à jour le calendrier
    function updateCalendar() {
        console.group('📅 Mise à jour du calendrier');
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        // Calcul des paramètres du calendrier
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startingDay = firstDay.getDay() || 7; // Convertir 0 (dimanche) en 7
        const totalDays = lastDay.getDate();
        

        // Générer le HTML du calendrier
        const monthNames = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
                          "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
        
        let calendarHTML = `
            <div class="calendar-nav p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <button class="btn btn-link text-decoration-none" id="prevMonth">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <h2 class="mb-0">${monthNames[month]} ${year}</h2>
                    <button class="btn btn-link text-decoration-none" id="nextMonth">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div class="calendar-grid">`;

        // En-tête des jours
        const days = ['Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'];
        days.forEach(day => {
            calendarHTML += `<div class="calendar-header">${day}</div>`;
        });

        // Récupérer les événements pour ce mois
        fetch(`/api/events/events-counts.php?month=${month + 1}&year=${year}`)
            .then(response => response.json())
            .then(events => {

                // Remplir les jours
                let dayCount = 1;
                for (let i = 0; i < 42; i++) {
                    if (i < startingDay - 1 || dayCount > totalDays) {
                        calendarHTML += '<div class="calendar-day empty"></div>';
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

                        // Construire la classe CSS
                        const classes = ['calendar-day'];
                        if (isToday) classes.push('current-day');
                        if (dayEvents.length > 0) classes.push('has-events');

                        calendarHTML += `
                            <div class="${classes.join(' ')}" data-date="${date.toISOString()}">
                                <span class="day-number">${dayCount}</span>
                                ${dayEvents.length > 0 ? `<span class="event-dot" title="${dayEvents.length} événement(s)"></span>` : ''}
                            </div>`;
                        dayCount++;
                    }
                }

                calendarHTML += '</div>';
                
                // Mettre à jour le conteneur
                calendarContainer.innerHTML = calendarHTML;
                
                // Réattacher les événements de navigation
                const prevMonth = document.getElementById('prevMonth');
                const nextMonth = document.getElementById('nextMonth');
                
                if (prevMonth && nextMonth) {
                    prevMonth.addEventListener('click', () => {
                        currentDate.setMonth(currentDate.getMonth() - 1);
                        updateCalendar();
                    });

                    nextMonth.addEventListener('click', () => {
                        currentDate.setMonth(currentDate.getMonth() + 1);
                        updateCalendar();
                    });
                }

                // Ajouter les événements sur les jours avec événements
                document.querySelectorAll('.calendar-day.has-events').forEach(day => {
                    day.addEventListener('click', () => {
                        const date = new Date(day.dataset.date);
                        window.location.href = `/templates/events/events.php?date=${date.toISOString().split('T')[0]}`;
                    });
                });
            })
            .catch(error => {
                console.error('❌ Erreur lors du chargement des événements:', error);
                calendarContainer.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement du calendrier</div>';
            })
            .finally(() => {
                console.groupEnd();
            });
    }

    // Fonction utilitaire pour vérifier si une date est aujourd'hui
    function isCurrentDay(date) {
        const today = new Date();
        return date.getDate() === today.getDate() &&
               date.getMonth() === today.getMonth() &&
               date.getFullYear() === today.getFullYear();
    }

    // Initialiser le calendrier
    updateCalendar();
});
