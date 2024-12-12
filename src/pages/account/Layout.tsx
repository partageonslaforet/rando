import React from 'react';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { User, Calendar, Settings } from 'lucide-react';
import { Header } from '../../components/Header';
import { useAuth } from '../../hooks/useAuth';

export function AccountLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = React.useState(false);
  const { user } = useAuth();
  const location = useLocation();

  const navigation = [
    { name: 'Mon Profil', href: '/account', icon: User },
    { name: 'Mes Événements', href: '/account/events', icon: Calendar },
    { name: 'Paramètres', href: '/account/settings', icon: Settings },
  ];

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
      <Header />

      <div className="container mx-auto px-4 py-24">
        <div className="flex flex-col md:flex-row gap-8">
          {/* Sidebar Mobile Toggle */}
          <button
            onClick={() => setIsSidebarOpen(!isSidebarOpen)}
            className="md:hidden flex items-center gap-2 text-gray-600 dark:text-gray-300"
          >
            Menu
          </button>

          {/* Sidebar */}
          <aside
            className={`
              md:w-64 flex-shrink-0
              ${isSidebarOpen ? 'block' : 'hidden'}
              md:block
            `}
          >
            <nav className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4">
              <div className="mb-6 p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 className="font-semibold text-gray-900 dark:text-white">
                  {user?.name || 'Utilisateur'}
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  {user?.email}
                </p>
              </div>

              <ul className="space-y-2">
                {navigation.map((item) => {
                  const isActive = location.pathname === item.href;
                  return (
                    <li key={item.name}>
                      <Link
                        to={item.href}
                        className={`
                          flex items-center gap-3 px-4 py-2 rounded-md
                          ${
                            isActive
                              ? 'bg-secondary text-white'
                              : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                          }
                        `}
                      >
                        <item.icon size={20} />
                        <span>{item.name}</span>
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </nav>
          </aside>

          {/* Main Content */}
          <main className="flex-1">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <Outlet />
            </div>
          </main>
        </div>
      </div>
    </div>
  );
}