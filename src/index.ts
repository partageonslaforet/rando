// Components
export { Home } from './pages/Home';
export { CreateEvent } from './components/CreateEvent';
export { EventDetail } from './components/EventDetail';
export { AdminDashboard } from './pages/admin/Dashboard';
export { AdminLoginPage } from './pages/admin/Login';
export { AccountLayout } from './pages/account/Layout';
export { Profile } from './pages/account/Profile';
export { Events } from './pages/account/Events';
export { Settings } from './pages/account/Settings';
export { Layout } from './components/Layout';
export { ProtectedRoute } from './components/ProtectedRoute';
export { ErrorBoundary } from './components/ErrorBoundary';

// Contexts
export { AuthProvider } from './contexts/AuthContext';

// Third-party exports
export { Toaster } from 'react-hot-toast';

// Hooks
export { useAuth } from './hooks/useAuth';

// Types
export type { Event, Route, Contact } from './types/event';
