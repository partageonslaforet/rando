import { getDb } from '@/lib/db';

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
  const db = getDb();
  
  try {
    const [totalUsersResult] = await db.execute('SELECT COUNT(*) as count FROM users');
    const [activeEventsResult] = await db.execute('SELECT COUNT(*) as count FROM events WHERE status = "active"');
    const [categoriesResult] = await db.execute('SELECT COUNT(DISTINCT category) as count FROM events');
    const [recentActivityResult] = await db.execute(
      `SELECT 
        CASE 
          WHEN type = 'event' THEN CONCAT('Nouvel événement : ', title)
          WHEN type = 'user' THEN CONCAT('Nouvel utilisateur : ', username)
        END as message,
        type,
        created_at as timestamp
      FROM (
        SELECT 'event' as type, title, NULL as username, created_at 
        FROM events 
        UNION ALL 
        SELECT 'user' as type, NULL as title, username, created_at 
        FROM users
      ) as activity 
      ORDER BY created_at DESC 
      LIMIT 5`
    );

    return {
      totalUsers: (totalUsersResult as any)[0].count,
      activeEvents: (activeEventsResult as any)[0].count,
      categories: (categoriesResult as any)[0].count,
      recentActivity: (recentActivityResult as any[]).map(item => ({
        type: item.type as 'event' | 'user',
        message: item.message,
        timestamp: new Date(item.timestamp).toISOString()
      }))
    };
  } catch (error) {
    console.error('Error fetching dashboard stats:', error);
    throw new Error('Failed to fetch dashboard statistics');
  }
}
