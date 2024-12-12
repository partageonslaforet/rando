import { useApi } from '../contexts/ApiContext';

export interface Event {
  id: number;
  title: string;
  description: string;
  date: string;
  startTime: string;
  endTime: string;
  address: string;
  location: [number, number];
  category: string;
  maxParticipants: number;
  currentParticipants: number;
  price: number;
  images: string[];
  organizer: {
    name: string;
    address: string;
    website: string;
  };
  status: string;
  createdAt: string;
  updatedAt: string;
}

interface ApiResponse<T> {
  status: string;
  data: T;
  message?: string;
}

export const useEvents = () => {
  const { api } = useApi();

  const getEvents = async (): Promise<Event[]> => {
    if (!api) {
      throw new Error('API non initialisée');
    }
    try {
      const response = await api.get<ApiResponse<Event[]>>('/events');
      return response.data.data;
    } catch (error) {
      console.error('Erreur lors de la récupération des événements:', error);
      throw error;
    }
  };

  const getEvent = async (id: number): Promise<Event> => {
    if (!api) {
      throw new Error('API non initialisée');
    }
    try {
      const response = await api.get<ApiResponse<Event>>(`/events/${id}`);
      return response.data.data;
    } catch (error) {
      console.error(`Erreur lors de la récupération de l'événement ${id}:`, error);
      throw error;
    }
  };

  const createEvent = async (eventData: Omit<Event, 'id'>): Promise<Event> => {
    if (!api) {
      throw new Error('API non initialisée');
    }
    try {
      const response = await api.post<ApiResponse<Event>>('/events', eventData);
      return response.data.data;
    } catch (error) {
      console.error('Erreur lors de la création de l\'événement:', error);
      throw error;
    }
  };

  const updateEvent = async (id: number, eventData: Partial<Event>): Promise<Event> => {
    if (!api) {
      throw new Error('API non initialisée');
    }
    try {
      const response = await api.put<ApiResponse<Event>>(`/events/${id}`, eventData);
      return response.data.data;
    } catch (error) {
      console.error(`Erreur lors de la mise à jour de l'événement ${id}:`, error);
      throw error;
    }
  };

  const deleteEvent = async (id: number): Promise<void> => {
    if (!api) {
      throw new Error('API non initialisée');
    }
    try {
      await api.delete(`/events/${id}`);
    } catch (error) {
      console.error(`Erreur lors de la suppression de l'événement ${id}:`, error);
      throw error;
    }
  };

  const joinEvent = async (id: number): Promise<Event> => {
    if (!api) {
      throw new Error('API non initialisée');
    }
    try {
      const response = await api.post<ApiResponse<Event>>(`/events/${id}/join`);
      return response.data.data;
    } catch (error) {
      console.error(`Erreur lors de l'inscription à l'événement ${id}:`, error);
      throw error;
    }
  };

  const leaveEvent = async (id: number): Promise<Event> => {
    if (!api) {
      throw new Error('API non initialisée');
    }
    try {
      const response = await api.post<ApiResponse<Event>>(`/events/${id}/leave`);
      return response.data.data;
    } catch (error) {
      console.error(`Erreur lors de la désinscription de l'événement ${id}:`, error);
      throw error;
    }
  };

  return {
    getEvents,
    getEvent,
    createEvent,
    updateEvent,
    deleteEvent,
    joinEvent,
    leaveEvent
  };
};
