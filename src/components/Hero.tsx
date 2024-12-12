import { useRef, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { AuthModal } from './auth/AuthModal';

interface HeroProps {
  searchQuery: string;
  onSearchChange: (query: string) => void;
}

export function Hero({ searchQuery, onSearchChange }: HeroProps) {
  const searchInputRef = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const [isAuthModalOpen, setIsAuthModalOpen] = useState(false);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    // Empêcher la soumission vide
    if (searchQuery.trim()) {
      // La recherche est déjà gérée via onSearchChange
      searchInputRef.current?.blur();
    }
  };

  useEffect(() => {
    if (typeof window !== 'undefined') {
      (window as any).resetSearchInput = () => {
        onSearchChange('');
        if (searchInputRef.current) {
          searchInputRef.current.value = '';
        }
      };
    }
  }, [onSearchChange]);

  return (
    <div className="relative w-screen h-[600px] -mt-16 -mx-[calc((100vw-100%)/2)] bg-hero bg-cover bg-center bg-no-repeat overflow-hidden">
      {/* Overlay sombre */}
      <div className="absolute inset-0 bg-black/30"></div>
      
      <div className="relative h-full flex items-center">
        <div className="mx-auto max-w-7xl px-6 w-full">
          <div className="mx-auto max-w-3xl text-center">
            <h2 className="text-4xl font-bold tracking-tight text-white sm:text-5xl mb-4">
              Découvrez des événements sportifs près de chez vous
            </h2>
            <h3 className="text-xl text-gray-200 mb-8">
              Trouvez et rejoignez des activités sportives organisées par des passionnés dans votre région
            </h3>
            
            <div className="flex flex-col sm:flex-row gap-4 justify-center items-center">
              <form onSubmit={handleSearch} className="relative flex-grow max-w-xl">
                <input
                  type="text"
                  ref={searchInputRef}
                  value={searchQuery}
                  onChange={(e) => onSearchChange(e.target.value)}
                  placeholder="Rechercher un événement..."
                  className="w-full px-4 py-3 rounded-lg bg-white/20 backdrop-blur-md text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-primary shadow-lg"
                />
                <button 
                  type="submit"
                  className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-300 hover:text-white"
                  aria-label="Rechercher"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </button>
              </form>
              
              <button 
                onClick={() => {
                  if (isAuthenticated) {
                    navigate('/CreateEvent');
                  } else {
                    setIsAuthModalOpen(true);
                  }
                }}
                className="px-6 py-3 bg-secondary text-white font-semibold rounded-lg shadow-lg hover:bg-secondary-dark transition-colors duration-200 whitespace-nowrap"
              >
                Créer un Événement
              </button>
            </div>
          </div>
        </div>
      </div>
      <AuthModal
        isOpen={isAuthModalOpen}
        onClose={() => setIsAuthModalOpen(false)}
        redirectUrl="/CreateEvent"
      />
    </div>
  );
}
