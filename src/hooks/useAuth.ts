import { useState, useEffect } from 'react';
import axios from 'axios';
import type { User } from '../types/user';

interface LoginCredentials {
  email: string;
  device_name: string;
}

interface AuthState {
  isAuthenticated: boolean;
  user: User | null;
  token: string | null;
  isLoading: boolean;
}

export function useAuth() {
  const [state, setState] = useState<AuthState>({
    isAuthenticated: false,
    user: null,
    token: null,
    isLoading: true,
  });

  useEffect(() => {
    // Récupérer le token du localStorage au chargement
    const storedToken = localStorage.getItem('authToken');
    const storedUser = localStorage.getItem('authUser');
    
    if (storedToken && storedUser) {
      setState({
        isAuthenticated: true,
        token: storedToken,
        user: JSON.parse(storedUser),
        isLoading: false,
      });
    } else {
      setState(prev => ({ ...prev, isLoading: false }));
    }
  }, []);

  const login = async (credentials: LoginCredentials) => {
    console.log('[Auth] Début de la tentative de connexion avec les credentials:', credentials);
    
    try {
      // Création d'une nouvelle instance axios
      const axiosInstance = axios.create({
        baseURL: 'https://api.rando.partageonslaforet.be',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      });
      
      console.log('[Auth] Création d\'une nouvelle instance axios avec l\'URL:', axiosInstance.defaults.baseURL);

      // Configuration de la requête
      const requestConfig = {
        url: '/auth/login',
        method: 'POST',
        data: credentials,
        headers: {
          'Content-Type': 'application/json'
        }
      };
      
      console.log('[Auth] Configuration de la requête:', requestConfig);

      // Envoi de la requête
      const response = await axiosInstance.request(requestConfig);
      console.log('[Auth] Réponse reçue:', response);

      // Extraction des données
      const { userId, token } = response.data;
      console.log('[Auth] Données extraites:', { userId, tokenLength: token?.length });

      // Mise à jour de l'état
      const userData: User = { 
        id: userId, 
        role: 'admin',
        name: 'Admin',
        email: credentials.email
      };
      setState({
        isAuthenticated: true,
        token,
        user: userData,
        isLoading: false,
      });

      // Sauvegarde dans le localStorage
      localStorage.setItem('authToken', token);
      localStorage.setItem('authUser', JSON.stringify(userData));

      console.log('[Auth] État mis à jour avec succès');
      return userData;
    } catch (error) {
      console.error('[Auth] Erreur lors de la connexion:', error);
      throw error;
    }
  };

  const logout = async () => {
    try {
      setState({
        isAuthenticated: false,
        token: null,
        user: null,
        isLoading: false,
      });
      localStorage.removeItem('authToken');
      localStorage.removeItem('authUser');
    } catch (error) {
      console.error('[Auth] Erreur lors de la déconnexion:', error);
      // On déconnecte quand même localement en cas d'erreur
      setState({
        isAuthenticated: false,
        token: null,
        user: null,
        isLoading: false,
      });
      localStorage.removeItem('authToken');
      localStorage.removeItem('authUser');
    }
  };

  return {
    ...state,
    login,
    logout,
  };
}