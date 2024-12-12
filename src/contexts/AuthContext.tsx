import { createContext, useEffect, useState, ReactNode } from 'react';
import toast from 'react-hot-toast';
import axios from 'axios';

// Types
interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  is_verified: boolean;
  is_active: boolean;
  created_at: string;
  last_login: string;
}

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentials: { email: string; password: string; device_name: string }) => Promise<void>;
  logout: () => void;
  setUser: (user: User | null) => void;
  setToken: (token: string | null) => void;
  verifyToken: (token: string) => Promise<boolean>;
}

// Fonction de logging pour l'authentification
const authDebug = {
  log: (context: string, message: string, data?: any) => {
    const timestamp = new Date().toISOString();
    console.log('🔐', `[${timestamp}] [${context}]`, message, data || '');
  },
  error: (context: string, message: string, error: any) => {
    const timestamp = new Date().toISOString();
    console.error('🚫', `[${timestamp}] [${context}]`, message, error);
  },
  warn: (context: string, message: string, data?: any) => {
    const timestamp = new Date().toISOString();
    console.warn('⚠️', `[${timestamp}] [${context}]`, message, data || '');
  }
};

export const AuthContext = createContext<AuthContextType>({
  user: null,
  isAuthenticated: false,
  isLoading: true,
  login: async () => {},
  logout: () => {},
  setUser: () => {},
  setToken: () => {},
  verifyToken: async () => false,
});

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [token, setToken] = useState<string | null>(null);

  // Vérifier le token au démarrage
  useEffect(() => {
    const storedToken = localStorage.getItem('token');
    if (storedToken) {
      authDebug.log('Auth', 'Token trouvé au démarrage', { tokenLength: storedToken.length });
      setToken(storedToken);
      verifyToken(storedToken);
    } else {
      authDebug.log('Auth', 'Aucun token au démarrage');
      setIsLoading(false);
    }
  }, []);

  // Vérifier la validité du token
  const verifyToken = async (token: string) => {
    try {
      authDebug.log('Auth', 'Vérification du token', { tokenLength: token.length });
      const response = await axios.get(`${import.meta.env.VITE_API_URL}/auth/me`, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data?.data?.user) {
        authDebug.log('Auth', 'Token valide, utilisateur récupéré', response.data.data.user);
        setUser(response.data.data.user);
        return true;
      }
    } catch (error: any) {
      authDebug.error('Auth', 'Erreur lors de la vérification du token', error);
      localStorage.removeItem('token');
      setToken(null);
      setUser(null);
    }

    setIsLoading(false);
    return false;
  };

  const login = async (credentials: { email: string; password: string; device_name: string }) => {
    try {
      authDebug.log('Auth', 'Début de la tentative de connexion', {
        email: credentials.email,
        timestamp: new Date().toISOString(),
        apiUrl: import.meta.env.VITE_API_URL
      });

      setIsLoading(true);

      authDebug.log('Auth', 'Envoi de la requête de connexion', {
        url: `${import.meta.env.VITE_API_URL}/auth/login`,
        method: 'POST',
        credentials: {
          email: credentials.email,
          device_name: credentials.device_name,
          passwordLength: credentials.password.length
        }
      });

      // Tentative de connexion avec une nouvelle instance axios
      const response = await axios.post(
        `${import.meta.env.VITE_API_URL}/auth/login`,
        credentials,
        {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          withCredentials: true
        }
      );

      authDebug.log('Auth', 'Réponse reçue', {
        status: response.status,
        statusText: response.statusText,
        headers: response.headers,
        data: response.data
      });

      if (response.data?.data?.token) {
        const { token, user } = response.data.data;
        authDebug.log('Auth', 'Token reçu', { tokenLength: token.length });
        
        localStorage.setItem('token', token);
        setToken(token);
        setUser(user);
        
        authDebug.log('Auth', 'Connexion réussie', {
          userId: user.id,
          userName: user.name,
          userEmail: user.email
        });
      } else {
        const error = new Error('Token non reçu dans la réponse');
        authDebug.error('Auth', 'Réponse invalide', {
          responseData: response.data,
          error
        });
        throw error;
      }
    } catch (error: any) {
      authDebug.error('Auth', 'Erreur lors de la connexion', {
        name: error.name,
        message: error.message,
        stack: error.stack,
        response: error.response,
        request: error.request,
        config: error.config
      });
      
      localStorage.removeItem('token');
      setToken(null);
      setUser(null);
      throw error;
    } finally {
      setIsLoading(false);
    }
  };

  const logout = () => {
    localStorage.removeItem('token');
    setToken(null);
    setUser(null);
  };

  const value = {
    user,
    isAuthenticated: !!user,
    isLoading,
    login,
    logout,
    setUser,
    setToken,
    verifyToken
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}