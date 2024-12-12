import { useState } from 'react';
import { HeaderAdmin } from '@/components/HeaderAdmin';
import { useAuth } from '@/hooks/useAuth';
import { Navigate } from 'react-router-dom';
import mapHeroImage from '@/assets/images/map-hero.jpg';

export default function AdminPage() {
  const { user, isAuthenticated } = useAuth();
  const [activeSection, setActiveSection] = useState('dashboard');

  // Rediriger si l'utilisateur n'est pas connecté ou n'est pas admin
  if (!isAuthenticated || user?.role !== 'admin') {
    return <Navigate to="/" />;
  }

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
      <HeaderAdmin />
      
      {/* Hero Section */}
      <div 
        className="relative h-[300px] bg-cover bg-center"
        style={{ backgroundImage: `url(${mapHeroImage})` }}
      >
        <div className="absolute inset-0 bg-black/50">
          <div className="max-w-7xl mx-auto px-4 h-full flex items-center">
            <div className="text-white">
              <h1 className="text-4xl font-bold mb-2">Administration</h1>
              <p className="text-xl">Gérez vos événements et utilisateurs</p>
            </div>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="grid grid-cols-12 gap-6">
          {/* Sidebar */}
          <div className="col-span-12 md:col-span-3">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
              <nav className="space-y-2">
                <button
                  onClick={() => setActiveSection('dashboard')}
                  className={`w-full text-left px-4 py-2 rounded-lg transition-colors ${
                    activeSection === 'dashboard'
                      ? 'bg-primary text-white'
                      : 'hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  Tableau de bord
                </button>
                <button
                  onClick={() => setActiveSection('events')}
                  className={`w-full text-left px-4 py-2 rounded-lg transition-colors ${
                    activeSection === 'events'
                      ? 'bg-primary text-white'
                      : 'hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  Événements
                </button>
                <button
                  onClick={() => setActiveSection('users')}
                  className={`w-full text-left px-4 py-2 rounded-lg transition-colors ${
                    activeSection === 'users'
                      ? 'bg-primary text-white'
                      : 'hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  Utilisateurs
                </button>
              </nav>
            </div>
          </div>

          {/* Main Content */}
          <div className="col-span-12 md:col-span-9">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
              {activeSection === 'dashboard' && (
                <div>
                  <h2 className="text-2xl font-bold mb-4">Tableau de bord</h2>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                      <div className="flex items-center space-x-4">
                        <div className="p-3 bg-blue-100 rounded-full">
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197zM13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                          </svg>
                        </div>
                        <div>
                          <p className="text-sm text-gray-500">Utilisateurs totaux</p>
                          <h3 className="text-2xl font-bold">127</h3>
                        </div>
                      </div>
                    </div>
                    
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                      <div className="flex items-center space-x-4">
                        <div className="p-3 bg-green-100 rounded-full">
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                        </div>
                        <div>
                          <p className="text-sm text-gray-500">Événements actifs</p>
                          <h3 className="text-2xl font-bold">24</h3>
                        </div>
                      </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                      <div className="flex items-center space-x-4">
                        <div className="p-3 bg-purple-100 rounded-full">
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197zM13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                          </svg>
                        </div>
                        <div>
                          <p className="text-sm text-gray-500">Nouveaux utilisateurs</p>
                          <h3 className="text-2xl font-bold">12</h3>
                          <p className="text-sm text-gray-500">cette semaine</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Activité récente */}
                  <div className="mt-6">
                    <h2 className="text-lg font-semibold mb-4">Activité récente</h2>
                    <div className="space-y-4">
                      <div className="flex items-center justify-between py-3 border-b">
                        <div className="flex items-center space-x-3">
                          <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                          <p>Nouvel événement créé par Jean Dupont</p>
                        </div>
                        <span className="text-sm text-gray-500">Il y a 2h</span>
                      </div>
                      <div className="flex items-center justify-between py-3 border-b">
                        <div className="flex items-center space-x-3">
                          <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                          <p>5 nouveaux utilisateurs inscrits</p>
                        </div>
                        <span className="text-sm text-gray-500">Il y a 5h</span>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {activeSection === 'events' && (
                <div>
                  <h2 className="text-2xl font-bold mb-4">Gestion des événements</h2>
                  <div className="flex justify-between items-center mb-6">
                    <div className="flex space-x-2">
                      <select className="border rounded-md px-3 py-1">
                        <option>Tous les statuts</option>
                        <option>En cours</option>
                        <option>À venir</option>
                        <option>Terminés</option>
                      </select>
                      <input
                        type="text"
                        placeholder="Rechercher..."
                        className="border rounded-md px-3 py-1"
                      />
                    </div>
                  </div>
                  <div className="overflow-x-auto">
                    <table className="min-w-full">
                      <thead>
                        <tr className="bg-gray-50">
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Titre
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Organisateur
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Statut
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        <tr>
                          <td className="px-6 py-4 whitespace-nowrap">
                            Randonnée en forêt
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            15 Dec 2024
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            Marie Martin
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                              À venir
                            </span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {activeSection === 'users' && (
                <div>
                  <h2 className="text-2xl font-bold mb-4">Gestion des utilisateurs</h2>
                  <p>Section en cours de développement...</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
