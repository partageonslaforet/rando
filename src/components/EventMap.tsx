import { useEffect } from 'react';
import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet';
import L, { Icon, LatLngExpression } from 'leaflet';
import type { Event } from '../types/event';
import 'leaflet/dist/leaflet.css';

// Create custom icons for each category
const createCustomIcon = (color: string) => new Icon({
  iconUrl: `data:image/svg+xml;base64,${btoa(`
    <svg width="25" height="41" viewBox="0 0 25 41" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M25 12.5C25 19.4036 12.5 41 12.5 41C12.5 41 0 19.4036 0 12.5C0 5.59644 5.59644 0 12.5 0C19.4036 0 25 5.59644 25 12.5Z" fill="${color}"/>
      <circle cx="12.5" cy="12.5" r="8.5" fill="white"/>
    </svg>
  `)}`,
  iconSize: [25, 41],
  iconAnchor: [12.5, 41],
  popupAnchor: [0, -41],
});

const categoryIcons = {
  running: createCustomIcon('#990047'),
  hiking: createCustomIcon('#2E7D32'),
  cycling: createCustomIcon('#1565C0'),
  default: createCustomIcon('#757575')
};

function MapController({ events }: { events: Event[] }) {
  const map = useMap();

  useEffect(() => {
    if (!map) {
      console.log('Map not ready');
      return;
    }

    if (!Array.isArray(events) || events.length === 0) {
      // Si pas d'événements, centrer sur la Belgique
      map.setView([50.5039, 4.4699], 8);
      return;
    }

    console.log('Starting map update with events:', events.length);

    // Log des coordonnées de chaque événement
    events.forEach((event, index) => {
      if (event.coordinates && Array.isArray(event.coordinates) && event.coordinates.length === 2) {
        console.log(`Event ${index} coordinates:`, event.coordinates);
      } else {
        console.warn(`Event ${index} has invalid coordinates:`, event.coordinates);
      }
    });

    // Filtrer les événements avec des coordonnées valides
    const validEvents = events.filter(e => 
      e.coordinates && 
      Array.isArray(e.coordinates) && 
      e.coordinates.length === 2 &&
      !isNaN(e.coordinates[0]) && 
      !isNaN(e.coordinates[1])
    );

    if (validEvents.length === 0) {
      map.setView([50.5039, 4.4699], 8);
      return;
    }

    // Créer les bounds avec les coordonnées extrêmes
    const lats = validEvents.map(e => e.coordinates[0]);
    const lngs = validEvents.map(e => e.coordinates[1]);
    
    const southWest = L.latLng(Math.min(...lats), Math.min(...lngs));
    const northEast = L.latLng(Math.max(...lats), Math.max(...lngs));
    const bounds = L.latLngBounds(southWest, northEast);
    const center = bounds.getCenter();

    console.log('Bounds:', bounds.toBBoxString());
    console.log('Center:', center);

    // Calculer la distance maximale entre les points en km
    const maxDistance = Math.max(
      calculateDistance(southWest.lat, southWest.lng, northEast.lat, northEast.lng),
      calculateDistance(southWest.lat, northEast.lng, northEast.lat, southWest.lng)
    );
    console.log('Max distance between points:', maxDistance, 'km');

    // Ajuster le zoom en fonction de la distance
    const maxZoom = maxDistance < 50 ? 11 : maxDistance < 100 ? 10 : 9;

    // D'abord, faire un flyTo vers le centre
    map.flyTo(center, maxZoom, {
      duration: 1.5,
      easeLinearity: 0.25
    });

    // Puis ajuster les bounds avec une animation
    setTimeout(() => {
      map.fitBounds(bounds, {
        padding: [50, 50],
        maxZoom,
        animate: true,
        duration: 0.5
      });
    }, 1600);

  }, [events, map]);

  return null;
}

// Fonction pour calculer la distance entre deux points (formule de Haversine)
function calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371; // Rayon de la Terre en kilomètres
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
    Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

export function EventMap({ events, onMarkerClick }: { events: Event[], onMarkerClick?: (eventId: number) => void }) {
  // Ne pas logger en production
  if (import.meta.env.DEV) {
    console.log('EventMap render - events:', events);
  }
  
  return (
    <div className="rounded-lg overflow-hidden">
      <MapContainer
        center={[50.5039, 4.4699]}
        zoom={8}
        style={{ height: '400px', width: '100%' }}
        className="z-0"
      >
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          maxZoom={13}
          minZoom={6}
          keepBuffer={8}
        />
        <MapController events={events} />
        {Array.isArray(events) && events.length > 0 && events.map((event) => (
          <Marker
            key={event.id}
            position={event.coordinates}
            icon={categoryIcons[event.category as keyof typeof categoryIcons] || categoryIcons.default}
            eventHandlers={{
              click: () => onMarkerClick?.(event.id),
            }}
          >
            <Popup>
              <div className="text-center">
                <h3 className="font-bold">{event.title}</h3>
                <p className="text-sm">{event.date}</p>
                <button
                  onClick={() => onMarkerClick?.(event.id)}
                  className="mt-2 px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark transition-colors"
                >
                  Voir les détails
                </button>
              </div>
            </Popup>
          </Marker>
        ))}
      </MapContainer>
    </div>
  );
}