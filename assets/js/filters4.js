// État global des filtres
const currentFilters = {
    period: 'upcoming',
    category: 'all',
    search: '',
    userPosition: null,
    radius: 10
};

let allEvents = [];
let map = null;
let markers = [];
let searchTimeout = null;

// Fonction utilitaire pour obtenir la date locale à minuit
function getLocalMidnight(date) {
    const localDate = new Date(date);
    localDate.setHours(0, 0, 0, 0);
    return localDate;
}

// Chargement des catégories
async function loadCategories() {
    try {
        const categories = await EventsAPI.getCategories();
        const container = document.querySelector('.category-filters');
        
        /* // Bouton "Toutes les catégories"
        let html = `
            <div class="category-filter-group">
                <button class="btn btn-primary active" data-category="all">
                    Toutes
                    <span class="badge badge-light">0</span>
                </button>
        `;
        
        // Générer les boutons pour chaque catégorie
        categories.forEach(category => {
            html += `
                <button class="btn btn-primary" data-category="${category.id}">
                    ${category.name}
                    <span class="badge badge-light">0</span>
                </button>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
 */        
        // Attacher les événements click
        document.querySelectorAll('[data-category]').forEach(button => {
            button.addEventListener('click', async () => {
                currentFilters.category = button.dataset.category;
                document.querySelectorAll('[data-category]').forEach(btn => {
                    btn.classList.toggle('active', btn === button);
                });
                await filterEvents();
            });
        });
    } catch (error) {
        console.error('Erreur lors du chargement des catégories:', error);
    }
}

// Fonction principale de filtrage
async function filterEvents() {
    console.log('🔍 Début filterEvents');
    console.log('État des filtres:', currentFilters);
    
    try {
        // 1. Récupérer les événements
        allEvents = await EventsAPI.getAllEvents({
            category: currentFilters.category
        });
        console.log('📥 Événements reçus:', allEvents.length);

        // 2. Appliquer le filtre temporel
        let filteredEvents = [...allEvents]; // Créer une copie
        const today = getLocalMidnight(new Date());
        
        console.log('📅 Date de référence:', today);
        
        if (currentFilters.period === 'upcoming') {
            console.log('🔍 Application du filtre "upcoming"');
            filteredEvents = filteredEvents.filter(event => {
                const eventDate = getLocalMidnight(new Date(event.date));
                const isUpcoming = eventDate >= today;
                console.log(`Événement ${event.title}: ${eventDate} >= ${today} = ${isUpcoming}`);
                return isUpcoming;
            });
        } else if (currentFilters.period === 'past') {
            filteredEvents = filteredEvents.filter(e => 
                getLocalMidnight(new Date(e.date)) < today
            );
        }

        console.log(`📊 Événements après filtrage temporel: ${filteredEvents.length}/${allEvents.length}`);

        // Autres filtres (distance, recherche)
        filteredEvents = filteredEvents.filter(event => {
            if (currentFilters.userPosition) {
                const distance = calculateDistance(
                    currentFilters.userPosition.lat,
                    currentFilters.userPosition.lng,
                    event.latitude,
                    event.longitude
                );
                if (distance > currentFilters.radius) {
                    return false;
                }
            }
            
            const matchesSearch = !currentFilters.search || 
                event.title.toLowerCase().includes(currentFilters.search.toLowerCase());
            
            return matchesSearch;
        });

        console.log('📊 Événements après filtrage:', {
            total: allEvents.length,
            filtered: filteredEvents.length,
            period: currentFilters.period,
            category: currentFilters.category
        });
        
        // 3. Mise à jour UI
        console.log('📊 Résultat final:', {
            total: allEvents.length,
            filtered: filteredEvents.length
        });
        
        await updateUI(filteredEvents, allEvents);
        
    } catch (error) {
        console.error('❌ Erreur:', error);
    }
}

// Calcul de la distance entre deux points
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Rayon de la Terre en km
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * 
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function toRad(value) {
    return value * Math.PI / 180;
}

