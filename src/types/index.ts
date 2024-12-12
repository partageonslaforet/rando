export interface LoginCredentials {
    email: string;
    password: string;
  }
  
  export interface CommonProps {
    isLoggedIn: boolean;
    onLogin: (credentials: LoginCredentials) => void;
    onLogout: () => void;
    isDarkMode: boolean;
    onToggleTheme: () => void;
  }