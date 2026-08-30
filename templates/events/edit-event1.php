<?php
?>
<script>
</script>
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/Models/Event.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
$eventManager = new Event($pdo);
$isEdit = false;
$event = null;

// Récupérer l'événement si on est en mode édition
if (isset($_GET['id'])) {
    $eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($eventId) {
        try {
            $event = $eventManager->getById($eventId);
            if ($event) {
                ?>
                <script>
                </script>
                <?php


                // Vérifier que l'utilisateur est l'organisateur
                if ($event['organizer_id'] !== $_SESSION['user_id']) {
                    $_SESSION['error'] = 'Vous n\'êtes pas autorisé à modifier cet événement';
                    header('Location: /templates/events/event-detail.php?id=' . $eventId);  // Redirection vers la page de détail
                    exit;
                }
                $isEdit = true;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /templates/events/events.php');
            exit;
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">
                        <?php echo $isEdit ? 'Modifier l\'événement' : 'Créer un événement'; ?>
                    </h2>

                    <!-- Messages d'erreur/succès -->
                    <div id="eventMessage" class="alert d-none"></div>

                    <form id="eventForm" method="POST" enctype="multipart/form-data">
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                        <?php endif; ?>

                        <!-- Informations de base -->
                        <div class="mb-4">
                            <h5>Informations de base</h5>
                            <div class="mb-3">
                                <label for="title" class="form-label">Titre *</label>
                                <input type="text" class="form-control" id="title" name="title" required
                                       value="<?php echo $isEdit ? htmlspecialchars($event['title']) : ''; ?>"
                                       minlength="5" maxlength="255">
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="5" required minlength="20"><?php echo $isEdit ? 
                                          htmlspecialchars($event['description']) : ''; ?></textarea>
                                <div class="form-text">
                                    Décrivez l'événement, le parcours, le niveau requis, etc.
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Catégorie *</label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">Choisir...</option>
                                            <option value="running" <?php echo $isEdit && 
                                                $event['category'] === 'running' ? 'selected' : ''; ?>>
                                                Course à pied
                                            </option>
                                            <option value="hiking" <?php echo $isEdit && 
                                                $event['category'] === 'hiking' ? 'selected' : ''; ?>>
                                                Randonnée
                                            </option>
                                            <option value="cycling" <?php echo $isEdit && 
                                                $event['category'] === 'cycling' ? 'selected' : ''; ?>>
                                                Vélo
                                            </option>
                                            <option value="other" <?php echo $isEdit && 
                                                $event['category'] === 'other' ? 'selected' : ''; ?>>
                                                Autre
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="difficulty" class="form-label">Difficulté</label>
                                        <select class="form-select" id="difficulty" name="difficulty">
                                            <option value="">Choisir...</option>
                                            <option value="easy" <?php echo $isEdit && 
                                                $event['difficulty'] === 'easy' ? 'selected' : ''; ?>>
                                                Facile
                                            </option>
                                            <option value="medium" <?php echo $isEdit && 
                                                $event['difficulty'] === 'medium' ? 'selected' : ''; ?>>
                                                Moyen
                                            </option>
                                            <option value="hard" <?php echo $isEdit && 
                                                $event['difficulty'] === 'hard' ? 'selected' : ''; ?>>
                                                Difficile
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date et heure -->
                        <div class="mb-4">
                            <h5>Date et heure</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="date" class="form-label">Date *</label>
                                        <input type="date" class="form-control" id="date" name="date" required
                                               min="<?php echo date('Y-m-d'); ?>"
                                               value="<?php echo $isEdit ? $event['date'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="start_time" class="form-label">Heure de début *</label>
                                        <input type="time" class="form-control" id="start_time" 
                                               name="start_time" required
                                               value="<?php echo $isEdit ? $event['start_time'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="end_time" class="form-label">Heure de fin</label>
                                        <input type="time" class="form-control" id="end_time" 
                                               name="end_time"
                                               value="<?php echo $isEdit ? $event['end_time'] : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Localisation -->
                        <div class="mb-4">
                            <h5>Localisation</h5>
                            <div class="mb-3">
                                <label for="location" class="form-label">Lieu de rendez-vous *</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       required
                                       value="<?php echo $isEdit ? 
                                       htmlspecialchars($event['location']) : ''; ?>">
                                <div class="form-text">
                                    Précisez un lieu facilement identifiable
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="venue" class="form-label">Local (optionnel)</label>
                                <input type="text" class="form-control" id="venue" name="venue" 
                                       value="<?php echo $isEdit ? 
                                       htmlspecialchars($event['venue']) : ''; ?>">
                                <div class="form-text">
                                    Nom du local ou lieu spécifique (salle, club, etc.)
                                </div>
                            </div>
                            <div id="map" style="height: 300px;" class="mb-3"></div>
                            <input type="hidden" id="coordinates" name="coordinates" 
                                   value="<?php echo $isEdit ? $event['coordinates'] : ''; ?>">
                        </div>

                        <!-- Parcours -->
                        <div class="mb-4">
                            <h5>Parcours</h5>
                            <div id="routes-container">
                                <?php if ($isEdit && !empty($event['routes'])): ?>
                                    <?php foreach ($event['routes'] as $index => $route): ?>
                                        <div class="route-item mb-3 border rounded p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6>Parcours <?php echo $index + 1; ?></h6>
                                                <button type="button" class="btn btn-sm btn-danger remove-route">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-2">
                                                        <label class="form-label">Nom du parcours</label>
                                                        <input type="text" class="form-control" 
                                                               name="routes[<?php echo $index; ?>][name]" 
                                                               value="<?php echo htmlspecialchars($route['name']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-2">
                                                        <label class="form-label">Prix (€)</label>
                                                        <input type="number" class="form-control" 
                                                               name="routes[<?php echo $index; ?>][price]" 
                                                               min="0" step="0.01" 
                                                               value="<?php echo $route['price']; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-2">
                                                        <label class="form-label">Distance (km)</label>
                                                        <input type="number" class="form-control" 
                                                               name="routes[<?php echo $index; ?>][distance]" 
                                                               min="0" step="0.1" 
                                                               value="<?php echo $route['distance']; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-2">
                                                        <label class="form-label">Dénivelé (m)</label>
                                                        <input type="number" class="form-control" 
                                                               name="routes[<?php echo $index; ?>][elevation]" 
                                                               min="0" 
                                                               value="<?php echo $route['elevation']; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="mb-2">
                                                        <label class="form-label">Fichier GPX</label>
                                                        <input type="file" class="form-control" 
                                                               name="routes[<?php echo $index; ?>][gpx_file]" 
                                                               accept=".gpx">
                                                        <?php if (!empty($route['gpx_file'])): ?>
                                                            <div class="form-text">
                                                                Fichier actuel : <?php echo basename($route['gpx_file']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="add-route">
                                <i class="bi bi-plus-circle"></i> Ajouter un parcours
                            </button>
                        </div>

                        <!-- Images -->
                        <div class="mb-4">
                            <h5>Images</h5>
                            <div class="mb-3">
                                <label for="images" class="form-label">Photos de l'événement (max 5)</label>
                                <input type="file" class="form-control" id="images" name="images[]" 
                                       accept="image/*" multiple>
                                <div class="form-text">
                                    Formats acceptés : JPG, PNG. Taille maximale : 5 Mo par image
                                </div>
                            </div>
                            <div id="preview-container" class="row mb-3"></div>
                            <?php if ($isEdit && !empty($event['images'])): ?>
                                <div class="row" id="existing-images">
                                    <?php foreach ($event['images'] as $image): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card">
                                                <img src="<?php echo htmlspecialchars($image['url']); ?>" 
                                                     class="card-img-top" alt="Image de l'événement">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="delete_images[]" 
                                                               value="<?php echo $image['id']; ?>">
                                                        <label class="form-check-label">Supprimer</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" 
                                                               name="main_image" 
                                                               value="<?php echo $image['id']; ?>"
                                                               <?php echo $image['is_main'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Image principale</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Paramètres -->
                        <div class="mb-4">
                            <h5>Paramètres</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_participants" class="form-label">
                                            Nombre maximum de participants
                                        </label>
                                        <input type="number" class="form-control" id="max_participants" 
                                               name="max_participants" min="0"
                                               value="<?php echo $isEdit ? 
                                               $event['max_participants'] : ''; ?>">
                                        <div class="form-text">
                                            Laissez vide pour un nombre illimité
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Prix (€) *</label>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               required min="0" step="0.01"
                                               value="<?php echo $isEdit ? $event['price'] : '0'; ?>">
                                        <div class="form-text">
                                            0 = gratuit
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $isEdit ? 'Enregistrer les modifications' : 'Créer l\'événement'; ?>
                            </button>
                            <a href="<?php echo $isEdit ? '/templates/events/event.php?id=' . $event['id'] : 
                                '/templates/events/events.php'; ?>" 
                               class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser la carte
    const map = L.map('map').setView([50.8503, 4.3517], 8); // Centré sur la Belgique
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    let marker;
    
    <?php if ($isEdit && !empty($event['coordinates'])): ?>
        // Récupérer les coordonnées existantes
        const coords = JSON.parse('<?php echo $event['coordinates']; ?>');
        marker = L.marker([coords.lat, coords.lng]).addTo(map);
        map.setView([coords.lat, coords.lng], 13);
    <?php endif; ?>

    // Ajouter un marqueur au clic
    map.on('click', function(e) {
        if (marker) {
            map.removeLayer(marker);
        }
        marker = L.marker(e.latlng).addTo(map);
        document.getElementById('coordinates').value = JSON.stringify({
            lat: e.latlng.lat,
            lng: e.latlng.lng
        });
    });

    // Validation du formulaire
    const eventForm = document.getElementById('eventForm');
    const eventMessage = document.getElementById('eventMessage');

    eventForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        
        try {
            const response = await fetch('/api/events.php', {
                method: '<?php echo $isEdit ? 'PUT' : 'POST'; ?>',
                body: formData
            });

            const data = await response.json();

            if (response.ok) {
                eventMessage.className = 'alert alert-success';
                eventMessage.textContent = data.message;
                
                // Rediriger vers la page de l'événement
                setTimeout(() => {
                    window.location.href = '/templates/events/event.php?id=' + data.event_id;
                }, 1500);
            } else {
                eventMessage.className = 'alert alert-danger';
                eventMessage.textContent = data.message || 'Une erreur est survenue';
            }
        } catch (error) {
            eventMessage.className = 'alert alert-danger';
            eventMessage.textContent = 'Erreur de connexion au serveur';
        }

        eventMessage.classList.remove('d-none');
        eventMessage.scrollIntoView({ behavior: 'smooth' });
    });

    // Gestionnaire d'ajout de parcours
    const routesContainer = document.getElementById('routes-container');
    const addRouteButton = document.getElementById('add-route');
    let routeCount = <?php echo $isEdit && !empty($event['routes']) ? count($event['routes']) : 0; ?>;

    addRouteButton.addEventListener('click', function() {
        const routeHtml = `
            <div class="route-item mb-3 border rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6>Parcours ${routeCount + 1}</h6>
                    <button type="button" class="btn btn-sm btn-danger remove-route">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <label class="form-label">Nom du parcours</label>
                            <input type="text" class="form-control" name="routes[${routeCount}][name]">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <label class="form-label">Prix (€)</label>
                            <input type="number" class="form-control" name="routes[${routeCount}][price]" 
                                   min="0" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <label class="form-label">Distance (km)</label>
                            <input type="number" class="form-control" name="routes[${routeCount}][distance]" 
                                   min="0" step="0.1">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <label class="form-label">Dénivelé (m)</label>
                            <input type="number" class="form-control" name="routes[${routeCount}][elevation]" 
                                   min="0">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="mb-2">
                            <label class="form-label">Fichier GPX</label>
                            <input type="file" class="form-control" name="routes[${routeCount}][gpx_file]" 
                                   accept=".gpx">
                        </div>
                    </div>
                </div>
            </div>
        `;
        routesContainer.insertAdjacentHTML('beforeend', routeHtml);
        routeCount++;
    });

    // Suppression d'un parcours
    routesContainer.addEventListener('click', function(e) {
        if (e.target.closest('.remove-route')) {
            e.target.closest('.route-item').remove();
        }
    });

    // Prévisualisation des images
    const imagesInput = document.getElementById('images');
    const previewContainer = document.getElementById('preview-container');

    imagesInput.addEventListener('change', function() {
        previewContainer.innerHTML = '';
        const files = Array.from(this.files);

        if (files.length > 5) {
            alert('Vous ne pouvez pas sélectionner plus de 5 images');
            this.value = '';
            return;
        }

        files.forEach((file, index) => {
            if (!file.type.startsWith('image/')) {
                alert(`Le fichier "${file.name}" n'est pas une image`);
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert(`L'image "${file.name}" dépasse la taille maximale de 5 Mo`);
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const previewHtml = `
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <img src="${e.target.result}" class="card-img-top" alt="Aperçu">
                            <div class="card-body">
                                <small class="text-muted">${file.name}</small>
                            </div>
                        </div>
                    </div>
                `;
                previewContainer.insertAdjacentHTML('beforeend', previewHtml);
            };
            reader.readAsDataURL(file);
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
