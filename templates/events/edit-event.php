<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug des variables serveur
error_log("=== Debug edit-event.php ===");
error_log("DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT']);
error_log("HTTP_HOST: " . $_SERVER['HTTP_HOST']);
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME']);

// Fonction d'autoloading pour les classes avec logs détaillés
spl_autoload_register(function ($class) {
    error_log("Tentative de chargement de la classe: " . $class);
    // Convertit le namespace en chemin de fichier
    $class = str_replace('App\\', '', $class);
    $class = str_replace('\\', '/', $class);
    $base = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
    $file = $base . '/src/' . $class . '.php';
    error_log("Tentative de chargement du fichier: " . $file);
    if (file_exists($file)) {
        error_log("Fichier trouvé, chargement de: " . $file);
        require_once $file;
    } else {
        error_log("ERREUR: Fichier non trouvé: " . $file);
    }
});

// Includes robustes: base projet via ROOT_PATH
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../logs/error.log.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/flash_messages.php';

// Modèles & helpers
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/Organization.php';
require_once ROOT_PATH . '/src/Models/EventCategory.php';
require_once ROOT_PATH . '/src/Models/Event.php';
require_once ROOT_PATH . '/src/Models/organizer_profile.php';

// Vérifier si la session n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug de la session
error_log("Session ID: " . session_id());
error_log("Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'non défini'));

// Utiliser la fonction requireLogin() existante
requireLogin();

// Vérifier si un ID d'événement est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    error_log("ID d'événement invalide ou manquant: " . (isset($_GET['id']) ? $_GET['id'] : 'non défini'));
    header('Location: ' . APP_URL . '/pages/user/my-events.php?error=' . urlencode('ID d\'événement invalide'));
    exit;
}

function logEventModification($db, $event_id, $field_name, $old_value, $new_value, $user_id) {
    $stmt = $db->prepare("
        INSERT INTO event_modifications (
            event_id,
            field_name,
            old_value,
            new_value,
            modified_by,
            modified_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    return $stmt->execute([$event_id, $field_name, $old_value, $new_value, $user_id]);
}

try {
    $db = getConnection();
    
    // ID d'événement normalisé
    $eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$eventId) {
        require_once ROOT_PATH . '/logs/error.log.php';
        logError('templates/events/edit-event.php', 'ID d\'événement invalide', ['raw_id' => $_GET['id'] ?? null]);
        throw new Exception('ID d\'événement invalide');
    }

    // Déterminer si l'utilisateur est admin
    $isAdmin = function_exists('isAdmin') ? isAdmin() : (($_SESSION['user_role'] ?? '') === 'admin');

    // Récupérer l'événement (admin: sans contrainte user_id)
    if ($isAdmin) {
        $stmt = $db->prepare("
            SELECT e.id, e.title, e.description, e.date, e.start_time, e.end_time,
                   e.location, e.coordinates, e.organisation, e.venue,
                   e.max_participants, e.category_id, e.user_id
            FROM events e
            WHERE e.id = ?
        ");
        $stmt->execute([$eventId]);
    } else {
        $stmt = $db->prepare("
            SELECT e.id, e.title, e.description, e.date, e.start_time, e.end_time,
                   e.location, e.coordinates, e.organisation, e.venue,
                   e.max_participants, e.category_id, e.user_id
            FROM events e
            WHERE e.id = ? AND e.user_id = ?
        ");
        $stmt->execute([$eventId, $_SESSION['user_id'] ?? 0]);
    }
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        require_once ROOT_PATH . '/logs/error.log.php';
        logError('templates/events/edit-event.php', 'Événement introuvable ou non autorisé', [
            'event_id' => $eventId,
            'is_admin' => $isAdmin,
            'session_user' => $_SESSION['user_id'] ?? null
        ]);
        throw new Exception('Événement non trouvé ou non autorisé');
    }

    // Récupérer les images avec fallback storage_path
    $mainImage = null;
    $secondaryImages = [];
    $imgStmt = $db->prepare("
        SELECT image_path, storage_path, is_main
        FROM event_images
        WHERE event_id = ?
        ORDER BY is_main DESC, id ASC
    ");
    $imgStmt->execute([$event['id']]);
    while ($img = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
        $url = resolveImagePublicUrl($img['image_path'], $img['storage_path']);
        if (!$url) {
            continue;
        }
        if ((int)$img['is_main'] === 1) {
            $mainImage = $url;
        } else {
            $secondaryImages[] = $url;
        }
    }
    $event['main_image'] = $mainImage;
    $event['secondary_images'] = $secondaryImages;

    // Ajouter les variables JavaScript pour le mode édition
    echo "<script>
        window.eventId = " . json_encode($event['id'] ?? $eventId) . ";
    </script>";

    echo "<!-- DEBUG EVENT DATA -->\n";
    echo "<!-- Event ID: " . htmlspecialchars((string)$eventId) . " -->\n";
    echo "<!-- User ID: " . htmlspecialchars($_SESSION['user_id']) . " -->\n";
    echo "<!-- Event Data: " . htmlspecialchars(print_r($event, true)) . " -->\n";
    error_log("=== DEBUG EVENT DATA ===");
    error_log("Event ID: " . $eventId);
    error_log("User ID: " . $_SESSION['user_id']);
    error_log("Event Data: " . print_r($event, true));
    error_log("=== END DEBUG EVENT DATA ===");

    if (!$event) {
        throw new Exception('Événement non trouvé ou non autorisé');
    }

    // Récupérer les informations de l'organisation
    $organizer_data = [
        'name' => null,
        'description' => null,
        'logo_path' => null,
        'email' => null,
        'phone' => null,
        'website' => null
    ];

    error_log("Event organisation ID: " . ($event['organisation'] ?? 'null'));

    if (!empty($event['organisation'])) {
        $stmt = $db->prepare("
            SELECT *
            FROM organizer_profiles
            WHERE id = ?
        ");
        $stmt->execute([$event['organisation']]);
        $organizer = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Organizer data from DB: " . print_r($organizer, true));
    }

    if (empty($organizer) && !empty($_SESSION['user_id'])) {
        $stmt = $db->prepare("
            SELECT *
            FROM organizer_profiles
            WHERE user_id = ?
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $organizer = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Organizer fallback from user: " . print_r($organizer, true));
    }

    if ($organizer) {
        $organizer_data = [
            'name' => $organizer['name'],
            'description' => $organizer['description'],
            'logo_path' => $organizer['logo_path'],
            'email' => $organizer['email'],
            'phone' => $organizer['phone'],
            'website' => $organizer['website']
        ];
        error_log("Organizer data prepared: " . print_r($organizer_data, true));
    } else {
        error_log("❌ Aucun organisateur trouvé pour l'événement " . $event['id']);
    }

    // Debug PHP final
    error_log("<!-- Debug PHP final -->");

    // Mettre à jour l'affichage du tableau avec plus de détails
    error_log("<!-- Mettre à jour l'affichage du tableau avec plus de détails -->");

    // Plus loin dans le code, mettre à jour les champs du formulaire
    error_log("<!-- Plus loin dans le code, mettre à jour les champs du formulaire -->");

    // Récupérer les parcours
    $stmt = $db->prepare("
        SELECT * 
        FROM event_parcours 
        WHERE event_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$event['id']]);
    $event['parcours'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si aucun parcours n'existe, créer un parcours par défaut
    if (empty($event['parcours'])) {
        $event['parcours'] = [[]];
    }

    // Récupérer les contacts supplémentaires
    $contactStmt = $db->prepare("
        SELECT name, phone
        FROM event_contacts
        WHERE event_id = ?
        ORDER BY id ASC
    ");
    $contactStmt->execute([$event['id']]);
    $event['contacts'] = $contactStmt->fetchAll(PDO::FETCH_ASSOC);

    // Variables nécessaires pour le template
    $pageTitle = "Modifier l'événement";
    $currentStep = 1;
    $maxSteps = 4;
    $isDraft = false;
    $originalEventId = $eventId;

    // Les images secondaires sont déjà un tableau depuis la requête ci-dessus

    // Récupérer les données nécessaires pour le formulaire
    $categoryManager = new EventCategory($db);
    $categories = $categoryManager->getAllActive();
    
    $organizerManager = new OrganizerProfile($db);
    $organizers = $organizerManager->getByUserId($_SESSION['user_id']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        // S'assurer qu'aucun contenu n'a été envoyé avant
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        try {
            // Validation des données
            if (empty($_POST['title'])) {
                throw new Exception("Le titre est requis");
            }
            
            // Mise à jour de l'événement
            $stmt = $db->prepare("
                UPDATE events 
                SET 
                    title = ?,
                    description = ?,
                    date = ?,
                    start_time = ?,
                    end_time = ?,
                    location = ?,
                    category_id = ?,
                    max_participants = ?,
                    organisation = ?,
                    updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            
            // Formatage des dates
            $eventDate = date('Y-m-d', strtotime($_POST['event_date']));
            $startTime = date('H:i:s', strtotime($_POST['start_time']));
            $endTime = !empty($_POST['end_time']) ? date('H:i:s', strtotime($_POST['end_time'])) : null;
            
            $success = $stmt->execute([
                $_POST['title'],
                $_POST['description'],
                $eventDate,
                $startTime,
                $endTime,
                $_POST['location'],
                $_POST['category_id'],
                $_POST['max_participants'],
                $_POST['organisation'],
                $_GET['id'],
                $_SESSION['user_id']
            ]);
            
            if (!$success) {
                throw new Exception("Erreur lors de la mise à jour de l'événement");
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Événement mis à jour avec succès'
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour : " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

} catch (Exception $e) {
    error_log('Exception dans edit-event.php: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());
    header('Location: ' . APP_URL . '/pages/user/my-events.php?error=' . urlencode('Erreur lors de la récupération de l\'événement'));
    exit;
}

// Inclure l'en-tête
require_once ROOT_PATH . '/templates/layouts/header-solid.php';
?>

<!-- Dépendances CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<link rel="stylesheet" href="/assets/css/create-event.css">
<link rel="stylesheet" href="/assets/css/event-display.css">

<!-- Scripts -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
<script src="https://unpkg.com/leaflet-polylinedecorator@1.6.0/dist/leaflet.polylineDecorator.js"></script>
<script>
    // Indiquer que nous sommes en mode édition
    window.isEditMode = true;
    window.eventId = <?php echo json_encode($event['id']); ?>;
</script>

<script src="/assets/js/events/event-maps.js"></script>
<script src="/assets/js/events/event-display.js"></script>

<!-- Modal de prévisualisation -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="auth-icon" aria-hidden="true">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <h5 class="modal-title" id="previewModalLabel">Prévisualisation de l'événement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <?php
                    // Prévisualisation avec le template public
                    require_once ROOT_PATH . '/src/Services/EventDisplayBuilder.php';
                    $builder = new EventDisplayBuilder($db);
                    $eventDisplay = $builder->build('published', (int)$event['id']);
                    if ($eventDisplay) {
                        $__backup = $event;
                        $event = $eventDisplay;
                        $mode = 'published';
                        include __DIR__ . '/event-display.php';
                        $event = $__backup; unset($__backup);
                    } else {
                        echo '<div class="alert alert-warning">Prévisualisation indisponible.</div>';
                    }
                ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<div class="min-h-screen background-color">
    <div class="create-event-hero">
        <div class="container">
            <h1 class="eventTitle">Modifier l'événement</h1>
            <p>Modifiez les informations de votre événement</p>
            <div class="mt-3">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#previewModal">
                    <i class="bi bi-eye"></i> Prévisualiser (rendu public)
                </button>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Steps -->
                <div class="steps mb-5">
                    <div class="step active" data-step="1" data-title="Informations de l'événement">
                        <button class="step-button" onclick="setStep(1)">1</button>
                    </div>
                    <div class="step" data-step="2" data-title="Informations de l'organisateur">
                        <button class="step-button" onclick="setStep(2)">2</button>
                    </div>
                    <div class="step" data-step="3" data-title="Validation">
                        <button class="step-button" onclick="setStep(3)">3</button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress mb-4">
                    <div id="progressBar" class="progress-bar" role="progressbar" style="width: 33%;" aria-valuenow="33" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <!-- Form -->
                <form id="createEventForm" class="needs-validation" novalidate>
                    <!-- Conteneur d'erreurs global -->
                    <div class="alert alert-danger d-none" id="globalErrorContainer" role="alert">
                        <ul class="list-unstyled mb-0" id="globalErrorList"></ul>
                    </div>

                    <!-- Step 1 -->
                    <div class="step-content" id="step1">
                        <!-- Images Upload -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Photos de l'événement</h3>
                                
                                <div class="mb-4">
                                    <?php if ($event['main_image']): ?>
                                        <div class="current-image mb-3">
                                            <img src="<?= $event['main_image'] ?>" alt="Image principale actuelle" class="img-thumbnail" style="max-width: 200px">
                                            <p class="text-muted">Image actuelle</p>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="main_image" accept="image/*">
                                    <div class="form-text">Format recommandé : JPG, PNG. Taille max : 5MB</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Images secondaires</label>
                                    <?php if (!empty($event['secondary_images'])): ?>
                                        <div class="current-images mb-3">
                                            <?php foreach ($event['secondary_images'] as $image): ?>
                                                <img src="<?= $image ?>" alt="Image secondaire" class="img-thumbnail me-2" style="max-width: 100px">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="secondary_images[]" multiple accept="image/*">
                                    <div class="form-text">Vous pouvez sélectionner plusieurs images</div>
                                </div>
                            </div>
                        </div>

                        <!-- Informations générales -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Informations générales</h3>
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label required-field">Titre de l'événement</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($event['title']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label required-field">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" required><?= htmlspecialchars($event['description']) ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label required-field">Date</label>
                                        <input type="text" 
                                               class="form-control flatpickr-date" 
                                               name="date" 
                                               data-date="<?= isset($event['date']) ? $event['date'] : '' ?>"
                                               required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label required-field">Heure de début</label>
                                        <input type="text" 
                                               class="form-control time-picker-input" 
                                               name="start_time" 
                                               value="<?= $event['start_time'] ? date('H:i', strtotime($event['start_time'])) : '' ?>" 
                                               placeholder="HH:MM" 
                                               required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label required-field">Heure de fin</label>
                                        <input type="text" 
                                               class="form-control time-picker-input" 
                                               name="end_time" 
                                               value="<?= $event['end_time'] ? date('H:i', strtotime($event['end_time'])) : '' ?>" 
                                               placeholder="HH:MM" 
                                               required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="category_id" class="form-label required-field">Catégorie</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>" <?= $category['id'] == $event['category_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Localisation -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Localisation</h3>
                                
                                <div class="form-group mb-3">
                                    <label for="location" class="form-label required-field">Nom du local</label>
                                    <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($event['location'] ?? '') ?>" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="address" class="form-label required-field">Adresse</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               id="address" 
                                               name="address" 
                                               value="<?= htmlspecialchars($event['venue'] ?? '') ?>"
                                               required>
                                        <button class="btn btn-outline-secondary" type="button" id="searchAddressBtn">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div id="locationMap" style="height: 400px;" class="mb-3"></div>
                                <div class="form-text">Déplacez le marqueur pour ajuster la position exacte</div>
                                <input type="hidden" id="coordinates" name="coordinates" value="<?= htmlspecialchars($event['coordinates'] ?? '') ?>" required>
                                <input type="hidden" id="latitude" name="latitude">
                                <input type="hidden" id="longitude" name="longitude">
                            </div>
                        </div>

                        <!-- Parcours -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Parcours</h3>
                                <div id="routes-container">
                                    <?php foreach ($event['parcours'] as $index => $parcours): ?>
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h5 class="mb-0">Parcours <?= $index + 1 ?></h5>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeGpx(<?= $index ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label required-field">Nom du parcours</label>
                                                <input type="text" class="form-control" name="routes[<?= $index ?>][name]" value="<?= htmlspecialchars($parcours['name'] ?? '') ?>" required>
                                                <input type="hidden" name="routes[<?= $index ?>][id]" value="<?= $parcours['id'] ?? '' ?>">
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-2">
                                                        <label class="form-label required-field">Distance (km)</label>
                                                        <input type="number" step="0.1" class="form-control" name="routes[<?= $index ?>][distance]" value="<?= htmlspecialchars($parcours['distance'] ?? '') ?>" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-2">
                                                        <label class="form-label">Dénivelé (m)</label>
                                                        <input type="number" class="form-control" name="routes[<?= $index ?>][elevation]" value="<?= htmlspecialchars($parcours['elevation_gain'] ?? '') ?>" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-2">
                                                        <label class="form-label required-field">Prix (€)</label>
                                                        <input type="number" step="0.50" class="form-control" name="routes[<?= $index ?>][price]" value="<?= htmlspecialchars($parcours['price'] ?? '') ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">Fichier GPX</label>
                                                <input type="file" class="form-control" name="routes[<?= $index ?>][gpx]" accept=".gpx" onchange="handleGpxUpload(this, <?= $index ?>)">
                                                <?php if (!empty($parcours['gpx_file'])): ?>
                                                    <input type="hidden" name="routes[<?= $index ?>][gpx_file]" value="<?= htmlspecialchars($parcours['gpx_file']) ?>">
                                                <?php endif; ?>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input type="checkbox" class="form-check-input" id="gpx_downloadable_<?= $index ?>" name="routes[<?= $index ?>][gpx_downloadable]" value="1" <?= !empty($parcours['gpx_downloadable']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="gpx_downloadable_<?= $index ?>">
                                                    Autoriser le téléchargement du GPX
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary" onclick="addRoute()">
                                    <i class="bi bi-plus-circle"></i> Ajouter un parcours
                                </button>
                            </div>
                        </div>

                        <!-- Carte des parcours -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Carte des parcours</h3>
                                <div id="gpxMap" style="height: 400px; margin-bottom: 1rem; border-radius: 0.5rem;"></div>
                                <div id="gpxLegend" class="gpx-legend"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-content d-none" id="step2">
                        <!-- Organizer Information -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Informations de l'organisateur</h3>
                                
                                <div class="mb-4">
                                    <?php if (!empty($organizers)): ?>
                                        <div class="mb-3">
                                            <label for="organizerSelect" class="form-label">Sélectionner un organisateur existant</label>
                                            <select class="form-select" id="organizerSelect" name="organizerId">
                                                <option value="">Nouvel organisateur</option>
                                                <?php foreach ($organizers as $organizer): ?>
                                                    <option value="<?= htmlspecialchars($organizer['id']) ?>" <?= ($event['organisation'] == $organizer['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($organizer['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>

                                    <div id="newOrganizerToggle" class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="useProfileInfo" name="useProfileInfo">
                                        <label class="form-check-label" for="useProfileInfo">
                                            Utiliser mes informations de profil
                                        </label>
                                    </div>
                                </div>

                                <div id="organizerFields">
                                    <div class="mb-3">
                                        <label for="organizerName" class="form-label required-field">Nom de l'organisation</label>
                                        <input type="text" class="form-control" id="organizerName" name="organizerName" value="<?= htmlspecialchars($organizer_data['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerAddress" class="form-label">Adresse</label>
                                        <input type="text" class="form-control" id="organizerAddress" name="organizerAddress" value="<?= htmlspecialchars($organizer_data['address'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerDescription" class="form-label">Description</label>
                                        <textarea class="form-control" id="organizerDescription" name="organizerDescription" rows="3"><?= htmlspecialchars($organizer_data['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerWebsite" class="form-label">Site web</label>
                                        <input type="url" class="form-control" id="organizerWebsite" name="organizerWebsite" value="<?= htmlspecialchars($organizer_data['website'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerPhone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="organizerPhone" name="organizerPhone" value="<?= htmlspecialchars($organizer_data['phone'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerEmail" class="form-label required-field">Email</label>
                                        <input type="email" class="form-control" id="organizerEmail" name="organizerEmail" value="<?= htmlspecialchars($organizer_data['email'] ?? '') ?>" required>
                                    </div>
                                        
                                    <!-- Logo Upload -->
                                    <div class="mb-3">
                                        <label for="organizerLogo" class="form-label">Logo</label>
                                        <div class="logo-upload-container">
                                            <?php if (!empty($organizer_data['logo_path'])): ?>
                                                <img id="logoPreview" class="logo-preview" src="<?= htmlspecialchars($organizer_data['logo_path']) ?>" alt="Logo preview" style="max-width: 200px; margin-bottom: 10px;">
                                            <?php else: ?>
                                                <img id="logoPreview" class="logo-preview" src="" alt="Logo preview" style="display: none; max-width: 200px; margin-bottom: 10px;">
                                            <?php endif; ?>
                                            <input type="file" class="form-control" id="organizerLogo" name="organizerLogo" accept="image/*">
                                            <small class="form-text text-muted">Format recommandé : PNG ou JPG, max 2Mo</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Contacts supplémentaires</h3>
                                <p class="card-text">Ajoutez d'autres contacts pour cet événement</p>
                                
                                <div id="contacts-container">
                                    <?php if (!empty($event['contacts'])): ?>
                                        <?php foreach ($event['contacts'] as $contact): ?>
                                            <div class="contact-item mb-3">
                                                <div class="row">
                                                    <div class="col-md-5">
                                                        <input type="text" class="form-control" name="contact_names[]" placeholder="Nom du contact" value="<?= htmlspecialchars($contact['name']) ?>">
                                                    </div>
                                                    <div class="col-md-5">
                                                        <input type="text" class="form-control" name="contact_numbers[]" placeholder="Numéro de téléphone" value="<?= htmlspecialchars($contact['phone']) ?>">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <button type="button" class="btn btn-danger" onclick="removeContact(this)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="btn btn-secondary" onclick="addContactField()">
                                    <i class="fas fa-plus"></i> Ajouter un contact
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="step-content d-none" id="step3">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Validation de l'événement</h3>
                                <div id="eventPreview"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary prev-step" id="prevButton" style="display: none;">
                            <i class="bi bi-arrow-left"></i> Précédent
                        </button>
                        <button type="button" class="btn btn-primary next-step" id="nextButton">
                            <span id="step1Text">Suivant</span>
                            <span id="step2Text" style="display: none;">Enregistrer</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" style="display: none;">
    <div class="spinner-border text-light" role="status">
        <span class="visually-hidden">Chargement...</span>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration du sélecteur de date
    const dateInput = document.querySelector('.flatpickr-date');
    const storedDate = dateInput.getAttribute('data-date');
    
    const fp = flatpickr(dateInput, {
        locale: "fr",
        dateFormat: "Y-m-d",
        allowInput: true,
        altInput: true,
        altFormat: "d/m/Y",
        time_24hr: true,
        defaultDate: storedDate || null,
        onChange: function(selectedDates, dateStr, instance) {
            // No-op: l'altInput affiche déjà le format humain
            // Conserver ce hook si besoin de logique future
        }
    });

    // Force la mise à jour de l'affichage
    if (storedDate) {
        const date = new Date(storedDate);
        fp.setDate(date, true);
    }

    // Initialiser les sélecteurs d'heure
    document.querySelectorAll('.time-picker-input').forEach(input => {
        flatpickr(input, {
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i',
            time_24hr: true,
            locale: 'fr',
            defaultDate: input.value
        });
    });
});
</script>

<!-- Charger uniquement le script d'édition -->
<script src="/assets/js/events/event-edit.js"></script>

<script>
    // Configuration de la carte
    const map = L.map('locationMap').setView([46.603354, 1.888334], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: ' OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;
    const coordinates = document.querySelector('input[name="coordinates"]');

    // Placer le marqueur selon les coordonnées
    if (coordinates.value) {
        const [lat, lng] = coordinates.value.split(',').map(coord => parseFloat(coord.trim()));
        if (!isNaN(lat) && !isNaN(lng)) {
            marker = L.marker([lat, lng]).addTo(map);
            map.setView([lat, lng], 13);
        }
    }
</script>