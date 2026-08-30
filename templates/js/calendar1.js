document.addEventListener('DOMContentLoaded', function() {

    // Gestion du filtre de proximité
    const proximityRange = document.getElementById('proximityRange');
    const rangeValue = document.getElementById('rangeValue');
    const toggleProximity = document.getElementById('toggleProximity');
    const proximitySection = document.getElementById('proximitySection');

        range: proximityRange,
        value: rangeValue,
        toggle: toggleProximity,
        section: proximitySection
    });

    function updateProximityFilter(enabled, value = null) {
        const currentParams = new URLSearchParams(window.location.search);
        
        if (enabled && value !== null) {
            currentParams.set('radius', value);
            toggleProximity.classList.remove('btn-secondary');
            toggleProximity.classList.add('btn-success');
            toggleProximity.querySelector('i').classList.remove('bi-toggle-off');
            toggleProximity.querySelector('i').classList.add('bi-toggle-on');
            proximitySection.classList.remove('disabled');
            proximityRange.disabled = false;
            rangeValue.textContent = value;
        } else {
            currentParams.delete('radius');
            toggleProximity.classList.remove('btn-success');
            toggleProximity.classList.add('btn-secondary');
            toggleProximity.querySelector('i').classList.remove('bi-toggle-on');
            toggleProximity.querySelector('i').classList.add('bi-toggle-off');
            proximitySection.classList.add('disabled');
            proximityRange.disabled = true;
            rangeValue.textContent = 'désactivé';
        }
        
        const newUrl = `${window.location.pathname}?${currentParams.toString()}`;
        window.location.href = newUrl;
    }

    if (proximityRange && rangeValue && toggleProximity) {
        toggleProximity.addEventListener('click', function() {
            const isEnabled = !proximityRange.disabled;
            updateProximityFilter(!isEnabled, proximityRange.value);
        });

        proximityRange.addEventListener('input', function() {
            if (!this.disabled) {
                updateProximityFilter(true, this.value);
            }
        });
    }

    // Gestion du calendrier
    const prevMonth = document.getElementById('prevMonth');
    const nextMonth = document.getElementById('nextMonth');
    const currentMonthElement = document.getElementById('currentMonth');
    let currentDate = new Date();

    function updateCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDay = firstDay.getDay() || 7; // Convertir 0 (dimanche) en 7
        const today = new Date();

            year,
            month,
            daysInMonth,
            startingDay,
            today: today.toISOString()
        });

        // Mise à jour du titre
        const monthName = currentDate.toLocaleString('fr-FR', { month: 'long' });
        currentMonthElement.textContent = `${monthName} ${year}`;

        // Récupération des événements pour ce mois
        const apiUrl = `/api/events.php?month=${month + 1}&year=${year}`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                const calendarBody = document.getElementById('calendar-body');
                calendarBody.innerHTML = '';

                // Cases vides pour les jours précédents
                for (let i = 1; i < startingDay; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.className = 'calendar-day p-2 text-center text-muted bg-light rounded';
                    calendarBody.appendChild(emptyDay);
                }

                // Jours du mois
                for (let day = 1; day <= daysInMonth; day++) {
                    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const eventsForDay = data.filter(event => event.date.startsWith(dateStr));

                    const dayElement = document.createElement('div');
                    const classes = ['calendar-day', 'p-2', 'text-center', 'position-relative', 'rounded'];
                    
                    // Vérifier si c'est aujourd'hui
                    if (today.getDate() === day && 
                        today.getMonth() === month && 
                        today.getFullYear() === year) {
                        classes.push('today');
                    }

                    if (eventsForDay.length > 0) {
                        classes.push('bg-success', 'text-white');
                    } else {
                        classes.push('bg-light');
                    }
                    
                    dayElement.className = classes.join(' ');
                    dayElement.textContent = day;

                    if (eventsForDay.length > 0) {
                        const badge = document.createElement('span');
                        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                        badge.textContent = eventsForDay.length;
                        dayElement.appendChild(badge);
                    }

                    calendarBody.appendChild(dayElement);
                }
            })
            .catch(error => console.error('Erreur lors du chargement des événements:', error));
    }

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

    // Initialisation du calendrier
    updateCalendar();

    // Gestion du calendrier
    function initCalendar() {
        console.group('Calendar Initialization');
        const now = new Date();
        const currentMonth = now.getMonth();
        const currentYear = now.getFullYear();
        
        // Charger les événements pour le mois en cours
        loadEvents(currentMonth + 1, currentYear);
    }

    function loadEvents(month, year) {
        console.group('Loading Events');
        
        fetch(`/api/events.php?month=${month}&year=${year}`)
            .then(response => response.json())
            .then(events => {
                updateCalendar(month, year, events);
            })
            .catch(error => {
                console.error('Error loading events:', error);
            })
            .finally(() => {
                console.groupEnd();
            });
    }

    function updateCalendar(month, year, events) {
        console.group('Updating Calendar');
        
        const firstDay = new Date(year, month - 1, 1);
        const lastDay = new Date(year, month, 0);
        const startingDay = firstDay.getDay();
        const totalDays = lastDay.getDate();
        
            firstDay,
            lastDay,
            startingDay,
            totalDays
        });

        // Mettre à jour l'en-tête du calendrier
        const monthNames = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
                           "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
        document.getElementById('currentMonth').textContent = `${monthNames[month-1]} ${year}`;

        // Générer les cellules du calendrier
        let calendarHTML = '';
        let dayCount = 1;

        // En-tête des jours de la semaine
        calendarHTML += '<div class="calendar-grid">';
        const days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        days.forEach(day => {
            calendarHTML += `<div class="calendar-header">${day}</div>`;
        });

        // Remplir les jours
        for (let i = 0; i < 42; i++) {
            if (i < startingDay || dayCount > totalDays) {
                calendarHTML += '<div class="calendar-day empty"></div>';
            } else {
                const currentDate = new Date(year, month - 1, dayCount);
                const isToday = isCurrentDay(currentDate);
                
                // Trouver les événements pour ce jour
                const dayEvents = events.filter(event => {
                    const eventDate = new Date(event.date);
                    return eventDate.getDate() === dayCount &&
                           eventDate.getMonth() === month - 1 &&
                           eventDate.getFullYear() === year;
                });
                

                let classes = ['calendar-day'];
                if (isToday) classes.push('current-day');
                if (dayEvents.length > 0) classes.push('has-events');

                calendarHTML += `
                    <div class="${classes.join(' ')}">
                        <span class="day-number">${dayCount}</span>
                        ${isToday ? '<span class="current-day-dot"></span>' : ''}
                        ${dayEvents.length > 0 ? `<span class="event-dot" title="${dayEvents.length} événement(s)"></span>` : ''}
                    </div>`;
                dayCount++;
            }
        }
        calendarHTML += '</div>';

        // Mettre à jour le calendrier
        document.querySelector('.calendar').innerHTML = calendarHTML;
        console.groupEnd();
    }

    function isCurrentDay(date) {
        const today = new Date();
        return date.getDate() === today.getDate() &&
               date.getMonth() === today.getMonth() &&
               date.getFullYear() === today.getFullYear();
    }

    // Initialiser le calendrier au chargement
    initCalendar();
});
