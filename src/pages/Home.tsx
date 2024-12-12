import { useState, useMemo, useEffect } from 'react';
import type { Event } from '../types/event';
import { Hero, Map, Filters, EventCard, Calendar, Pagination } from '../components';
import { FilterState } from '../types/filters';
import { Section } from '../layouts/MainLayout';

interface HomeProps {
  events?: Event[];
}

export function Home({ events = [] }: HomeProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [filters, setFilters] = useState<FilterState>({
    timeFilter: 'upcoming',
    categories: [],
    proximity: {
      enabled: false,
      radius: 10,
      userLocation: null,
    },
  });
  const [sortOption] = useState<'date-asc' | 'date-desc' | 'title-asc' | 'title-desc'>('date-asc');
  const [currentPage, setCurrentPage] = useState(1);
  const eventsPerPage = 6;

  const filteredEvents = useMemo(() => {
    let filtered = events;

    // Filtre par recherche
    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(event => 
        event.title.toLowerCase().includes(query) ||
        event.description.toLowerCase().includes(query) ||
        event.address.toLowerCase().includes(query)
      );
    }

    // Filtre temporel
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    filtered = filtered.filter(event => {
      const eventDate = new Date(event.date);
      switch (filters.timeFilter) {
        case 'upcoming':
          return eventDate >= today;
        case 'today':
          return eventDate >= today && eventDate < tomorrow;
        case 'past':
          return eventDate < today;
        default:
          return true;
      }
    });

    // Filtre par catégories
    if (filters.categories.length > 0) {
      filtered = filtered.filter(event => 
        filters.categories.includes(event.category)
      );
    }

    // Filtre par proximité
    if (filters.proximity.enabled && filters.proximity.userLocation) {
      const [userLat, userLng] = filters.proximity.userLocation;
      const radius = filters.proximity.radius;

      filtered = filtered.filter(event => {
        const distance = getDistanceFromLatLonInKm(
          userLat,
          userLng,
          event.location[0],
          event.location[1]
        );
        return distance <= radius;
      });
    }

    // Tri des résultats
    filtered.sort((a, b) => {
      switch (sortOption) {
        case 'date-asc':
          return new Date(a.date).getTime() - new Date(b.date).getTime();
        case 'date-desc':
          return new Date(b.date).getTime() - new Date(a.date).getTime();
        case 'title-asc':
          return a.title.localeCompare(b.title);
        case 'title-desc':
          return b.title.localeCompare(a.title);
        default:
          return 0;
      }
    });

    return filtered;
  }, [events, searchQuery, filters, sortOption]);

  // Pagination des événements
  const paginatedEvents = useMemo(() => {
    const startIndex = (currentPage - 1) * eventsPerPage;
    const endIndex = startIndex + eventsPerPage;
    return filteredEvents.slice(startIndex, endIndex);
  }, [filteredEvents, currentPage]);

  const totalPages = Math.ceil(filteredEvents.length / eventsPerPage);

  // Reset la pagination quand les filtres changent
  useEffect(() => {
    setCurrentPage(1);
  }, [filters, searchQuery, sortOption]);

  // Fonction utilitaire pour calculer la distance entre deux points
  function getDistanceFromLatLonInKm(lat1: number, lon1: number, lat2: number, lon2: number) {
    const R = 6371; // Rayon de la Terre en km
    const dLat = deg2rad(lat2 - lat1);
    const dLon = deg2rad(lon2 - lon1);
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
      Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  function deg2rad(deg: number) {
    return deg * (Math.PI / 180);
  }

  // Calculer le nombre d'événements par catégorie
  const eventCountByCategory = useMemo(() => {
    const counts: Record<string, number> = {};
    events.forEach(event => {
      if (event.category) {
        counts[event.category] = (counts[event.category] || 0) + 1;
      }
    });
    return counts;
  }, [events]);

  return (
    <div className="min-h-screen flex flex-col">
      <Hero searchQuery={searchQuery} onSearchChange={setSearchQuery} />
      <div className="flex-grow bg-gray-50 dark:bg-gray-900">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="space-y-6">
            {/* Filtres et Calendrier */}
            <div className="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6">
              <Section className="bg-white p-4 rounded-lg">
                <Filters 
                  filters={filters}
                  onFilterChange={(newFilters: FilterState) => setFilters(newFilters)}
                  onSearchReset={() => setSearchQuery('')}
                  totalEvents={filteredEvents.length}
                  eventCountByCategory={eventCountByCategory}
                />
              </Section>

              <Section className="bg-white p-4 rounded-lg">
                <Calendar events={filteredEvents} />
              </Section>
            </div>

            {/* Carte */}
            <Section className="bg-white p-4 rounded-lg">
              <div className="w-full h-[400px]">
                <Map events={paginatedEvents} />
              </div>
            </Section>

            {/* Liste des événements */}
            <Section className="bg-white p-4 rounded-lg">
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {paginatedEvents.map(event => (
                  <EventCard key={event.id} event={event} />
                ))}
              </div>
              {paginatedEvents.length === 0 && (
                <div className="text-center py-8">
                  <p className="text-gray-500">Aucun événement trouvé</p>
                </div>
              )}
            </Section>

            {filteredEvents.length > eventsPerPage && (
              <div className="flex justify-center">
                <Pagination
                  currentPage={currentPage}
                  totalPages={totalPages}
                  onPageChange={setCurrentPage}
                />
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}