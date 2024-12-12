export interface FilterState {
  timeFilter: 'upcoming' | 'today' | 'past';
  categories: string[];
  proximity: {
    enabled: boolean;
    radius: number;
    userLocation: [number, number] | null;
  };
}
