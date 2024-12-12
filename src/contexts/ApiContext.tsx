import React, { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import axios, { AxiosInstance, AxiosError } from 'axios';

// Configuration de base pour l'API
const isDevelopment = process.env.NODE_ENV === 'development';
const baseURL = import.meta.env.VITE_API_URL || (isDevelopment
  ? 'http://localhost:8000'
  : 'https://api.rando.partageonslaforet.be');

// Fonction de logging améliorée
const debug = {
  log: (context: string, message: string, data?: any) => {
    const timestamp = new Date().toISOString();
    const logEntry = { timestamp, context, message, data };
    console.log('🔍', `[${timestamp}] [${context}]`, message, data || '');
    
    try {
      const storedLogs = localStorage.getItem('api_logs') || '[]';
      const logs = JSON.parse(storedLogs);
      logs.push(logEntry);
      localStorage.setItem('api_logs', JSON.stringify(logs.slice(-100)));
    } catch (error) {
      console.error('Erreur lors de la sauvegarde des logs:', error);
    }
  },
  error: (context: string, message: string, error: any) => {
    const timestamp = new Date().toISOString();
    const errorData = error instanceof AxiosError ? {
      message: error.message,
      status: error.response?.status,
      data: error.response?.data,
      config: {
        url: error.config?.url,
        method: error.config?.method,
        headers: error.config?.headers,
        withCredentials: error.config?.withCredentials
      }
    } : error;

    const logEntry = { timestamp, context, message, error: errorData };
    console.error('❌', `[${timestamp}] [${context}]`, message, errorData);

    try {
      const storedLogs = localStorage.getItem('api_error_logs') || '[]';
      const logs = JSON.parse(storedLogs);
      logs.push(logEntry);
      localStorage.setItem('api_error_logs', JSON.stringify(logs.slice(-50)));
    } catch (err) {
      console.error('Erreur lors de la sauvegarde des logs d\'erreur:', err);
    }
  }
};

interface ApiContextType {
  api: AxiosInstance | null;
  isReady: boolean;
}

const ApiContext = createContext<ApiContextType>({
  api: null,
  isReady: false
});

interface ApiProviderProps {
  children: ReactNode;
}

export function ApiProvider({ children }: ApiProviderProps) {
  const [api, setApi] = useState<AxiosInstance | null>(null);
  const [isReady, setIsReady] = useState(false);

  useEffect(() => {
    const initializeApi = async () => {
      try {
        debug.log('API', 'Initialisation de l\'API', { baseURL, isDevelopment });

        const instance = axios.create({
          baseURL,
          timeout: 10000,
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Origin': isDevelopment ? 'http://localhost:3000' : 'https://rando.partageonslaforet.be'
          },
        });

        // Intercepteur pour les requêtes
        instance.interceptors.request.use(
          (config) => {
            const token = localStorage.getItem('token');
            if (token) {
              config.headers.Authorization = `Bearer ${token}`;
            }
            debug.log('API', 'Requête envoyée', {
              method: config.method,
              url: config.url,
              headers: config.headers
            });
            return config;
          },
          (error) => {
            debug.error('API', 'Erreur lors de la requête', error);
            return Promise.reject(error);
          }
        );

        // Intercepteur pour les réponses
        instance.interceptors.response.use(
          (response) => {
            debug.log('API', 'Réponse reçue', {
              status: response.status,
              url: response.config.url
            });
            return response;
          },
          (error) => {
            debug.error('API', 'Erreur de réponse', error);
            return Promise.reject(error);
          }
        );

        // Test de l'API
        try {
          await instance.get('/');
          debug.log('API', 'API initialisée avec succès');
          setApi(instance);
          setIsReady(true);
        } catch (error) {
          debug.error('API', 'Erreur lors du test de l\'API', error);
          setApi(null);
          setIsReady(false);
        }
      } catch (error) {
        debug.error('API', 'Erreur lors de l\'initialisation de l\'API', error);
        setApi(null);
        setIsReady(false);
      }
    };

    initializeApi();
  }, []);

  return (
    <ApiContext.Provider value={{ api, isReady }}>
      {children}
    </ApiContext.Provider>
  );
}

export function useApi() {
  const context = useContext(ApiContext);
  if (context === undefined) {
    throw new Error('useApi must be used within an ApiProvider');
  }
  return context;
}

// Fonctions de gestion des logs
export function getLogs(): string {
  try {
    const apiLogs = JSON.parse(localStorage.getItem('api_logs') || '[]');
    const errorLogs = JSON.parse(localStorage.getItem('api_error_logs') || '[]');
    return JSON.stringify({ apiLogs, errorLogs }, null, 2);
  } catch (error) {
    console.error('Erreur lors de la récupération des logs:', error);
    return 'Erreur lors de la récupération des logs';
  }
}

export function clearLogs(): void {
  try {
    localStorage.removeItem('api_logs');
    localStorage.removeItem('api_error_logs');
    console.log('Logs effacés avec succès');
  } catch (error) {
    console.error('Erreur lors de l\'effacement des logs:', error);
  }
}
