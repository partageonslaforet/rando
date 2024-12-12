import { MapPin, Calendar, Clock, Users } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import type { Event } from '../types/event';
import { useState } from 'react';

interface EventCardProps {
  event: Event;
}

const defaultImage = 'https://images.unsplash.com/photo-1551632811-561732d1e306';

export function EventCard({ event }: EventCardProps) {
  const navigate = useNavigate();
  const [imageError, setImageError] = useState(false);

  // Formater la date pour l'affichage
  const formatDate = (dateStr: string) => {
    try {
      const date = new Date(dateStr);
      if (isNaN(date.getTime())) {
        console.warn('Invalid date format:', dateStr);
        return 'Date non disponible';
      }
      return new Intl.DateTimeFormat('fr-FR', { 
        day: 'numeric',
        month: 'long',
        year: 'numeric'
      }).format(date);
    } catch (error) {
      console.error('Error formatting date:', error);
      return 'Date non disponible';
    }
  };

  // Formater l'heure pour l'affichage
  const formatTime = (timeStr: string) => {
    try {
      const [hours, minutes] = timeStr.split(':');
      return `${hours}h${minutes}`;
    } catch (error) {
      console.error('Error formatting time:', error);
      return 'Heure non disponible';
    }
  };

  // Déterminer si l'événement est passé
  const isPastEvent = () => {
    try {
      const eventDate = new Date(event.date);
      const now = new Date();
      return !isNaN(eventDate.getTime()) && eventDate < now;
    } catch (error) {
      console.warn('Error checking past event:', error);
      return false;
    }
  };

  // Vérifier si l'événement a toutes les propriétés requises
  if (!event?.id || !event?.title || !event?.date) {
    console.error('Event missing required properties:', event);
    return null;
  }

  const handleCardClick = (e: React.MouseEvent) => {
    e.preventDefault();
    try {
      navigate(`/event/${event.id}`);
    } catch (error) {
      console.error('Error navigating to event:', error);
    }
  };

  return (
    <div 
      onClick={handleCardClick}
      className={`
        bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-lg 
        transition-all duration-300 hover:shadow-xl hover:scale-102
        cursor-pointer relative group
        ${isPastEvent() ? 'opacity-75' : ''}
      `}
    >
      {/* Image de couverture avec overlay au hover */}
      <div className="relative h-48">
        <img 
          src={imageError ? defaultImage : (event.images?.[0] || defaultImage)}
          alt={event.title}
          className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
          onError={() => setImageError(true)}
          loading="lazy"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
        
        {/* Badge de catégorie */}
        <div className="absolute top-4 right-4">
          <div className="px-3 py-1 text-sm font-medium rounded-full shadow-md bg-primary text-white">
            {event.category === 'running' ? 'Course à pied' :
             event.category === 'hiking' ? 'Randonnée' :
             event.category === 'cycling' ? 'Vélo' : 'Autre'}
          </div>
        </div>

        {isPastEvent() && (
          <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <span className="text-white text-lg font-semibold">Événement passé</span>
          </div>
        )}
      </div>

      {/* Contenu de la carte */}
      <div className="p-4 space-y-4">
        {/* Titre */}
        <h3 className="font-bold text-lg line-clamp-2 text-secondary">
          {event.title}
        </h3>

        {/* Informations avec icônes */}
        <div className="space-y-2">
          <div className="flex items-center space-x-2 text-sm">
            <Calendar className="w-4 h-4 text-primary" />
            <span className="text-gray-600 dark:text-gray-300">{formatDate(event.date)}</span>
          </div>
          
          <div className="flex items-center space-x-2 text-sm">
            <Clock className="w-4 h-4 text-primary" />
            <span className="text-gray-600 dark:text-gray-300">{formatTime(event.startTime)}</span>
          </div>

          <div className="flex items-center space-x-2 text-sm">
            <MapPin className="w-4 h-4 text-primary" />
            <span className="text-gray-600 dark:text-gray-300 line-clamp-1">{event.address}</span>
          </div>

          {event.maxParticipants && (
            <div className="flex items-center space-x-2 text-sm">
              <Users className="w-4 h-4 text-primary" />
              <span className="text-gray-600 dark:text-gray-300">
                {event.currentParticipants}/{event.maxParticipants} participants
              </span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}