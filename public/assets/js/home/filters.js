/**
 * Fichier: /assets/js/home/filters.js
 * Rôle: Gestion des filtres (période, catégorie, recherche) et de l’état `window.currentFilters`.
 * Utilisation: met à jour les compteurs et déclenche le rendu paginé via EventsAPI.
 * Dépendances: window.EventsAPI, DOM (boutons data-period/data-category, champ recherche).
 */
// État global des filtres (exposé sur window pour accès inter-scripts)
window.currentFilters = window.currentFilters || {
    period: null,
    category: null,
    search: null
};
const currentFilters = window.currentFilters;

// Chargement des événements depuis l'API
async function loadEvents() {
    try {
        const response = await fetch('/api/events/events-counts.php');
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const data = await response.json();
        return data.data || [];
    } catch (error) {
        console.error('❌ Erreur lors du chargement des événements:', error);
        return [];
    }
}

// Fonction principale de filtrage
async function filterEvents() {
    try {
        const events = await loadEvents();
        console.log('📊 Événements chargés:', events);
        
        // 1. D'abord, filtrer par recherche si elle existe
        let filteredEvents = events;
        if (currentFilters.search) {
            // Contexte recherché: activer "À venir" et désactiver catégorie
            currentFilters.period = 'upcoming';
            currentFilters.category = null;
            
            const searchTerm = String(currentFilters.search || '').toLowerCase();
            filteredEvents = events.filter(event => {
                const title = String(event.title || '').toLowerCase();
                const organisation = String(event.organisation || '').toLowerCase();
                const location = String(event.location || '').toLowerCase();
                const venue = String(event.venue || '').toLowerCase();
                const meetingName = String(event.meeting_name || '').toLowerCase();
                const meetingAddress = String(event.meeting_address || '').toLowerCase();
                const meetingCity = String(event.meeting_city || '').toLowerCase();
                return (
                    title.includes(searchTerm)
                    || organisation.includes(searchTerm)
                    || location.includes(searchTerm)
                    || venue.includes(searchTerm)
                    || meetingName.includes(searchTerm)
                    || meetingAddress.includes(searchTerm)
                    || meetingCity.includes(searchTerm)
                );
            });
        }

        // 2. Mettre à jour les compteurs sur l'ensemble des événements (indépendant de la recherche)
        updateCounters(events);
        
        // 3. Mettre à jour les classes actives des filtres (visuel bouton)
        document.querySelectorAll('[data-period]').forEach(button => {
            const isActive = button.dataset.period === currentFilters.period;
            button.classList.toggle('active', isActive);
            button.classList.toggle('btn-primary', isActive);
            button.classList.toggle('btn-light', !isActive);
        });

        document.querySelectorAll('[data-category]').forEach(button => {
            const isActive = button.dataset.category === currentFilters.category;
            button.classList.toggle('active', isActive);
            // Optionnel: refléter visuellement avec Bootstrap
            button.classList.toggle('btn-primary', isActive);
            button.classList.toggle('btn-light', !isActive);
        });
        
        // 4. Appliquer les autres filtres
        filteredEvents = filteredEvents.filter(event => applyFilters(event, currentFilters));
        
        console.log('🔍 Après filtrage:', {
            total: events.length,
            filtered: filteredEvents.length,
            filters: currentFilters,
            events: filteredEvents
        });
        
        updateUI(filteredEvents);
        return filteredEvents;
    } catch (error) {
        console.error('❌ Erreur dans filterEvents:', error);
        return [];
    }
}

