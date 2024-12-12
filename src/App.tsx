import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { Home } from './pages/Home';
import { Account } from './pages/Account';
import { CreateEvent } from './pages/CreateEvent';
import { EventDetails } from './pages/EventDetails';
import { useAuth } from './hooks/useAuth';
import { AuthProvider } from './contexts/AuthContext';
import { Toaster } from 'react-hot-toast';
import { ThemeProvider } from './contexts/ThemeContext';
import { Header } from './components/Header';
import { Footer } from './components/Footer';
import { EditEvent } from './pages/EditEvent';
import { Register } from './pages/Register';
import { Login } from './pages/Login';
import type { Event } from './types/event';
import VerifyEmailSent from './pages/verify-email-sent';
import ResendVerification from './pages/resend-verification';
import AdminPage from './pages/admin';

// Route protégée pour les utilisateurs authentifiés
function PrivateRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuth();
  
  if (!isAuthenticated) {
    return <Navigate to="/login" />;
  }

  return <>{children}</>;
}

// Route protégée pour les administrateurs
function AdminRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, user } = useAuth();
  
  if (!isAuthenticated || user?.role !== 'admin') {
    return <Navigate to="/" />;
  }

  return <>{children}</>;
}

function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <Router>
          <div className="min-h-screen flex flex-col">
            <Routes>
              <Route path="/" element={<Home />} />
              <Route 
                path="/account" 
                element={
                  <PrivateRoute>
                    <Account />
                  </PrivateRoute>
                } 
              />
              <Route 
                path="/CreateEvent" 
                element={
                  <PrivateRoute>
                    <CreateEvent />
                  </PrivateRoute>
                } 
              />
              <Route 
                path="/event/:id" 
                element={<EventDetails />} 
              />
              <Route 
                path="/event/edit/:id" 
                element={
                  <PrivateRoute>
                    <EditEvent />
                  </PrivateRoute>
                } 
              />
              <Route path="/register" element={<Register />} />
              <Route path="/login" element={<Login />} />
              <Route path="/verify-email-sent" element={<VerifyEmailSent />} />
              <Route path="/resend-verification" element={<ResendVerification />} />
              <Route 
                path="/admin" 
                element={
                  <AdminRoute>
                    <AdminPage />
                  </AdminRoute>
                } 
              />
            </Routes>
            <Toaster position="bottom-right" />
          </div>
        </Router>
      </AuthProvider>
    </ThemeProvider>
  );
}

export default App;
