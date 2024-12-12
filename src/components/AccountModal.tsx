import React from 'react';
import { Link } from 'react-router-dom';
import { User, Calendar, Settings, LogOut } from 'lucide-react';

interface AccountModalProps {
  isOpen: boolean;
  onClose: () => void;
  onLogout: () => void;
}

export function AccountModal({ isOpen, onClose, onLogout }: AccountModalProps) {
  if (!isOpen) return null;

  return (
    <div 
      className="fixed inset-0 z-50 flex items-start justify-center"
      onClick={onClose}
    >
      <div 
        className="mt-20 bg-white dark:bg-gray-800 rounded-lg shadow-lg w-64 overflow-hidden"
        onClick={e => e.stopPropagation()}
      >
        <div className="p-4 bg-[#990047] text-white">
          <h3 className="font-semibold">Mon Compte</h3>
        </div>

        <nav className="p-2">
          <Link
            to="/account"
            className="flex items-center gap-3 px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md"
            onClick={onClose}
          >
            <User size={18} />
            <span>Mon Profil</span>
          </Link>

          <Link
            to="/account/events"
            className="flex items-center gap-3 px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md"
            onClick={onClose}
          >
            <Calendar size={18} />
            <span>Mes Événements</span>
          </Link>

          <Link
            to="/account/settings"
            className="flex items-center gap-3 px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md"
            onClick={onClose}
          >
            <Settings size={18} />
            <span>Paramètres</span>
          </Link>

          <button
            onClick={() => {
              onLogout();
              onClose();
            }}
            className="flex items-center gap-3 px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/10 w-full rounded-md"
          >
            <LogOut size={18} />
            <span>Déconnexion</span>
          </button>
        </nav>
      </div>
    </div>
  );
}