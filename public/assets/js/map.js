// Initialisation de la carte
let map;

document.addEventListener('DOMContentLoaded', function() {
    // Création de la carte centrée sur la Belgique
    map = L.map('map').setView([50.5039, 4.4699], 8);

    // Ajout de la couche OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Récupération des événements depuis le data-attribute
    const mapContainer = document.getElementById('map');
    const events = JSON.parse(mapContainer.dataset.events || '[]');

    // Ajout des marqueurs pour chaque événement
    events.forEach(event => {
        if (event.latitude && event.longitude) {
            const marker = L.marker([event.latitude, event.longitude])
                .addTo(map)
                .bindPopup(`
                    <h5>${event.title}</h5>
                    <p>${event.date}</p>
                    <a href="/event.php?id=${event.id}" class="btn btn-primary btn-sm">Voir détails</a>
                `);
        }
    });
});
