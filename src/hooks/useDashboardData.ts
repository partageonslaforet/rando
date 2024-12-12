import { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from './useAuth';

interface DashboardStats {
  totalUsers: number;
  activeEvents: number;
  categories: number;
  recentActivity: Array<{
    type: 'event' | 'user';
    message: string;
    timestamp: string;
  }>;
}

interface DashboardData {
  isLoading: boolean;
  error: string | null;
  data: DashboardStats | null;
}

export function useDashboardData(): DashboardData {
  const { token } = useAuth();
  const [state, setState] = useState<DashboardData>({
    isLoading: true,
    error: null,
    data: null,
  });

  useEffect(() => {
    async function fetchDashboardData() {
      if (!token) {
        setState(prev => ({
          ...prev,
          isLoading: false,
          error: 'Non authentifié',
          data: null,
        }));
        return;
      }

      try {
        console.log('[Dashboard] Fetching dashboard data...');
        const response = await axios.get<DashboardStats>('/dashboard', {
          baseURL: 'https://api.rando.partageonslaforet.be',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          }
        });
        console.log('[Dashboard] Response received:', response.data);

        // Vérification et transformation des données
        const data: DashboardStats = {
          totalUsers: Number(response.data.totalUsers) || 0,
          activeEvents: Number(response.data.activeEvents) || 0,
          categories: Number(response.data.categories) || 0,
          recentActivity: Array.isArray(response.data.recentActivity) 
            ? response.data.recentActivity.map(activity => ({
                type: activity.type as 'event' | 'user',
                message: String(activity.message),
                timestamp: String(activity.timestamp)
              }))
            : []
        };

        console.log('[Dashboard] Processed data:', data);
        setState({
          data,
          isLoading: false,
          error: null,
        });
      } catch (error) {
        console.error('[Dashboard] Error:', error);
        setState(prev => ({
          ...prev,
          isLoading: false,
          error: error instanceof Error ? error.message : 'Une erreur est survenue',
          data: null,
        }));
      }
    }

    fetchDashboardData();
  }, [token]); // Ajout du token comme dépendance

  return state;
}
