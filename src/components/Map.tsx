import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { useEffect } from 'react';

// Fix for default marker icons in React-Leaflet
delete (L.Icon.Default.prototype as any)._getIconUrl;

// Importer directement les images des marqueurs
import markerIconUrl from '../assets/marker-secondary.svg';
import type { Event } from '../types/event';

interface MarkerData {
  position: [number, number];
  title?: string;
  description?: string;
}

// Composant pour ajuster la vue de la carte
function MapBounds({ markers }: { markers: MarkerData[] }) {
  const map = useMap();

  useEffect(() => {
    if (markers && markers.length > 0) {
      const bounds = L.latLngBounds(markers.map(marker => marker.position));
      map.flyToBounds(bounds, { 
        padding: [50, 50],
        duration: 0.5
      });
    }
  }, [markers, map]);

  return null;
}

interface MapProps {
  center?: [number, number];
  zoom?: number;
  events?: Event[];
}

export function Map({ 
  center = [50.8503, 4.3517], // Default to Brussels
  zoom = 13,
  events = []
}: MapProps) {
  const customIcon = L.icon({
    iconUrl: markerIconUrl,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
  });

  // Créer les marqueurs à partir des événements
  const markers: MarkerData[] = events.map(event => ({
    position: event.location,
    title: event.title,
    description: event.description
  }));

  return (
    <div className="w-full h-full">
      <div className="rounded-lg overflow-hidden">
        <MapContainer 
          center={center} 
          zoom={zoom} 
          scrollWheelZoom={false}
          style={{ height: '400px', width: '100%' }}
        >
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          {markers.map((marker: MarkerData, index) => (
            <Marker
              key={index}
              position={marker.position}
              icon={customIcon}
            >
              {(marker.title || marker.description) && (
                <Popup>
                  <div className="p-2">
                    <h3 className="font-medium text-gray-900">{marker.title}</h3>
                    {marker.description && (
                      <p className="mt-1 text-sm text-gray-600">{marker.description}</p>
                    )}
                  </div>
                </Popup>
              )}
            </Marker>
          ))}
          <MapBounds markers={markers} />
        </MapContainer>
      </div>
    </div>
  );
}
