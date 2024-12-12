import * as mysql from 'mysql2/promise';
import { PoolOptions, Pool } from 'mysql2/promise';

let db: Pool | null = null;

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

class Database {
  private static instance: Database;
  private connection: mysql.Connection | null = null;

  private constructor() {}

  public static getInstance(): Database {
    if (!Database.instance) {
      Database.instance = new Database();
    }
    return Database.instance;
  }

  private async getConnection(): Promise<mysql.Connection> {
    if (!this.connection) {
      this.connection = await mysql.createConnection({
        host: process.env.MYSQL_HOST,
        user: process.env.MYSQL_USER,
        password: process.env.MYSQL_PASSWORD,
        database: process.env.MYSQL_DATABASE
      });
    }
    return this.connection;
  }

  public async getDashboardStats(): Promise<DashboardStats> {
    const connection = await this.getConnection();
    
    try {
      const [totalUsersResult] = await connection.execute('SELECT COUNT(*) as count FROM users');
      const [activeEventsResult] = await connection.execute('SELECT COUNT(*) as count FROM events WHERE status = "active"');
      const [categoriesResult] = await connection.execute('SELECT COUNT(DISTINCT category) as count FROM events');
      const [recentActivityResult] = await connection.execute(
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
}

export function getDb(): Pool {
  if (db) return db;

  if (typeof window !== 'undefined') {
    throw new Error('Database connection can only be established on the server side');
  }

  const dbConfig: PoolOptions = {
    host: process.env.MYSQL_HOST,
    user: process.env.MYSQL_USER,
    password: process.env.MYSQL_PASSWORD,
    database: process.env.MYSQL_DATABASE,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
  };

  db = mysql.createPool(dbConfig);
  return db;
}

export const getDashboardStats = async () => {
  const db = new Database();
  return db.getDashboardStats();
};