// Application des filtres
function applyFilters(event, filters) {
    console.log('🔍 Valeurs des filtres:', filters);
    console.log('⚡ Application des filtres sur:', {
        event,
        filters,
        date: new Date(event.date)
    });
    
    const eventDate = new Date(event.date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Vérification des filtres
    if (filters.period) {
        const result = (() => {
            switch (filters.period) {
                case 'upcoming':
                    return eventDate >= today;
                case 'today':
                    return eventDate.toDateString() === today.toDateString();
                case 'past':
                    return eventDate < today;
                default:
                    return true;
            }
        })();
        console.log('🕒 Filtre temporel:', {
            period: filters.period,
            eventDate,
            today,
            passed: result
        });
        if (!result) return false;
    }

    if (filters.category && filters.category !== 'all') {
        const eventCategories = event.category_ids || (event.category_id ? [event.category_id] : []);
        const result = eventCategories.includes(parseInt(filters.category));
        console.log('📑 Filtre catégorie:', {
            category: filters.category,
            eventCategory: eventCategories,
            passed: result
        });
        if (!result) return false;
    }

    if (filters.search) {
        const searchTerm = String(filters.search || '').toLowerCase();
        const title = String(event.title || '').toLowerCase();
        const organisation = String(event.organisation || '').toLowerCase();
        const location = String(event.location || '').toLowerCase();
        const venue = String(event.venue || '').toLowerCase();
        const meetingName = String(event.meeting_name || '').toLowerCase();
        const meetingAddress = String(event.meeting_address || '').toLowerCase();
        const meetingCity = String(event.meeting_city || '').toLowerCase();
        const anyMatch = (
            title.includes(searchTerm)
            || organisation.includes(searchTerm)
            || location.includes(searchTerm)
            || venue.includes(searchTerm)
            || meetingName.includes(searchTerm)
            || meetingAddress.includes(searchTerm)
            || meetingCity.includes(searchTerm)
        );
        if (!anyMatch) return false;
    }


    return true;
}

// Mise à jour des compteurs
function updateCounters(allEvents) {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    // 1. Compteurs temporels (basés sur TOUS les événements)
    const temporalCounts = {
        all: allEvents.length,
        upcoming: 0,
        today: 0,
        past: 0
    };

    allEvents.forEach(event => {
        const eventDate = new Date(event.date);
        if (eventDate >= today) temporalCounts.upcoming++;
        if (eventDate.toDateString() === today.toDateString()) temporalCounts.today++;
        if (eventDate < today) temporalCounts.past++;
    });

    // Mise à jour des badges temporels
    document.querySelectorAll('[data-period]').forEach(button => {
        const period = button.dataset.period;
        const badge = button.querySelector('.badge');
        const count = temporalCounts[period] || 0;
        if (badge) badge.textContent = count;
        button.classList.toggle('active', period === currentFilters.period);
        button.classList.toggle('disabled', count === 0);
    });

    // 2. Compteurs de catégories (basés sur les événements de la période sélectionnée)
    const eventsInPeriod = allEvents.filter(event => {
        const eventDate = new Date(event.date);
        switch(currentFilters.period) {
            case 'upcoming': return eventDate >= today;
            case 'today': return eventDate.toDateString() === today.toDateString();
            case 'past': return eventDate < today;
            default: return true;
        }
    });

    const categoryCounts = {
        all: eventsInPeriod.length,
        categories: {}
    };

    eventsInPeriod.forEach(event => {
        const categoryIds = event.category_ids || (event.category_id ? [event.category_id] : []);
        categoryIds.forEach(categoryId => {
            categoryCounts.categories[categoryId] = (categoryCounts.categories[categoryId] || 0) + 1;
        });
    });

    // Mise à jour des badges de catégories
    document.querySelectorAll('[data-category]').forEach(button => {
        const categoryId = button.dataset.category;
        const count = categoryId === 'all' ? categoryCounts.all : (categoryCounts.categories[categoryId] || 0);
        const badge = button.querySelector('.badge');
        if (badge) badge.textContent = count;
        button.classList.toggle('active', categoryId === currentFilters.category);
        button.classList.toggle('disabled', count === 0);
    });
}

// Mise à jour de l'interface
async function updateUI(filteredEvents) {
    console.log('🎨 Mise à jour UI avec:', filteredEvents);
    console.log('🎯 updateUI appelée avec:', filteredEvents); // Ajout de ce log
    console.log('🎨 Mise à jour UI avec:', filteredEvents);
    
    if (window.mapFunctions && window.mapFunctions.updateMapMarkers) {
        console.log('🗺️ Mise à jour carte...');
        window.mapFunctions.updateMapMarkers(filteredEvents);
    }

    // Rendu de la liste: utiliser l'API paginée avec les filtres courants
    const containerEl = document.getElementById('events-container');
    let perPage = 5;
    try {
        const stored = localStorage.getItem('events_per_page');
        if (stored && !Number.isNaN(parseInt(stored, 10))) {
            perPage = parseInt(stored, 10);
        } else if (containerEl && containerEl.dataset.pageSize) {
            const v = parseInt(containerEl.dataset.pageSize, 10);
            if (!Number.isNaN(v) && v > 0) perPage = v;
        }
    } catch (e) { /* ignore */ }

    const apiFilters = {};
    if (currentFilters.period) apiFilters.period = currentFilters.period;
    if (currentFilters.category && currentFilters.category !== 'all') apiFilters.category = currentFilters.category;
    if (currentFilters.search) apiFilters.search = currentFilters.search;

    const loadPaged = (page = 1, limit = perPage) => {
        if (!window.EventsAPI || typeof window.EventsAPI.getAllEventsPaged !== 'function') {
            if (window.eventListFunctions && typeof window.eventListFunctions.updateEventsList === 'function') {
                window.eventListFunctions.updateEventsList(filteredEvents);
            }
            return Promise.resolve();
        }
        console.log('� Chargement liste paginée avec filtres', { apiFilters, page, limit });
        return window.EventsAPI.getAllEventsPaged(apiFilters, page, limit).then(({ data, pagination }) => {
            if (window.eventListFunctions && typeof window.eventListFunctions.updateEventsList === 'function') {
                window.eventListFunctions.updateEventsList(data || []);
            }
            if (window.eventListFunctions && typeof window.eventListFunctions.renderPagination === 'function') {
                window.eventListFunctions.renderPagination(pagination, (newPage) => loadPaged(newPage, limit));
            }
            if (window.eventListFunctions && typeof window.eventListFunctions.renderPerPageControl === 'function') {
                window.eventListFunctions.renderPerPageControl(limit, (newPerPage) => {
                    try { localStorage.setItem('events_per_page', String(newPerPage)); } catch (e) {}
                    perPage = newPerPage;
                    loadPaged(1, newPerPage);
                });
            }
        }).catch(err => {
            console.error('❌ Erreur chargement paginé filtré:', err);
            if (window.eventListFunctions && typeof window.eventListFunctions.updateEventsList === 'function') {
                window.eventListFunctions.updateEventsList(filteredEvents || []);
            }
        });
    };

    loadPaged(1, perPage);

    const eventsContainer = document.querySelector('#eventsList');
    if (eventsContainer) {
        try {
            console.log('📝 Envoi au template PHP...');
            const response = await fetch('/templates/components/events/events-list.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    filters: currentFilters
                })
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const html = await response.text();
            console.log('✅ HTML reçu:', html.substring(0, 100) + '...');
            eventsContainer.innerHTML = html;
        } catch (error) {
            console.error('❌ Erreur mise à jour liste:', error);
        }
    }
}

