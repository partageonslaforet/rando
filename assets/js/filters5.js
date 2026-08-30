// État global des filtres
const currentFilters = {
    period: null,
    category: null,
    search: null
};

// Chargement des événements depuis l'API
async function loadEvents() {
    try {
        const response = await fetch('/api/events.php');
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
        
        updateCounters(events);
        const filteredEvents = events.filter(event => applyFilters(event, currentFilters));
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
        const result = event.category_id === parseInt(filters.category);
        console.log('📑 Filtre catégorie:', {
            category: filters.category,
            eventCategory: event.category_id,
            passed: result
        });
        if (!result) return false;
    }

    if (filters.search) {
        const searchTerm = filters.search.toLowerCase();
        const matchTitle = event.title.toLowerCase().includes(searchTerm);
        const matchOrganisation = event.organisation.toLowerCase().includes(searchTerm);
        if (!matchTitle && !matchOrganisation) {
            return false;
        }
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
        const categoryId = event.category_id;
        categoryCounts.categories[categoryId] = (categoryCounts.categories[categoryId] || 0) + 1;
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

    if (window.eventListFunctions && window.eventListFunctions.updateEventsList) {
        console.log('📝 Mise à jour liste...');
        window.eventListFunctions.updateEventsList(filteredEvents);
    }

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

    // Fonction de recherche
    const handleSearch = async () => {
        currentFilters.search = searchInput.value.trim();
        await filterEvents();
    };

    // Recherche sur saisie (avec debounce)
    let debounceTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(handleSearch, 300);
    });

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

    // 5. Premier chargement
    filterEvents();

});