// Variables globales pour les parcours
let routeCounter = 0;

// Fonction pour ajouter un nouveau parcours
function addRoute() {
    const container = document.getElementById('routesContainer');
    if (!container) {
        console.error('❌ Conteneur de routes non trouvé');
        return;
    }

    const routeTemplate = `
        <div class="route-item card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Parcours ${routeCounter + 1}</h5>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRoute(this)">
                        <i class="bi bi-trash"></i> Supprimer
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom du parcours</label>
                        <input type="text" class="form-control" name="routes[${routeCounter}][name]" placeholder="Nom du parcours">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Distance (km)</label>
                        <input type="number" step="0.1" class="form-control" name="routes[${routeCounter}][distance]" placeholder="Distance">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dénivelé (m)</label>
                        <input type="number" class="form-control" name="routes[${routeCounter}][elevationGain]" placeholder="Dénivelé">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="routes[${routeCounter}][description]" rows="3" placeholder="Description du parcours"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Fichier GPX</label>
                        <input type="file" class="form-control" name="routes[${routeCounter}][gpx]" accept=".gpx" onchange="handleGpxFileSelect(this, ${routeCounter})">
                    </div>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', routeTemplate);
    routeCounter++;
    updateRouteNumbers();
}

// Fonction pour supprimer un parcours
function removeRoute(button) {
    const routeItem = button.closest('.route-item');
    if (routeItem) {
        routeItem.remove();
        updateRouteNumbers();
        updateRouteIndexes();
    }
}

// Fonction pour mettre à jour les numéros des parcours
function updateRouteNumbers() {
    const routes = document.querySelectorAll('.route-item');
    routes.forEach((route, index) => {
        const title = route.querySelector('.card-title');
        if (title) {
            title.textContent = `Parcours ${index + 1}`;
        }
    });
    routeCounter = routes.length;
}

// Fonction pour mettre à jour les index des champs
function updateRouteIndexes() {
    const routes = document.querySelectorAll('.route-item');
    routes.forEach((route, index) => {
        // Mettre à jour les noms des champs
        const inputs = route.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/routes\[\d+\]/, `routes[${index}]`));
            }
        });
    });
}

// Export des fonctions
window.addRoute = addRoute;
window.removeRoute = removeRoute;
window.updateRouteNumbers = updateRouteNumbers;
window.updateRouteIndexes = updateRouteIndexes;
