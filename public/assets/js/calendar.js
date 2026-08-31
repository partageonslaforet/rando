document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;
    
    window.calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'fr',
        firstDay: 1,
        headerToolbar: {
            left: 'prev',
            center: 'title',
            right: 'next'
        },
        eventDisplay: 'block',
        displayEventTime: false,
        displayEventEnd: false,
        height: 'auto',
        dayHeaderFormat: { weekday: 'short' },
        // Nouvelles options pour gérer les bordures
        borderColor: 'transparent',
        dayCellClassNames: 'no-border',
        dayMaxEvents: true,
        views: {
            dayGrid: {
                dayMaxEventRows: 0,
                fixedWeekCount: false
            }
        },
        eventContent: function(arg) {
            const dayEvents = arg.event.extendedProps.categories.length;
            return { 
                html: `<div class="event-badge">${dayEvents}</div>`
            };
        },
        dateClick: function(info) {
            selectDate(info.dateStr);
        },
        eventClick: function(info) {
            const date = info.event.startStr ? info.event.startStr.split('T')[0] : '';
            if (date) selectDate(date);
            info.jsEvent.preventDefault();
        }
    });
    
    window.calendar.render();

    // Fonction pour filtrer les événements d'une date et mettre à jour la carte/liste
    function selectDate(date) {
        if (!window.allEvents || !window.mapFunctions || !window.eventListFunctions) return;

        const selectedEvents = window.allEvents.filter(event => {
            if (!event.date) return false;
            return event.date.split(' ')[0] === date;
        });

        if (selectedEvents.length > 0) {
            window.mapFunctions.updateMapMarkers(selectedEvents);
            window.eventListFunctions.updateEventsList(selectedEvents);
        }
    }

    // Charger les événements
    if (typeof EventsAPI !== 'undefined') {
        EventsAPI.getAllEvents().then(events => {
            window.allEvents = events;
            console.log('Events reçus:', events); // Debug
    
            // Grouper les événements par date
            const eventsByDate = events.reduce((acc, event) => {
                const date = event.date.split(' ')[0];
                if (!acc[date]) {
                    acc[date] = [];
                }
                acc[date].push(event);
                return acc;
            }, {});
    
            console.log('Events groupés:', eventsByDate); // Debug
    
            // Créer les événements du calendrier avec les badges groupés
            const calendarEvents = Object.entries(eventsByDate).map(([date, dayEvents]) => {
                const categories = [...new Set(dayEvents.map(event => event.category))];
                console.log(`Catégories pour ${date}:`, categories); // Debug
                
                return {
                    start: date,
                    categories: categories,
                    allDay: true
                };
            });
    
            console.log('Events calendrier:', calendarEvents); // Debug
            window.calendar.removeAllEvents();
            window.calendar.addEventSource(calendarEvents);
        });
    }
});