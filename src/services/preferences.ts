import { useApi } from '../contexts/ApiContext';

export interface UserPreferences {
  emailNotifications: boolean;
  // Ajouter d'autres préférences utilisateur ici au besoin
}

interface ApiResponse<T> {
  status: string;
  data: T;
  message?: string;
}

export const usePreferences = () => {
  const { api } = useApi();

  const getPreferences = async (): Promise<UserPreferences> => {
    if (!api) {
      throw new Error('API non initialisée');
    }
    try {
      const response = await api.get<ApiResponse<UserPreferences>>('/preferences');
      return response.data.data;
    } catch (error) {
      console.error('Erreur lors de la récupération des préférences:', error);
      throw error;
    }
  };

  const updatePreferences = async (preferences: Partial<UserPreferences>): Promise<UserPreferences> => {
    if (!api) {
      throw new Error('API non initialisée');
    }
    try {
      const response = await api.put<ApiResponse<UserPreferences>>('/preferences', preferences);
      return response.data.data;
    } catch (error) {
      console.error('Erreur lors de la mise à jour des préférences:', error);
      throw error;
    }
  };

  return {
    getPreferences,
    updatePreferences
  };
};
