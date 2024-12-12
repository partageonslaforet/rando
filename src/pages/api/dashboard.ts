import { NextApiRequest, NextApiResponse } from 'next';
import { getDashboardStats } from '@/api/dashboard';

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  if (req.method !== 'GET') {
    return res.status(405).json({ message: 'Method not allowed' });
  }

  try {
    const stats = await getDashboardStats();
    res.status(200).json(stats);
  } catch (error) {
    console.error('Dashboard API Error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
}
