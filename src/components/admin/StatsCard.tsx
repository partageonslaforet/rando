import React from 'react';
import { LucideIcon } from 'lucide-react';

interface StatsCardProps {
  title: string;
  value: number;
  icon: LucideIcon;
  trend?: number;
  trendColor?: string;
}

export function StatsCard({ 
  title, 
  value, 
  icon: Icon,
  trend,
  trendColor = trend && trend > 0 ? 'text-green-500' : 'text-red-500'
}: StatsCardProps) {
  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-gray-500 dark:text-gray-400">{title}</h3>
        <div className="p-2 bg-[#990047] bg-opacity-10 rounded-lg">
          <Icon size={20} className="text-[#990047]" />
        </div>
      </div>
      <div className="flex items-end gap-2">
        <p className="text-2xl font-bold dark:text-white">{value}</p>
        {trend && (
          <p className={`text-sm ${trendColor}`}>
            {trend > 0 ? '+' : ''}{trend}%
          </p>
        )}
      </div>
    </div>
  );
}