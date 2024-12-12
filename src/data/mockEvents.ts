import type { Event } from '../types/event';

export const mockEvents: Event[] = [
  {
    id: 1,
    title: 'Trail des Fagnes',
    description: 'Course nature dans les Hautes Fagnes. Départ du Signal de Botrange. Trois parcours au choix selon votre niveau.',
    images: [],
    date: '2024-12-15',
    startTime: '09:00',
    endTime: '14:00',
    address: 'Route de Botrange 133, 4950 Waimes',
    location: [50.5274, 6.0583],
    coordinates: [50.5274, 6.0583],
    category: 'running',
    maxParticipants: 200,
    currentParticipants: 150,
    price: 15,
    organizer: {
      name: 'Club des Fagnes',
      address: 'Signal de Botrange, 4950 Waimes',
      website: 'https://clubdesfagnes.be'
    },
    contact: {
      name: 'Jean Dupont',
      phone: '+32 123 45 67 89',
      email: 'contact@clubdesfagnes.be',
      role: 'Organisateur'
    },
    routes: [
      {
        name: 'Parcours Découverte',
        distance: 12,
        elevation: 350,
        price: 15
      }
    ],
    status: 'upcoming',
    createdAt: '2023-12-01T10:00:00Z',
    updatedAt: '2023-12-01T10:00:00Z'
  },
  {
    id: 2,
    title: 'Randonnée Vallée de la Semois',
    description: 'Randonnée hivernale le long de la Semois. Passage par Bouillon et son château. Deux parcours disponibles.',
    images: [],
    date: '2024-12-15',
    startTime: '09:00',
    endTime: '16:00',
    address: 'Place Ducale 1, 6830 Bouillon',
    location: [49.7945, 5.0667],
    coordinates: [49.7945, 5.0667],
    category: 'hiking',
    maxParticipants: 100,
    currentParticipants: 45,
    price: 5,
    organizer: {
      name: 'Olivier Conrard',
      address: 'Rue de la Forêt 1, 1000 Bruxelles',
      website: 'https://partageonslaforet.be'
    },
    contact: {
      name: 'Olivier Conrard',
      phone: '+32 123 45 67 89',
      email: 'oconrard@gmail.com',
      role: 'Guide'
    },
    routes: [
      {
        name: 'Balade Familiale',
        distance: 8,
        elevation: 200,
        price: 5
      },
      {
        name: 'Grand Tour de la Semois',
        distance: 20,
        elevation: 550,
        price: 8
      }
    ],
    status: 'upcoming',
    createdAt: '2023-12-05T14:30:00Z',
    updatedAt: '2023-12-05T14:30:00Z'
  },
  {
    id: 3,
    title: 'Cyclisme en Ardennes',
    description: 'Circuit vélo dans les Ardennes belges. Départ de La Roche-en-Ardenne. Trois parcours pour tous les niveaux.',
    images: [],
    date: '2024-12-29',
    startTime: '08:30',
    endTime: '17:00',
    address: 'Place du Marché 1, 6980 La Roche-en-Ardenne',
    location: [50.1833, 5.5753],
    coordinates: [50.1833, 5.5753],
    category: 'cycling',
    maxParticipants: 200,
    currentParticipants: 85,
    price: 10,
    organizer: {
      name: 'Running Club Ardennes',
      address: 'Place du Marché 5, 6980 La Roche-en-Ardenne',
      website: 'https://rcardennes.be'
    },
    contact: {
      name: 'Pierre Martin',
      phone: '+32 456 78 90 12',
      email: 'info@rcardennes.be',
      role: 'Président'
    },
    routes: [
      {
        name: 'Parcours Découverte',
        distance: 30,
        elevation: 400,
        price: 10
      },
      {
        name: 'Parcours Sportif',
        distance: 60,
        elevation: 1000,
        price: 15
      },
      {
        name: 'Parcours Expert',
        distance: 100,
        elevation: 2000,
        price: 20
      }
    ],
    status: 'upcoming',
    createdAt: '2023-12-10T09:15:00Z',
    updatedAt: '2023-12-10T09:15:00Z'
  },
  {
    id: 4,
    title: 'Trail de la Citadelle - Namur',
    description: 'Trail urbain autour de la Citadelle de Namur avec vue sur la Meuse. Parcours techniques avec escaliers et sentiers historiques.',
    images: [],
    date: '2025-01-20',
    startTime: '10:00',
    endTime: '15:00',
    address: 'Route Merveilleuse 64, 5000 Namur',
    location: [50.4633, 4.8667],
    coordinates: [50.4633, 4.8667],
    category: 'running',
    maxParticipants: 250,
    currentParticipants: 120,
    price: 8,
    organizer: {
      name: 'Olivier Conrard',
      address: 'Rue de la Forêt 1, 1000 Bruxelles',
      website: 'https://partageonslaforet.be'
    },
    contact: {
      name: 'Olivier Conrard',
      phone: '+32 123 45 67 89',
      email: 'oconrard@gmail.com',
      role: 'Organisateur'
    },
    routes: [
      {
        name: 'Découverte Citadelle',
        distance: 8,
        elevation: 250,
        price: 8
      },
      {
        name: 'Tour des Remparts',
        distance: 15,
        elevation: 500,
        price: 12
      }
    ],
    status: 'upcoming',
    createdAt: '2023-12-15T11:30:00Z',
    updatedAt: '2023-12-15T11:30:00Z'
  },
  {
    id: 5,
    title: 'Randonnée Forêt de Soignes',
    description: 'Balade printanière en Forêt de Soignes. Découverte de la faune et de la flore avec un guide nature.',
    images: [],
    date: '2025-02-02',
    startTime: '09:30',
    endTime: '16:30',
    address: 'Chaussée de La Hulpe 53, 1180 Uccle',
    location: [50.7950, 4.4077],
    coordinates: [50.7950, 4.4077],
    category: 'hiking',
    maxParticipants: 60,
    currentParticipants: 25,
    price: 5,
    organizer: {
      name: 'Nature & Découvertes ASBL',
      address: 'Avenue de la Forêt 10, 1180 Uccle',
      website: 'https://nature-decouvertes.be'
    },
    contact: {
      name: 'Sophie Lambert',
      phone: '+32 345 67 89 01',
      email: 'info@nature-decouvertes.be',
      role: 'Guide Nature'
    },
    routes: [
      {
        name: 'Sentier Nature',
        distance: 6,
        elevation: 100,
        price: 5
      },
      {
        name: 'Circuit des Étangs',
        distance: 12,
        elevation: 200,
        price: 5
      },
      {
        name: 'Grande Traversée',
        distance: 20,
        elevation: 350,
        price: 5
      }
    ],
    status: 'upcoming',
    createdAt: '2023-12-20T15:45:00Z',
    updatedAt: '2023-12-20T15:45:00Z'
  },
  {
    id: 6,
    title: 'Tour des Flandres Amateur',
    description: 'Version amateur du célèbre Tour des Flandres. Pavés et monts mythiques au programme.',
    images: [],
    date: '2025-02-15',
    startTime: '07:30',
    endTime: '18:00',
    address: 'Markt 1, 9700 Oudenaarde',
    location: [50.8483, 3.6003],
    coordinates: [50.8483, 3.6003],
    category: 'cycling',
    maxParticipants: 2000,
    currentParticipants: 1250,
    price: 25,
    organizer: {
      name: 'Olivier Conrard',
      address: 'Rue de la Forêt 1, 1000 Bruxelles',
      website: 'https://partageonslaforet.be'
    },
    contact: {
      name: 'Olivier Conrard',
      phone: '+32 123 45 67 89',
      email: 'oconrard@gmail.com',
      role: 'Organisateur'
    },
    routes: [
      {
        name: 'Découverte des Monts',
        distance: 75,
        elevation: 800,
        price: 25
      },
      {
        name: 'Parcours Classic',
        distance: 140,
        elevation: 1600,
        price: 35
      },
      {
        name: 'Parcours Pro',
        distance: 180,
        elevation: 2500,
        price: 45
      }
    ],
    status: 'upcoming',
    createdAt: '2023-12-25T08:20:00Z',
    updatedAt: '2023-12-25T08:20:00Z'
  },
  {
    id: 7,
    title: 'Balade nocturne - Forêt de Soignes',
    description: 'Découvrez la magie de la forêt de Soignes de nuit. Balade guidée avec observation de la faune nocturne.',
    images: [],
    date: '2024-12-20',
    startTime: '20:00',
    endTime: '22:30',
    address: 'Drève de Lorraine, 1180 Uccle',
    location: [50.7879, 4.4033],
    coordinates: [50.7879, 4.4033],
    category: 'hiking',
    maxParticipants: 30,
    currentParticipants: 12,
    price: 10,
    organizer: {
      name: 'Nature & Découvertes ASBL',
      address: 'Avenue de la Forêt 10, 1180 Uccle',
      website: 'https://nature-decouvertes.be'
    },
    contact: {
      name: 'Sophie Lambert',
      phone: '+32 456 78 90 12',
      email: 'contact@nature-decouvertes.be',
      role: 'Guide Nature'
    },
    routes: [
      {
        name: 'Parcours Nocturne',
        distance: 5,
        elevation: 100,
        price: 10
      }
    ],
    status: 'upcoming',
    createdAt: '2023-12-30T16:10:00Z',
    updatedAt: '2023-12-30T16:10:00Z'
  }
];
