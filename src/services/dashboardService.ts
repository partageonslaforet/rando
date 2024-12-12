import { db } from '@/lib/db';
import { RowDataPacket } from 'mysql2/promise';

interface EventRow extends RowDataPacket {
  title: string;
  created_at: Date;
  user_name: string;
}

interface UserRow extends RowDataPacket {
  name: string;
  created_at: Date;
}

interface CountResult extends RowDataPacket {
  count: number;
}

export interface DashboardStats {
  totalUsers: number;
  activeEvents: number;
  categories: number;
  recentActivity: Array<{
    type: 'event' | 'user';
    message: string;
    timestamp: string;
  }>;
}

export async function getDashboardStats(): Promise<DashboardStats> {
  try {
    // Récupérer le nombre total d'utilisateurs
    const [usersResult] = await db.query<CountResult[]>(
      'SELECT COUNT(*) as count FROM cool5792_users'
    );
    const totalUsers = usersResult[0].count;

    // Récupérer le nombre d'événements actifs
    const [eventsResult] = await db.query<CountResult[]>(
      'SELECT COUNT(*) as count FROM cool5792_sports_events WHERE end_date > NOW()'
    );
    const activeEvents = eventsResult[0].count;

    // Récupérer le nombre de catégories uniques
    const [categoriesResult] = await db.query<CountResult[]>(
      'SELECT COUNT(DISTINCT category) as count FROM cool5792_sports_events WHERE category IS NOT NULL'
    );
    const categories = categoriesResult[0].count;

    // Récupérer les événements récents
    const [recentEvents] = await db.query<EventRow[]>(
      `SELECT 
        e.title, 
        e.created_at,
        u.name as user_name
      FROM cool5792_sports_events e
      LEFT JOIN cool5792_users u ON e.user_id = u.id
      ORDER BY e.created_at DESC
      LIMIT 3`
    );

    // Récupérer les utilisateurs récents
    const [recentUsers] = await db.query<UserRow[]>(
      `SELECT name, created_at
      FROM cool5792_users
      ORDER BY created_at DESC
      LIMIT 3`
    );

    // Combiner et formater l'activité récente
    const recentActivity = [
      ...recentEvents.map(event => ({
        type: 'event' as const,
        message: `Nouvel événement créé par ${event.user_name || 'Utilisateur inconnu'}: ${event.title}`,
        timestamp: event.created_at.toISOString()
      })),
      ...recentUsers.map(user => ({
        type: 'user' as const,
        message: `Nouvel utilisateur inscrit: ${user.name}`,
        timestamp: user.created_at.toISOString()
      }))
    ].sort((a, b) => 
      new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime()
    ).slice(0, 5);

    return {
      totalUsers,
      activeEvents,
      categories,
      recentActivity
    };
  } catch (error) {
    console.error('Erreur lors de la récupération des statistiques:', error);
    throw error;
  }
}
