import { Link } from 'react-router-dom';
import { Calendar, MapPin, Users, Edit, Trash } from 'lucide-react';

const userEvents = [
  {
    id: 1,
    title: 'Trail des Fagnes',
    date: '2024-04-15',
    location: 'Spa, Belgique',
    participants: 45,
    status: 'upcoming'
  },
  {
    id: 2,
    title: 'Randonnée des Ardennes',
    date: '2024-03-20',
    location: 'La Roche-en-Ardenne',
    participants: 28,
    status: 'past'
  }
];

export function Events() {
  return (
    <div>
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold dark:text-white">Mes Événements</h1>
        <Link
          to="/create"
          className="bg-[#990047] text-white px-4 py-2 rounded-md hover:bg-[#800039]"
        >
          Créer un événement
        </Link>
      </div>

      <div className="space-y-4">
        {userEvents.map(event => (
          <div
            key={event.id}
            className="bg-gray-50 dark:bg-gray-700 rounded-lg p-4"
          >
            <div className="flex justify-between items-start">
              <div>
                <h3 className="text-lg font-semibold dark:text-white mb-2">
                  {event.title}
                </h3>
                <div className="space-y-2">
                  <div className="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <Calendar size={16} />
                    <span>{new Date(event.date).toLocaleDateString()}</span>
                  </div>
                  <div className="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <MapPin size={16} />
                    <span>{event.location}</span>
                  </div>
                  <div className="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <Users size={16} />
                    <span>{event.participants} participants</span>
                  </div>
                </div>
              </div>

              <div className="flex gap-2">
                <button
                  className="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/10 rounded-full"
                  title="Modifier"
                >
                  <Edit size={20} />
                </button>
                <button
                  className="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/10 rounded-full"
                  title="Supprimer"
                >
                  <Trash size={20} />
                </button>
              </div>
            </div>

            <div className="mt-4 flex gap-4">
              <Link
                to={`/event/${event.id}`}
                className="text-[#990047] hover:text-[#800039]"
              >
                Voir les détails
              </Link>
              {event.status === 'upcoming' && (
                <Link
                  to={`/event/${event.id}/participants`}
                  className="text-[#990047] hover:text-[#800039]"
                >
                  Gérer les participants
                </Link>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}