// Mise à jour des compteurs
function updateCounters(events) {
    const today = getLocalMidnight(new Date());
    
    // Filtrer d'abord les événements selon le filtre temporel actif
    let filteredEvents = events;
    if (currentFilters.period === 'upcoming') {
        filteredEvents = events.filter(e => getLocalMidnight(new Date(e.date)) >= today);
    } else if (currentFilters.period === 'past') {
        filteredEvents = events.filter(e => getLocalMidnight(new Date(e.date)) < today);
    } else if (currentFilters.period === 'today') {
        filteredEvents = events.filter(e => 
            getLocalMidnight(new Date(e.date)).getTime() === today.getTime()
        );
    }
    
    // Calculer les compteurs
    const counts = {
        all: filteredEvents.length,
        categories: filteredEvents.reduce((acc, event) => {
            if (event.category_id) {
                acc[event.category_id] = (acc[event.category_id] || 0) + 1;
            }
            return acc;
        }, {})
    };

    // Mettre à jour les badges des catégories
    document.querySelectorAll('[data-category]').forEach(button => {
        const categoryId = button.dataset.category;
        const count = categoryId === 'all' ? counts.all : (counts.categories[categoryId] || 0);
        const badge = button.querySelector('.badge');
        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('badge-light', count === 0);
            badge.classList.toggle('badge-primary', count > 0);
        }
    });
}

// Initialisation des filtres temporels
document.querySelectorAll('[data-period]').forEach(button => {
    button.addEventListener('click', async () => {
        currentFilters.period = button.dataset.period;
        document.querySelectorAll('[data-period]').forEach(btn => {
            btn.classList.toggle('active', btn === button);
        });
        await filterEvents();
    });
});

// Initialisation de la recherche
const searchInput = document.querySelector('#search-input');
if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(async () => {
            currentFilters.search = e.target.value.trim();
            await filterEvents();
        }, 300);
    });
}

// Initialisation de la géolocalisation
const locationButton = document.querySelector('#location-button');
if (locationButton) {
    locationButton.addEventListener('click', () => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    currentFilters.userPosition = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    await filterEvents();
                },
                (error) => {
                    console.error('Erreur de géolocalisation:', error);
                }
            );
        }
    });
}

// Initialisation du rayon de recherche
const radiusInput = document.querySelector('#radius-input');
if (radiusInput) {
    radiusInput.addEventListener('change', async (e) => {
        currentFilters.radius = parseInt(e.target.value, 10);
        if (currentFilters.userPosition) {
            await filterEvents();
        }
    });
}

// Mise à jour de l'interface
async function updateUI(filteredEvents, allEvents) {
    console.log('🔄 Mise à jour UI avec', filteredEvents.length, 'événements filtrés');
    
    // 1. Mise à jour des compteurs
    updateCounters(allEvents);

    // 2. Mise à jour de la carte
    if (map) {
        // Effacer les anciens marqueurs
        markers.forEach(marker => marker.remove());
        markers = [];

        // Ajouter les nouveaux marqueurs pour les événements filtrés
        filteredEvents.forEach(event => {
            if (event.latitude && event.longitude) {
                const marker = L.marker([event.latitude, event.longitude])
                    .bindPopup(`
                        <strong>${event.title}</strong><br>
                        Date: ${new Date(event.date).toLocaleDateString()}<br>
                        ${event.description || ''}
                    `);
                marker.addTo(map);
                markers.push(marker);
            }
        });

        // Ajuster la vue de la carte si nécessaire
        if (markers.length > 0) {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    // 3. Mise à jour de la liste des événements
    const eventsContainer = document.querySelector('#eventsList');
    if (eventsContainer) {
        if (filteredEvents.length === 0) {
            eventsContainer.innerHTML = '<div class="alert alert-info">Aucun événement ne correspond à vos critères.</div>';
        } else {
            eventsContainer.innerHTML = filteredEvents.map(event => `
                <div class="event-card">
                    <h3>${event.title}</h3>
                    <p class="event-date">
                        <i class="fas fa-calendar"></i> 
                        ${new Date(event.date).toLocaleDateString()}
                    </p>
                    ${event.description ? `<p class="event-description">${event.description}</p>` : ''}
                    ${event.location ? `
                        <p class="event-location">
                            <i class="fas fa-map-marker-alt"></i> 
                            ${event.location}
                        </p>
                    ` : ''}
                </div>
            `).join('');
        }
    }

    // 4. Mise à jour visuelle des filtres
    document.querySelectorAll('[data-period]').forEach(button => {
        button.classList.toggle('active', button.dataset.period === currentFilters.period);
    });
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', async () => {
    console.log('🚀 Initialisation...');
    
    // Activer visuellement le bouton "upcoming"
    const upcomingButton = document.querySelector('[data-period="upcoming"]');
    if (upcomingButton) {
        upcomingButton.classList.add('active');
    }
    
    // Charger les catégories
    await loadCategories();
    
    // Forcer le premier filtrage
    currentFilters.period = 'upcoming'; // S'assurer que c'est bien défini
    await filterEvents(); // Ceci devrait maintenant filtrer correctement
    
    console.log('✅ Initialisation terminée');
});