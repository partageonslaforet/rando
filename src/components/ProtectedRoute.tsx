import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { toast } from 'react-hot-toast';
import { useMemo } from 'react';

interface ProtectedRouteProps {
  children?: React.ReactNode;
  requireAdmin?: boolean;
}

export function ProtectedRoute({ children, requireAdmin = false }: ProtectedRouteProps) {
  const { isLoggedIn, user, isLoading } = useAuth();
  const location = useLocation();

  const redirectPath = useMemo(() => {
    const currentPath = location.pathname + location.search + location.hash;
    return currentPath;
  }, [location]);

  // Si l'utilisateur n'est pas connecté et que le chargement est terminé
  if (!isLoading && (!isLoggedIn || !user)) {
    // Stocker le chemin actuel pour la redirection après connexion
    localStorage.setItem('redirectAfterLogin', redirectPath);
    
    // Afficher un message à l'utilisateur
    toast.error('Veuillez vous connecter pour accéder à cette page', {
      id: 'auth-required',
      duration: 3000
    });
    
    // Rediriger vers la page d'accueil avec le modal d'authentification
    return <Navigate to="/" state={{ openAuthModal: true, from: redirectPath }} replace />;
  }

  // Si la route nécessite les droits admin
  if (!isLoading && requireAdmin && user?.role !== 'admin') {
    toast.error('Accès non autorisé', {
      id: 'admin-required',
      duration: 3000
    });
    return <Navigate to="/" replace />;
  }

  // Afficher un loader pendant la vérification de l'authentification
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  // Si tout est OK, afficher le contenu protégé
  return <>{children}</>;
}