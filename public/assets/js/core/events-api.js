/**
 * Fichier: /assets/js/core/events-api.js
 * Rôle: Client API des événements (liste paginée, comptages, catégories).
 * Utilisation: expose `window.EventsAPI` consommé par carte, filtres, init, calendrier.
 * Dépendances: Fetch API, endpoints /api/events/events-counts.php, /api/events/categories.php.
 */
// Définition de l'API des événements
const EventsAPI = {
    // URL de base de l'API
    baseUrl: '/api/events/events-counts.php',
    
    // Cache pour les compteurs
    _cachedCounts: null,

    // Fonction pour charger les événements avec des filtres
    async getAllEvents(filters = {}) {
        console.log('📡 Chargement des événements depuis l\'API avec filtres:', filters);
        try {
            const params = new URLSearchParams();
            
            // On ne garde que category comme filtre pour l'API
            if (filters.category !== 'all' && filters.category) {
                params.append('category', filters.category);
            }
        
            const url = `${this.baseUrl}?${params}`;
            console.log('🔗 URL de l\'API:', url);
            
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
        
            const result = await response.json();
            console.log('📦 Réponse brute de l\'API:', result);
            console.log('🔍 Structure de la réponse:', {
                hasData: 'data' in result,
                hasEvents: 'events' in result,
                type: result.data ? 'data' : (result.events ? 'events' : 'unknown')
            });
            
            return result.data || result.events || [];
        } catch (error) {
            console.error('❌ Erreur lors du chargement des événements:', error);
            throw error;
        }
    },

    // Fonction pour charger un événement spécifique
    async getEventById(eventId) {
        console.log('📡 Chargement de l\'événement:', eventId);
        try {
            const response = await fetch(`${this.baseUrl}?id=${eventId}`);
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }

            const result = await response.json();
            console.log('✅ Événement chargé:', result);
            return result.data;

        } catch (error) {
            console.error('❌ Erreur lors du chargement de l\'événement:', error);
            throw error;
        }
    },

    // Fonction pour charger les compteurs d'événements
    async loadEventCounts() {
        if (this._cachedCounts && (Date.now() - this._cachedCounts.timestamp) < 30000) {
            console.log('📡 Utilisation des compteurs en cache');
            return this._cachedCounts.data;
        }

        console.log('📡 Chargement des compteurs d\'événements');
        try {
            const response = await fetch(`${this.baseUrl}?count=true`);
            if (!response.ok) {
                const defaultCounts = {
                    all: 0,
                    upcoming: 0,
                    past: 0,
                    categories: {}
                };
                this._cachedCounts = {
                    data: defaultCounts,
                    timestamp: Date.now()
                };
                console.warn('⚠️ Impossible de charger les compteurs, utilisation des valeurs par défaut');
                return defaultCounts;
            }

            const result = await response.json();
            console.log('✅ Compteurs chargés:', result);
            
            this._cachedCounts = {
                data: result.counts || {},
                timestamp: Date.now()
            };
            return this._cachedCounts.data;

        } catch (error) {
            console.error('❌ Erreur lors du chargement des compteurs:', error);
            const defaultCounts = {
                all: 0,
                upcoming: 0,
                past: 0,
                categories: {}
            };
            this._cachedCounts = {
                data: defaultCounts,
                timestamp: Date.now()
            };
            return defaultCounts;
        }
    },

    // Méthode pour forcer le rechargement des compteurs
    async refreshEventCounts() {
        this._cachedCounts = null;
        return await this.loadEventCounts();
    },

    // Nouvelle méthode pour charger les catégories
    async getCategories() {
        try {
            const response = await fetch('/api/events/categories.php');
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            const result = await response.json();
            return result.data || [];
        } catch (error) {
            console.error('❌ Erreur lors du chargement des catégories:', error);
            return [];
        }
    },

    // Nouvelle méthode paginée: retourne { data, pagination }
    async getAllEventsPaged(filters = {}, page = 1, limit = 12) {
        console.log('📡 Chargement paginé des événements', { filters, page, limit });
        const params = new URLSearchParams();
        // Support minimal des filtres utiles
        if (filters.category && filters.category !== 'all') params.append('category', filters.category);
        if (filters.period && filters.period !== 'all') params.append('period', filters.period);
        if (filters.search) params.append('search', filters.search);
        params.append('page', page);
        params.append('limit', limit);

        const url = `${this.baseUrl}?${params}`;
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        const result = await response.json();
        return {
            data: result.data || [],
            pagination: result.pagination || null,
            counts: result.counts || {}
        };
    }
};

// Exposer l'API globalement
window.EventsAPI = EventsAPI;

// Maintenir la rétrocompatibilité avec l'ancien nom
window.eventsApi = {
    loadEvents: EventsAPI.getAllEvents.bind(EventsAPI),
    loadEventsPaged: EventsAPI.getAllEventsPaged.bind(EventsAPI),
    loadEventById: EventsAPI.getEventById.bind(EventsAPI),
    loadEventCounts: EventsAPI.loadEventCounts.bind(EventsAPI),
    getCategories: EventsAPI.getCategories.bind(EventsAPI),
    baseUrl: EventsAPI.baseUrl
};

// L'initialisation est maintenant gérée par init.js