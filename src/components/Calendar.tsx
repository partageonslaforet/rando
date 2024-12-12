import { useState } from 'react';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, isSameMonth, addMonths, subMonths, isToday as isDateToday } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Event } from '../types/event';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface CalendarProps {
  events: Event[];
}

export function Calendar({ events }: CalendarProps) {
  const [currentDate, setCurrentDate] = useState(new Date());
  const monthStart = startOfMonth(currentDate);
  const monthEnd = endOfMonth(currentDate);
  const monthDays = eachDayOfInterval({ start: monthStart, end: monthEnd });

  // Calculer le nombre d'événements par jour
  const eventCountByDay: Record<string, number> = {};
  events.forEach(event => {
    const dateKey = format(new Date(event.date), 'yyyy-MM-dd');
    eventCountByDay[dateKey] = (eventCountByDay[dateKey] || 0) + 1;
  });

  const handleMonthChange = (direction: number) => {
    setCurrentDate(prevDate => direction === -1 ? subMonths(prevDate, 1) : addMonths(prevDate, 1));
  };

  return (
    <div className="rounded-2xl shadow-sm">
      <div className="flex items-center justify-between border-b dark:border-gray-700 pb-3">
        <button
          onClick={() => handleMonthChange(-1)}
          type="button"
          className="-my-1.5 flex flex-none items-center justify-center p-1.5 text-gray-400 hover:text-gray-500"
          aria-label="Mois précédent"
        >
          <ChevronLeft size={20} className="w-5 h-5" />
        </button>
        <h2 className="text-xl font-semibold text-gray-900 dark:text-white capitalize">
          {format(currentDate, 'MMMM yyyy', { locale: fr })}
        </h2>
        <button
          onClick={() => handleMonthChange(1)}
          type="button"
          className="-my-1.5 -mr-1.5 flex flex-none items-center justify-center p-1.5 text-gray-400 hover:text-gray-500"
          aria-label="Mois suivant"
        >
          <ChevronRight size={20} className="w-5 h-5" />
        </button>
      </div>

      <div className="grid grid-cols-7 gap-px text-xs leading-6 text-center text-gray-500 dark:text-gray-400 border-b dark:border-gray-700">
        {['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((day, i) => (
          <div key={i} className="py-2 font-semibold">
            {day}
          </div>
        ))}
      </div>
      <div className="grid grid-cols-7 text-sm">
        {monthDays.map((day) => {
          const dateKey = format(day, 'yyyy-MM-dd');
          const hasEvents = eventCountByDay[dateKey] > 0;
          const eventCount = eventCountByDay[dateKey] || 0;
          const isCurrentDay = isDateToday(day);
          const isInCurrentMonth = isSameMonth(day, currentDate);

          return (
            <div
              key={day.toString()}
              className={`relative h-14 hover:bg-gray-100 dark:hover:bg-gray-700 focus:z-10 ${
                hasEvents ? 'bg-secondary rounded-lg' : ''
              } ${!isInCurrentMonth ? 'bg-gray-50 dark:bg-gray-800/50' : ''}`}
            >
              {hasEvents && (
                <div className="absolute top-1 right-1">
                  <div className="h-4 w-4 rounded-full bg-white text-xs text-secondary flex items-center justify-center">
                    {eventCount}
                  </div>
                </div>
              )}
              <time
                dateTime={format(day, 'yyyy-MM-dd')}
                className={`absolute bottom-1 left-1 flex h-6 w-6 items-center justify-center rounded-full text-sm ${
                  isCurrentDay
                    ? 'bg-primary text-white'
                    : isInCurrentMonth
                    ? hasEvents
                      ? 'text-white'
                      : 'text-gray-900 dark:text-white'
                    : 'text-gray-400 dark:text-gray-500'
                }`}
              >
                {format(day, 'd')}
              </time>
            </div>
          );
        })}
      </div>
    </div>
  );
}