// Gestionnaires d'événements
document.addEventListener('DOMContentLoaded', async () => {
    // Indiquer que les filtres pilotent la liste
    window.__filtersDrivesList = true;
    // 1. Initialisation avec "upcoming" par défaut
    currentFilters.period = 'upcoming';
    await filterEvents();
    
    // 2. Écouteurs pour les filtres temporels
    document.querySelectorAll('[data-period]').forEach(button => {
        button.addEventListener('click', async () => {
            if (button.classList.contains('disabled')) return;
            
            const period = button.dataset.period;
            currentFilters.period = button.dataset.period;
            await filterEvents();
        });
    });

    // 3. Écouteurs pour les filtres de catégories
    document.querySelectorAll('[data-category]').forEach(button => {
        button.addEventListener('click', async () => {
            if (button.classList.contains('disabled')) return;
            
            const category = button.dataset.category;
            currentFilters.category = category === currentFilters.category ? null : category;
            console.log('🎯 Catégorie sélectionnée:', currentFilters.category); 
            await filterEvents();
        });
    });

    // 4. Écouteurs pour la recherche
    const searchInput = document.getElementById('searchInput');
    const resetButton = document.querySelector('.reset-button');
    const searchButton = document.getElementById('searchButton');

    // Vérifier que les éléments existent avant d'ajouter les écouteurs
    if (searchInput && resetButton && searchButton) {
        // Fonction de recherche
        const handleSearch = async () => {
            const term = (searchInput.value || '').trim();
            currentFilters.search = term;
            // Forcer le filtre période "À venir" et vider la catégorie
            currentFilters.period = 'upcoming';
            currentFilters.category = null;

            // Lance le filtrage + mise à jour UI (paginée)
            const results = await filterEvents();

            // Afficher un toast avec le nombre total (via pagination de l'API)
            try {
                let total = Array.isArray(results) ? results.length : 0;
                if (window.EventsAPI && typeof window.EventsAPI.getAllEventsPaged === 'function') {
                    const { pagination } = await window.EventsAPI.getAllEventsPaged(
                        { search: term, period: 'upcoming' },
                        1,
                        1
                    );
                    if (pagination && typeof pagination.total === 'number') {
                        total = pagination.total;
                    }
                }
                if (typeof showToast === 'function') {
                    const toastCls = total > 0 ? 'bg-success' : 'bg-danger';
                    showToast(`${total} résultat${total > 1 ? 's' : ''}`, toastCls);
                }
            } catch (e) {
                console.warn('Impossible d’afficher le toast du total', e);
            }

            // Vider le champ de recherche après exécution
            searchInput.value = '';

            // Aller directement à la liste
            const list = document.getElementById('events-container');
            if (list && typeof list.scrollIntoView === 'function') {
                list.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // Forcer visuellement le filtre "À venir" actif
            document.querySelectorAll('[data-period]').forEach(btn => {
                const is = btn.dataset.period === 'upcoming';
                btn.classList.toggle('active', is);
                btn.classList.toggle('btn-primary', is);
                btn.classList.toggle('btn-light', !is);
            });
        };

        // Recherche sur clic du bouton
        searchButton.addEventListener('click', handleSearch);

        // Recherche sur Enter
        searchInput.addEventListener('keypress', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                await handleSearch();
            }
        });

        // Reset de la recherche
        resetButton.addEventListener('click', async () => {
            searchInput.value = '';
            currentFilters.search = null;
            await filterEvents();
        });
    } else {
        console.warn('Éléments de recherche non trouvés dans le DOM');
    }


    // 5. Écouteur pour les clics sur le calendrier
    document.querySelectorAll('.calendar-day.has-events').forEach(day => {
        day.addEventListener('click', async () => {
            // Réinitialiser les autres filtres
            currentFilters.period = null;
            currentFilters.category = null;
            currentFilters.search = null;
            
            // Définir la date sélectionnée
            currentFilters.selectedDate = day.getAttribute('data-date');
            
            // Réinitialiser le champ de recherche si présent
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.value = '';
            }
            
            await filterEvents();
        });
    });

    // 6. Premier chargement
    filterEvents();

});