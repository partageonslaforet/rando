import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Calendar, MapPin } from 'lucide-react';
import type { Event } from '../../types/event';

export function RecentEvents() {
  const { data: events = [] } = useQuery({
    queryKey: ['admin', 'recent-events'],
    queryFn: async () => {
      // Fetch recent events from API
      return [] as Event[];
    },
  });

  return (
    <div className="space-y-4">
      {events.map((event) => (
        <div 
          key={event.id}
          className="flex items-start gap-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg"
        >
          <img
            src={event.images[0]}
            alt={event.title}
            className="w-16 h-16 rounded-lg object-cover"
          />
          <div className="flex-1 min-w-0">
            <h3 className="font-medium dark:text-white truncate">
              {event.title}
            </h3>
            <div className="mt-1 flex flex-col gap-1">
              <p className="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-1">
                <Calendar size={14} />
                <span>{event.date}</span>
              </p>
              <p className="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-1">
                <MapPin size={14} />
                <span>{event.address}</span>
              </p>
            </div>
          </div>
          <span className={`px-2 py-1 text-xs rounded-full ${
            event.category === 'running'
              ? 'bg-blue-100 text-blue-800'
              : event.category === 'hiking'
              ? 'bg-green-100 text-green-800'
              : 'bg-orange-100 text-orange-800'
          }`}>
            {event.category}
          </span>
        </div>
      ))}
    </div>
  );
}