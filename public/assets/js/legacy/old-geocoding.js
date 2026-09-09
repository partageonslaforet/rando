// Fonction pour convertir une adresse en coordonnées via Nominatim
async function geocodeAddress(address) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`);
        const data = await response.json();
        
        if (data && data.length > 0) {
            return {
                lat: parseFloat(data[0].lat),
                lon: parseFloat(data[0].lon)
            };
        }
        return null;
    } catch (error) {
        console.error('Erreur de géocodage:', error);
        return null;
    }
}

// Fonction pour ajouter un délai entre les requêtes (pour respecter les limites de l'API)
function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Fonction principale pour initialiser la carte avec les adresses
async function initMapWithAddresses() {
    // Création de la carte centrée sur la Belgique
    map = L.map('map').setView([50.5039, 4.4699], 8);

    // Ajout de la couche OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Récupération des événements
    const mapContainer = document.getElementById('map');
    const events = JSON.parse(mapContainer.dataset.events || '[]');

    // Traitement de chaque événement
    for (const event of events) {
        if (event.address) {
            // Ajout d'un délai de 1 seconde entre chaque requête pour respecter les limites de l'API
            await delay(1000);
            
            const coords = await geocodeAddress(event.address);
            if (coords) {
                const marker = L.marker([coords.lat, coords.lon])
                    .addTo(map)
                    .bindPopup(`
                        <h5>${event.title}</h5>
                        <p>${event.date}</p>
                        <p><i class="bi bi-geo-alt"></i> ${event.address}</p>
                        <a href="/event.php?id=${event.id}" class="btn btn-success btn-sm">Voir détails</a>
                    `);
            }
        }
    }
}
