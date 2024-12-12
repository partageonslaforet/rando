import { useApi } from '../contexts/ApiContext';

interface ApiResponse<T> {
  status: string;
  data: T;
  message?: string;
}

export interface ProfileData {
  name: string;
  email: string;
  address?: string;
  phone?: string;
  bio?: string;
}

export const useProfile = () => {
  const { api } = useApi();

  const updateProfile = async (profileData: Partial<ProfileData>): Promise<ProfileData> => {
    if (!api) {
      throw new Error('API non initialisée');
    }

    try {
      const response = await api.put<ApiResponse<ProfileData>>('/profile', profileData);
      console.log('Response from updateProfile:', response);
      if (!response.data || !response.data.data) {
        console.log('Profile update failed, returning null');
        return null;
      }
      return response.data.data;
    } catch (error) {
      console.error('Error in updateProfile:', error);
      throw error;
    }
  };

  const getProfile = async (): Promise<ProfileData> => {
    if (!api) {
      throw new Error('API non initialisée');
    }

    try {
      const response = await api.get<ApiResponse<ProfileData>>('/profile');
      console.log('Response from getProfile:', response);
      if (!response.data || !response.data.data) {
        console.log('No profile found, returning null');
        return null;
      }
      return response.data.data;
    } catch (error) {
      console.error('Error in getProfile:', error);
      throw error;
    }
  };

  return {
    updateProfile,
    getProfile
  };
};
