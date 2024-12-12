import mysql, { Pool, PoolOptions } from 'mysql2/promise';

class Database {
  private static instance: Database;
  private pool: Pool | null = null;

  private constructor() {}

  public static getInstance(): Database {
    if (!Database.instance) {
      Database.instance = new Database();
    }
    return Database.instance;
  }

  public async getConnection(): Promise<Pool> {
    if (this.pool) return this.pool;

    const config: PoolOptions = {
      host: process.env.MYSQL_HOST,
      user: process.env.MYSQL_USER,
      password: process.env.MYSQL_PASSWORD,
      database: process.env.MYSQL_DATABASE,
      waitForConnections: true,
      connectionLimit: 10,
      queueLimit: 0
    };

    this.pool = mysql.createPool(config);
    return this.pool;
  }
}

// Export une instance unique
export const db = Database.getInstance();
