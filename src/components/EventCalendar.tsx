import { Calendar, dateFnsLocalizer } from 'react-big-calendar';
import { format, parse, startOfWeek, getDay, startOfDay, endOfDay, addDays } from 'date-fns';
import { fr } from 'date-fns/locale';
import { useEffect } from 'react';
import type { Event } from '../types/event';

const locales = {
  'fr': fr,
};

const localizer = dateFnsLocalizer({
  format,
  parse,
  startOfWeek: () => startOfWeek(new Date(), { locale: fr }),
  getDay,
  locales,
});

interface CalendarEvent {
  title: string;
  start: Date;
  end: Date;
  resource?: any;
  event?: Event;
  allDay?: boolean;
}

interface EventCalendarProps {
  events: Event[];
  category: string;
  onSelectDate: (date: Date | null) => void;
  selectedDate: Date | null;
}

function CustomToolbar({ onNavigate, label }: any) {
  return (
    <div className="rbc-toolbar">
      <div className="rbc-btn-group">
        <button type="button" onClick={() => onNavigate('TODAY')}>
          Aujourd'hui
        </button>
        <button type="button" onClick={() => onNavigate('PREV')}>
          Mois précédent
        </button>
        <button type="button" onClick={() => onNavigate('NEXT')}>
          Mois suivant
        </button>
      </div>
      <span className="rbc-toolbar-label">{label}</span>
    </div>
  );
}

export function EventCalendar({ events, category, onSelectDate, selectedDate }: EventCalendarProps) {
  // Normaliser les dates des événements une seule fois
  const normalizedEventDates = events.map(event => {
    const eventDate = startOfDay(new Date(event.date));
    return format(eventDate, 'yyyy-MM-dd');
  });

  // Fonction de vérification des événements avec normalisation des dates
  const hasEventOnDate = (date: Date) => {
    const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    const dateToCheck = format(startOfDay(localDate), 'yyyy-MM-dd');
    return normalizedEventDates.includes(dateToCheck);
  };

  // Créer les événements du calendrier avec regroupement par date
  const calendarEvents: CalendarEvent[] = events.map(event => {
    const eventDate = startOfDay(new Date(event.date));
    
    return {
      title: '1',
      start: eventDate,
      end: endOfDay(eventDate),
      event,
      allDay: true
    };
  });

  const handleSelectSlot = ({ start }: { start: Date }) => {
    const dateStr = format(start, 'yyyy-MM-dd');
    const eventsOnDate = events.filter(event => {
      const eventDate = new Date(event.date);
      return format(eventDate, 'yyyy-MM-dd') === dateStr;
    });

    if (eventsOnDate.length > 0) {
      onSelectDate(start);
    }
  };

  const handleSelectEvent = (event: CalendarEvent) => {
    // Utiliser la date stockée dans la resource
    const eventDate = new Date(event.start);
    onSelectDate(eventDate);
  };

  // Log uniquement en développement
  useEffect(() => {
    // Commenté temporairement pour réduire les logs
    /*
    if (import.meta.env.DEV) {
      console.log('Calendar events:', {
        total: events.length,
        normalized: normalizedEventDates
      });
    }
    */
  }, [events, calendarEvents]);

  return (
    <div className="h-full flex flex-col">
      <div className="rbc-calendar-container w-full">
        <Calendar<CalendarEvent>
          localizer={localizer}
          events={calendarEvents}
          startAccessor="start"
          endAccessor="end"
          views={['month']}
          defaultView="month"
          selectable
          onSelectSlot={(slotInfo) => {
            handleSelectSlot(slotInfo);
          }}
          onSelectEvent={(event) => {
            handleSelectEvent(event);
          }}
          messages={{
            previous: "Mois précédent",
            today: "Aujourd'hui",
            next: "Mois suivant",
            month: "Mois",
            date: "Date",
            event: "Événement"
          }}
          formats={{
            monthHeaderFormat: (date: Date) => format(date, 'MMMM yyyy', { locale: fr }),
            weekdayFormat: (date: Date) => format(date, 'EEEEEE', { locale: fr }).toUpperCase(),
            dayFormat: (date: Date) => format(date, 'd', { locale: fr }),
          }}
          dayPropGetter={(date: Date) => {
            const hasEvent = hasEventOnDate(date);
            const isToday = date.toDateString() === new Date().toDateString();
            
            return {
              className: `${isToday ? 'rbc-today' : ''} ${hasEvent ? 'has-event' : ''}`.trim(),
              style: {
                backgroundColor: hasEvent ? 'var(--color-secondary)' : 'transparent',
                borderRadius: hasEvent ? '8px' : undefined,
                margin: hasEvent ? '0.125rem' : undefined,
                transition: 'all 0.2s ease'
              }
            };
          }}
          eventPropGetter={(event: CalendarEvent) => ({
            className: 'rbc-event-content',
            style: {
              backgroundColor: 'white',
              color: 'var(--color-secondary)',
              border: 'none',
              borderRadius: '50%',
              width: '20px',
              height: '20px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              position: 'absolute',
              right: '5px',
              bottom: '5px',
              fontSize: '0.75rem',
              fontWeight: '600',
              boxShadow: '0 1px 2px rgba(0, 0, 0, 0.1)',
              pointerEvents: 'auto',
              zIndex: 2
            }
          })}
        />
      </div>
    </div>
  );
}