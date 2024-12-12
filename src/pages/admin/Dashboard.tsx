import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Users, Calendar, Tag, BarChart } from 'lucide-react';
import { AdminLayout } from '../../components/admin/AdminLayout';
import { StatsCard } from '../../components/admin/StatsCard';
import { RecentEvents } from '../../components/admin/RecentEvents';
import { PendingValidations } from '../../components/admin/PendingValidations';

export function AdminDashboard() {
  const { data: stats } = useQuery({
    queryKey: ['admin', 'stats'],
    queryFn: async () => {
      // Fetch stats from API
      return {
        users: 150,
        events: 45,
        categories: 3,
        pendingValidations: 12
      };
    }
  });

  return (
    <AdminLayout>
      <div className="p-6">
        <h1 className="text-2xl font-bold mb-6 dark:text-white">Tableau de bord</h1>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <StatsCard
            title="Utilisateurs"
            value={stats?.users || 0}
            icon={Users}
            trend={+5}
          />
          <StatsCard
            title="Événements"
            value={stats?.events || 0}
            icon={Calendar}
            trend={+2}
          />
          <StatsCard
            title="Catégories"
            value={stats?.categories || 0}
            icon={Tag}
          />
          <StatsCard
            title="En attente"
            value={stats?.pendingValidations || 0}
            icon={BarChart}
            trend={-3}
            trendColor="text-red-500"
          />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 className="text-xl font-semibold mb-4 dark:text-white">
              Événements récents
            </h2>
            <RecentEvents />
          </div>

          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 className="text-xl font-semibold mb-4 dark:text-white">
              Validations en attente
            </h2>
            <PendingValidations />
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}