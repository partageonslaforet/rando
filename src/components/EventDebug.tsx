import { useEffect } from 'react';
import type { Event } from '../types/event';

interface EventDebugProps {
  events?: Event[];
}

export function EventDebug({ events = [] }: EventDebugProps) {
  useEffect(() => {
    // Force le log après le montage du composant
    const timer = setTimeout(() => {
      console.group('🏠 Event Debug');
      console.log('Total Events:', events.length);
      console.table(events.map(e => ({
        id: e.id,
        title: e.title,
        category: e.category,
        date: e.date,
        status: e.status
      })));
      console.groupEnd();
    }, 100);

    return () => clearTimeout(timer);
  }, [events]);

  return (
    <div className="bg-yellow-100 p-4 border-4 border-yellow-500 text-black">
      <h2 className="text-lg font-bold mb-2">📊 Debug Info</h2>
      <div className="grid grid-cols-2 gap-4">
        <div>
          <h3 className="font-semibold">Environment</h3>
          <ul className="text-sm">
            <li>Mode: {import.meta.env.MODE}</li>
            <li>API: {import.meta.env.VITE_API_URL}</li>
            <li>Base: {import.meta.env.BASE_URL}</li>
          </ul>
        </div>
        <div>
          <h3 className="font-semibold">Events</h3>
          <ul className="text-sm">
            <li>Total: {events.length}</li>
            <li>Categories: {Array.from(new Set(events.map(e => e.category))).join(', ')}</li>
            <li>IDs: {events.map(e => e.id).join(', ')}</li>
          </ul>
        </div>
      </div>
    </div>
  );
}
