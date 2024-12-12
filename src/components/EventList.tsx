import { Link } from 'react-router-dom';
import { Card } from './ui/card';
import { Button } from './ui/button';
import { Pencil, Trash2 } from 'lucide-react';
import type { Event } from '../types/event';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';

interface EventListProps {
  events: Event[];
  onEdit?: (eventId: number) => void;
  onDelete?: (eventId: number) => void;
  isLoading?: boolean;
}

export function EventList({ events, onEdit, onDelete, isLoading = false }: EventListProps) {
  if (isLoading) {
    return (
      <div className="space-y-4">
        {[1, 2, 3].map((i) => (
          <Card key={i} className="p-4 animate-pulse">
            <div className="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
            <div className="h-4 bg-gray-200 rounded w-1/2"></div>
          </Card>
        ))}
      </div>
    );
  }

  if (!events.length) {
    return (
      <div className="text-center py-8">
        <p className="text-gray-500 dark:text-gray-400">
          Vous n'avez pas encore créé d'événements.
        </p>
        <Link
          to="/components/ui/CreateEvent"
          className="inline-flex items-center px-4 py-2 mt-4 text-sm font-medium text-white bg-secondary rounded-md hover:bg-secondary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary"
        >
          Créer mon premier événement
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {events.map((event) => (
        <Card key={event.id} className="p-4">
          <div className="flex items-start justify-between">
            <div>
              <h3 className="text-lg font-semibold">{event.title}</h3>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                {format(new Date(event.date), 'PPP', { locale: fr })}
              </p>
              <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {event.location}
              </p>
              <p className="text-sm mt-2">{event.description}</p>
            </div>
            <div className="flex space-x-2">
              {onEdit && (
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => onEdit(event.id)}
                  title="Modifier l'événement"
                >
                  <Pencil className="h-4 w-4" />
                </Button>
              )}
              {onDelete && (
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => {
                    if (window.confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')) {
                      onDelete(event.id);
                    }
                  }}
                  title="Supprimer l'événement"
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              )}
            </div>
          </div>
        </Card>
      ))}
    </div>
  );
}
