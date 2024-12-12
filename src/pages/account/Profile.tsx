import React, { useState, useEffect } from 'react';
import { User, Mail, Calendar, Shield } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { useApi } from '../../contexts/ApiContext';
import toast from 'react-hot-toast';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';

export function Profile() {
  const { user } = useAuth();
  const { api } = useApi();
  const [isEditing, setIsEditing] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    email: ''
  });

  useEffect(() => {
    if (user) {
      setFormData({
        name: user.name || '',
        email: user.email || ''
      });
    }
  }, [user]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!api) {
      toast.error('Service API non disponible');
      return;
    }

    try {
      const response = await api.patch('/auth/profile', {
        name: formData.name,
        email: formData.email
      });

      if (response.data?.status === 'success') {
        toast.success('Profil mis à jour avec succès');
        setIsEditing(false);
      } else {
        toast.error('Erreur lors de la mise à jour du profil');
      }
    } catch (error) {
      console.error('Erreur lors de la mise à jour du profil:', error);
      toast.error('Erreur lors de la mise à jour du profil');
    }
  };

  if (!user) {
    return null;
  }

  return (
    <div className="space-y-6 bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm">
      <div className="border-b border-gray-200 dark:border-gray-700 pb-4">
        <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Profil</h2>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Gérez vos informations personnelles
        </p>
      </div>

      <div className="space-y-6">
        {/* Informations de base */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <div className="flex items-center gap-2 text-gray-700 dark:text-gray-300">
              <User size={20} />
              <span className="font-medium">Nom</span>
            </div>
            {isEditing ? (
              <input
                type="text"
                name="name"
                value={formData.name}
                onChange={handleChange}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              />
            ) : (
              <p className="mt-1 text-gray-900 dark:text-gray-100">{user.name || 'Non renseigné'}</p>
            )}
          </div>

          <div>
            <div className="flex items-center gap-2 text-gray-700 dark:text-gray-300">
              <Mail size={20} />
              <span className="font-medium">Email</span>
              {user.isVerified && (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                  Vérifié
                </span>
              )}
            </div>
            {isEditing ? (
              <input
                type="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              />
            ) : (
              <p className="mt-1 text-gray-900 dark:text-gray-100">{user.email}</p>
            )}
          </div>
        </div>

        {/* Informations du compte */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <div className="flex items-center gap-2 text-gray-700 dark:text-gray-300">
              <Calendar size={20} />
              <span className="font-medium">Membre depuis</span>
            </div>
            <p className="mt-1 text-gray-900 dark:text-gray-100">
              {format(new Date(user.createdAt), 'dd MMMM yyyy', { locale: fr })}
            </p>
          </div>

          <div>
            <div className="flex items-center gap-2 text-gray-700 dark:text-gray-300">
              <Shield size={20} />
              <span className="font-medium">Rôle</span>
            </div>
            <p className="mt-1 text-gray-900 dark:text-gray-100 capitalize">
              {user.role === 'admin' ? 'Administrateur' : 'Utilisateur'}
            </p>
          </div>

          {user.lastLogin && (
            <div>
              <div className="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                <Calendar size={20} />
                <span className="font-medium">Dernière connexion</span>
              </div>
              <p className="mt-1 text-gray-900 dark:text-gray-100">
                {format(new Date(user.lastLogin), 'dd MMMM yyyy à HH:mm', { locale: fr })}
              </p>
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
          {isEditing ? (
            <>
              <button
                type="button"
                onClick={() => setIsEditing(false)}
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
              >
                Annuler
              </button>
              <button
                type="button"
                onClick={handleSubmit}
                className="px-4 py-2 text-sm font-medium text-white bg-primary border border-transparent rounded-md shadow-sm hover:bg-primary-dark"
              >
                Enregistrer
              </button>
            </>
          ) : (
            <button
              type="button"
              onClick={() => setIsEditing(true)}
              className="px-4 py-2 text-sm font-medium text-white bg-primary border border-transparent rounded-md shadow-sm hover:bg-primary-dark"
            >
              Modifier
            </button>
          )}
        </div>
      </div>
    </div>
  );
}