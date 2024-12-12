export interface Route {
  name: string;
  distance: number; // en km
  elevation: number; // dénivelé en mètres
  price: number; // en euros
  gpxUrl?: string; // optionnel
}

export interface Contact {
  name: string;
  phone: string;
  email: string;
  role: string;
}

export interface Event {
  id: number;
  title: string;
  images: string[];
  date: string;
  startTime: string;
  endTime?: string; // optionnel
  description: string;
  address: string;
  location: [number, number]; // [latitude, longitude]
  category: 'running' | 'hiking' | 'cycling';
  maxParticipants: number;
  currentParticipants: number;
  price: number;
  organizer: {
    name: string;
    address: string;
    description?: string;
    website?: string;
  };
  contact: Contact;
  routes: Route[];
  facebookUrl?: string;
  status: 'upcoming' | 'ongoing' | 'completed' | 'cancelled';
  createdAt: string;
  updatedAt: string;
  coordinates: [number, number]; // [latitude, longitude]
}