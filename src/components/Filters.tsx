import { useState, ChangeEvent } from 'react';
import { RotateCcw } from 'lucide-react';
import type { FilterState } from '../types/filters';

interface FiltersProps {
  filters: FilterState;
  onFilterChange: (filters: FilterState) => void;
  onSearchReset?: () => void;
  totalEvents: number;
  eventCountByCategory: Record<string, number>;
}

const timeFilterOptions = [
  { id: 'upcoming', label: 'À venir' },
  { id: 'today', label: "Aujourd'hui" },
  { id: 'past', label: 'Passés' },
];

const categories = [
  { id: 'all', label: 'Tous' },
  { id: 'hiking', label: 'Randonnée' },
  { id: 'running', label: 'Course à pied' },
  { id: 'cycling', label: 'Vélo' },
  { id: 'other', label: 'Autre' },
];

export function Filters({ filters, onFilterChange, onSearchReset, totalEvents, eventCountByCategory }: FiltersProps) {
  const handleTimeFilterChange = (value: 'upcoming' | 'today' | 'past') => {
    onFilterChange({
      ...filters,
      timeFilter: value,
    });
  };

  const handleCategoryChange = (categoryId: string) => {
    onFilterChange({
      ...filters,
      categories: categoryId === 'all' ? [] : [...filters.categories, categoryId],
    });
  };

  const handleProximityToggle = () => {
    onFilterChange({
      ...filters,
      proximity: {
        ...filters.proximity,
        enabled: !filters.proximity.enabled,
      },
    });
  };

  const handleRadiusChange = (radius: number) => {
    onFilterChange({
      ...filters,
      proximity: {
        ...filters.proximity,
        radius,
      },
    });
  };

  const resetFilters = () => {
    const defaultFilters: FilterState = {
      timeFilter: 'upcoming',
      categories: [],
      proximity: {
        enabled: false,
        radius: 10,
        userLocation: null
      }
    };
    onFilterChange(defaultFilters);
    onSearchReset?.();
  };

  return (
    <div className="space-y-6">
      {/* En-tête des filtres */}
      <div className="flex justify-between items-center bg-white shadow-sm rounded-xl p-4">
        <h2 className="text-lg font-semibold text-secondary">
          {totalEvents} événement{totalEvents !== 1 ? 's' : ''} trouvé{totalEvents !== 1 ? 's' : ''}
        </h2>
        <button
          onClick={resetFilters}
          className="flex items-center gap-2 px-3 py-2 text-sm text-secondary hover:text-secondary-dark transition-colors"
          aria-label="Réinitialiser tous les filtres"
        >
          <RotateCcw size={16} className="text-secondary" />
          Réinitialiser
        </button>
      </div>

      {/* Filtres temporels */}
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrer par période">
        {timeFilterOptions.map(option => {
          const isSelected = filters.timeFilter === option.id;
          return (
            <button
              key={option.id}
              onClick={() => handleTimeFilterChange(option.id as 'upcoming' | 'today' | 'past')}
              className={`px-4 py-2 rounded-md text-sm ${
                isSelected
                  ? 'bg-primary text-white'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
              }`}
              role="switch"
              aria-checked={isSelected ? 'true' : 'false'}
              aria-label={`Filtre temporel : ${option.label}`}
            >
              {option.label}
            </button>
          );
        })}
      </div>

      {/* Catégories */}
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrer par catégorie">
        {categories.map(category => {
          const isSelected = category.id === 'all' 
            ? filters.categories.length === 0 
            : filters.categories.includes(category.id);
          return (
            <button
              key={category.id}
              onClick={() => handleCategoryChange(category.id)}
              className={`flex items-center gap-2 px-4 py-2 rounded-md text-sm ${
                isSelected
                  ? category.id === 'all'
                    ? 'bg-primary text-white'
                    : 'bg-secondary text-white'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
              }`}
              role="switch"
              aria-checked={isSelected ? 'true' : 'false'}
              aria-label={`Filtre catégorie : ${category.label}`}
            >
              <span>{category.label}</span>
              {category.id !== 'all' && (
                <span 
                  className={`inline-flex items-center justify-center px-2 py-1 text-xs font-medium rounded-full ${
                    isSelected
                      ? 'bg-white bg-opacity-20 text-white'
                      : 'bg-primary text-white'
                  }`}
                  aria-label={`${eventCountByCategory[category.id] || 0} événements`}
                >
                  {eventCountByCategory[category.id] || 0}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* Filtre de proximité */}
      <div className={`space-y-3 ${!filters.proximity.enabled ? 'opacity-50' : ''}`}>
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300">Proximité</h3>
          <button
            onClick={handleProximityToggle}
            className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${
              filters.proximity.enabled ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-700'
            }`}
            role="switch"
            aria-checked={filters.proximity.enabled ? 'true' : 'false'}
            aria-label="Activer le filtre de proximité"
            title="Activer/désactiver le filtre de proximité"
          >
            <span
              className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                filters.proximity.enabled ? 'translate-x-5' : 'translate-x-0'
              }`}
            />
          </button>
        </div>

        <div className={`space-y-2 transition-opacity duration-200 ${!filters.proximity.enabled ? 'pointer-events-none' : ''}`}>
          <div className="flex items-center justify-between">
            <span className="text-sm text-gray-500 dark:text-gray-400">
              Rayon : {filters.proximity.radius} km
            </span>
          </div>
          <label htmlFor="proximity-radius" className="sr-only">Rayon de recherche en kilomètres</label>
          <input
            type="range"
            min={1}
            max={100}
            value={filters.proximity.radius}
            onChange={(e: ChangeEvent<HTMLInputElement>) => handleRadiusChange(Number(e.target.value))}
            className="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-primary"
            disabled={!filters.proximity.enabled}
            id="proximity-radius"
            aria-label="Rayon de recherche en kilomètres"
            aria-valuemin={1}
            aria-valuemax={100}
            aria-valuenow={filters.proximity.radius}
            aria-valuetext={`${filters.proximity.radius} kilomètres`}
          />
          <div className="flex justify-between text-xs text-gray-500 dark:text-gray-400">
            <span>1 km</span>
            <span>100 km</span>
          </div>
        </div>
      </div>
    </div>
  );
}
