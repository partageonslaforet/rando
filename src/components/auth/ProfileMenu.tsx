import { useState, useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { User, LogOut } from 'lucide-react';

export function ProfileMenu() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  console.log('ProfileMenu - État:', { 
    user,
    isOpen,
    userName: user?.name
  });

  useEffect(() => {
    console.log('ProfileMenu - Montage');
    const handleClickOutside = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      console.log('ProfileMenu - Démontage');
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const handleLogout = () => {
    console.log('ProfileMenu - Déconnexion');
    logout();
    navigate('/');
    setIsOpen(false);
  };

  const handleProfileClick = () => {
    console.log('ProfileMenu - Navigation vers le profil');
    navigate('/account');
    setIsOpen(false);
  };

  if (!user) {
    console.log('ProfileMenu - Pas d\'utilisateur, ne pas rendre');
    return null;
  }

  return (
    <div className="relative" ref={menuRef}>
      <button
        onClick={() => {
          console.log('ProfileMenu - Toggle menu');
          setIsOpen(!isOpen);
        }}
        className="flex items-center space-x-2 px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary-dark transition-colors"
      >
        <User size={18} />
        <span>{user.name || 'Profil'}</span>
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-50">
          <div className="py-1" role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
            <button
              onClick={handleProfileClick}
              className="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
              role="menuitem"
            >
              Mon profil
            </button>
            <button
              onClick={handleLogout}
              className="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center"
              role="menuitem"
            >
              <LogOut size={18} className="mr-2" />
              Déconnexion
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
