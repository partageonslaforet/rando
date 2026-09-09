// Fonctions spécifiques à la page de détail d'événement
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les boutons
    initializeButtons();
    
    // Initialiser la carte si elle existe
    const mapElement = document.getElementById('event-map');
    if (mapElement && eventData.latitude && eventData.longitude) {
        initializeMap(eventData.latitude, eventData.longitude);
    }
});

function initializeButtons() {
    // Bouton Favoris
    const favoriteBtn = document.querySelector('.btn-favorite');
    if (favoriteBtn) {
        favoriteBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon.classList.contains('bi-heart')) {
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
            } else {
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
            }
        });
    }

    // Bouton Partager
    const shareBtn = document.querySelector('.btn-share');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            if (navigator.share) {
                navigator.share({
                    title: eventData.title,
                    text: eventData.description,
                    url: window.location.href
                }).catch(console.error);
            } else {
                // Fallback pour les navigateurs qui ne supportent pas l'API Web Share
                const tempInput = document.createElement('input');
                tempInput.value = window.location.href;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                alert('Lien copié dans le presse-papier !');
            }
        });
    }
}

function initializeMap(lat, lng) {
    const map = L.map('event-map').setView([lat, lng], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map);
}
