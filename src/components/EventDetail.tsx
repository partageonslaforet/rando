import { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import { Calendar, Clock, MapPin, User, Phone, Mail, Globe } from 'lucide-react';
import type { Event } from '../types/event';
import { useAuth } from '../hooks/useAuth';
import { formatDate } from '../utils/date';

interface EventDetailProps {
  events: Event[];
}

export function EventDetail({ events }: EventDetailProps) {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  
  // Vérification que events est un tableau
  if (!Array.isArray(events)) {
    console.error('Events is not an array:', events);
    return (
      <div className="flex flex-col items-center justify-center min-h-screen">
        <h1 className="text-2xl font-bold mb-4">Erreur de chargement</h1>
        <button
          onClick={() => navigate('/')}
          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Retour à l'accueil
        </button>
      </div>
    );
  }

  const event = events.find(e => e.id === Number(id));
  
  useEffect(() => {
    if (!event) {
      console.warn(`Event not found for id: ${id}`);
    }
  }, [event, id]);

  if (!event) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen">
        <h1 className="text-2xl font-bold mb-4">Événement non trouvé</h1>
        <button
          onClick={() => navigate('/')}
          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Retour à l'accueil
        </button>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      {/* En-tête de l'événement */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold mb-4">{event.title}</h1>
        <p className="text-gray-600 dark:text-gray-300 mb-4">{event.description}</p>
        
        {/* Informations principales */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
          <div className="flex items-center">
            <Calendar className="w-5 h-5 mr-2" />
            <span>{formatDate(event.date)}</span>
          </div>
          <div className="flex items-center">
            <Clock className="w-5 h-5 mr-2" />
            <span>{event.startTime}</span>
          </div>
          <div className="flex items-center">
            <MapPin className="w-5 h-5 mr-2" />
            <span>{event.address}</span>
          </div>
        </div>
      </div>

      {/* Carte */}
      <div className="mb-8 h-[400px]">
        <MapContainer
          center={event.location}
          zoom={13}
          className="h-full w-full rounded-lg"
        >
          <TileLayer
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          />
          <Marker position={event.location}>
            <Popup>{event.title}</Popup>
          </Marker>
        </MapContainer>
      </div>

      {/* Parcours */}
      <div className="mb-8">
        <h2 className="text-2xl font-bold mb-4">Parcours disponibles</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {event.routes.map((route, index) => (
            <div
              key={index}
              className="p-4 border rounded-lg bg-white dark:bg-gray-800"
            >
              <h3 className="font-bold mb-2">{route.name}</h3>
              <p>Distance : {route.distance} km</p>
              <p>Prix : {route.price} €</p>
              {route.gpxUrl && (
                <a
                  href={route.gpxUrl}
                  className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                  download
                >
                  Télécharger le fichier GPX
                </a>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Organisateur */}
      <div className="mb-8">
        <h2 className="text-2xl font-bold mb-4">Organisateur</h2>
        <div className="p-4 border rounded-lg bg-white dark:bg-gray-800">
          <h3 className="font-bold mb-2">{event.organizer.name}</h3>
          <p className="mb-2">{event.organizer.description}</p>
          <p className="flex items-center mb-2">
            <MapPin className="w-4 h-4 mr-2" />
            {event.organizer.address}
          </p>
          {event.organizer.website && (
            <p className="flex items-center">
              <Globe className="w-4 h-4 mr-2" />
              <a
                href={event.organizer.website}
                target="_blank"
                rel="noopener noreferrer"
                className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
              >
                Site web
              </a>
            </p>
          )}
        </div>
      </div>

      {/* Contact */}
      <div className="mb-8">
        <h2 className="text-2xl font-bold mb-4">Contact</h2>
        <div className="p-4 border rounded-lg bg-white dark:bg-gray-800">
          <div className="flex items-center mb-2">
            <User className="w-4 h-4 mr-2" />
            <span className="font-bold">{event.contact.name}</span>
          </div>
          <p className="text-gray-600 dark:text-gray-300 mb-2">{event.contact.role}</p>
          {event.contact.phone && (
            <p className="flex items-center mb-2">
              <Phone className="w-4 h-4 mr-2" />
              <a
                href={`tel:${event.contact.phone}`}
                className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
              >
                {event.contact.phone}
              </a>
            </p>
          )}
          {event.contact.email && (
            <p className="flex items-center">
              <Mail className="w-4 h-4 mr-2" />
              <a
                href={`mailto:${event.contact.email}`}
                className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
              >
                {event.contact.email}
              </a>
            </p>
          )}
        </div>
      </div>

      {/* Boutons d'action */}
      <div className="flex justify-between">
        <button
          onClick={() => navigate('/')}
          className="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
        >
          Retour à l'accueil
        </button>
        {user && (
          <button
            onClick={() => navigate(`/event/${event.id}/edit`)}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Modifier l'événement
          </button>
        )}
      </div>
    </div>
  );
}
