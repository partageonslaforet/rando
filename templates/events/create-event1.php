<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_log("🚀 Début create-event.php");

// Inclure les dépendances dans le bon ordre
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    error_log("✅ database.php chargé");
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';
    error_log("✅ functions.php chargé");
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
    error_log("✅ auth_check.php chargé");
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Models/EventCategory.php';
    error_log("✅ EventCategory.php chargé");
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/organizer_profile.php';
    error_log("✅ organizer_profile.php chargé");
} catch (Exception $e) {
    error_log("❌ Erreur lors du chargement des dépendances : " . $e->getMessage());
    header('Location: /?error=configuration_error');
    exit;
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    error_log("❌ Utilisateur non connecté");
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
error_log("✅ Utilisateur connecté (ID: " . $_SESSION['user_id'] . ")");

// Vérifier si on est en mode édition d'un brouillon existant
$draft_id = isset($_GET['draft_id']) ? intval($_GET['draft_id']) : null;
$isEditMode = false;

if ($draft_id) {
    // Mode édition : vérifier que le brouillon existe et appartient à l'utilisateur
    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT id FROM event_drafts WHERE id = ? AND user_id = ?");
        $stmt->execute([$draft_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            error_log("❌ Brouillon non trouvé ou non autorisé");
            header('Location: /events/drafts.php?error=' . urlencode('Brouillon non trouvé ou non autorisé'));
            exit;
        }
        error_log("✅ Brouillon vérifié (ID: $draft_id)");
        $isEditMode = true;
    } catch (Exception $e) {
        error_log("❌ Erreur lors de la vérification du brouillon : " . $e->getMessage());
        header('Location: /events/drafts.php?error=' . urlencode('Erreur lors de la vérification du brouillon'));
        exit;
    }
}

// Récupérer les informations complètes de l'utilisateur et les catégories
try {
    $db = getConnection();
    
    // Récupérer l'utilisateur
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("✅ Informations utilisateur récupérées");

    if (!$user) {
        error_log("❌ Utilisateur non trouvé en base de données");
        header('Location: /login.php');
        exit;
    }

    // Récupérer les catégories actives
    $categoryManager = new EventCategory($db);
    $categories = $categoryManager->getAllActive();
    error_log("✅ Catégories récupérées : " . count($categories));

    // Récupérer les profils organisateurs de l'utilisateur
    $organizerManager = new OrganizerProfile($db);
    error_log("✅ OrganizerProfile initialisé");
    $organizers = $organizerManager->getByUserId($_SESSION['user_id']);
    error_log("✅ Profils organisateurs récupérés : " . count($organizers));

} catch (PDOException $e) {
    error_log("❌ Erreur lors de la récupération des informations : " . $e->getMessage());
    header('Location: /?error=database_error');
    exit;
} catch (Exception $e) {
    error_log("❌ Erreur inattendue : " . $e->getMessage());
    header('Location: /?error=unexpected_error');
    exit;
}

// Inclure le header après toutes les vérifications et redirections potentielles
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
error_log("✅ Header inclus");

?>

<link rel="stylesheet" href="/assets/css/create-event.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.css">
<script src="/assets/js/event-images.js"></script>

<style>
.required-field::after {
    content: " *";
    color: var(--bs-primary);
    font-weight: bold;
}

.form-label.required-field {
    position: relative; 
    display: inline-block;
}

.progress-bar {
    background-color: var(--primary-color);
}

#loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(5px);
    display: none;
}

.loading-spinner {
    text-align: center;
}

.loading-text {
    font-size: 1.2rem;
    color: #0d6efd;
    margin-top: 1rem;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
}
</style>

<div id="loading-overlay" style="display: none;">
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Chargement...</span>
        </div>
        <div class="loading-text">Chargement...</div>
    </div>
</div>

<div class="min-h-screen background-color">
    <div class="create-event-hero">
        <div class="container">
            <h1 class="eventTitle">Créer un événement</h1>
            <p>Partagez votre passion et organisez des événements sportifs</p>
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
                    <div class="step" data-step="3" data-title="Prévisualisation">
                        <button class="step-button" onclick="setStep(3)">3</button>
                    </div>
                    <div class="step" data-step="4" data-title="Enregistrement">
                        <button class="step-button" onclick="setStep(4)">4</button>
                    </div>
                    <div class="step" data-step="5" data-title="Notification">
                        <button class="step-button" disabled>5</button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress mb-4">
                    <div id="progressBar" class="progress-bar" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <!-- Form -->
                <form id="createEventForm" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <?php if ($isEditMode): ?>
                        <!-- Champ caché pour le draftId en mode édition -->
                        <input type="hidden" name="draftId" value="<?php echo htmlspecialchars($draft_id); ?>">
                    <?php endif; ?>
                    
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
                                
                                <!-- Image principale -->
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="bi bi-star-fill text-warning"></i> Image principale
                                    </label>
                                    <div class="main-image-container">
                                        <img id="mainImagePreview" class="main-image-preview" style="display: none;">
                                        <label class="image-upload-button">
                                            <i class="bi bi-upload"></i>
                                            <span>Choisir l'image principale</span>
                                            <input type="file" 
                                                   id="mainImage"
                                                   name="mainImage"
                                                   accept="image/*" 
                                                   onchange="handleMainImageUpload(event)"
                                                   class="hidden">
                                        </label>
                                    </div>
                                    <div class="form-text">Cette image sera affichée en couverture de votre événement. Format recommandé: carré. Poids maximum: 5 Mo</div>
                                </div>

                                <!-- Images secondaires -->
                                <div class="mb-3">
                                    <label class="form-label">Images secondaires</label>
                                    <div class="secondary-images-container">
                                        <div id="secondaryImagesPreview" class="row g-3" style="display: none;"></div>
                                        <div class="secondary-images-input">
                                            <label class="image-upload-button">
                                                <i class="bi bi-upload"></i>
                                                <span>Choisir des images</span>
                                                <input type="file" 
                                                       id="secondaryImages"
                                                       name="secondaryImages"
                                                       accept="image/*"
                                                       multiple
                                                       onchange="handleSecondaryImagesUpload(event)"
                                                       class="hidden">
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-text">Format recommandé: 1920x1080px. Poids maximum: 2 Mo par image</div>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Informations générales</h3>
                                <div class="mb-3">
                                    <label for="title" class="form-label required-field">Titre de l'événement</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                    <div class="invalid-feedback">
                                        Veuillez saisir un titre pour l'événement
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="4" required></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label required-field">Date</label>
                                            <input type="date" class="form-control" name="date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label required-field">Heure de début</label>
                                            <input type="time" class="form-control" name="startTime" required>
                                            <div class="invalid-feedback">
                                                Veuillez saisir une heure de début
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Heure de fin</label>
                                            <input type="time" class="form-control" name="endTime">
                                            <div class="form-text">Optionnel</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="category" class="form-label required-field">Catégorie</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Sélectionnez une catégorie</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= htmlspecialchars($category['id']) ?>" 
                                                    data-icon="<?= htmlspecialchars($category['icon'] ?? '') ?>"
                                                    data-color="<?= htmlspecialchars($category['color'] ?? '') ?>">
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner une catégorie
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Localisation</h3>
                                
                                <div class="form-group mb-3">
                                    <label for="location_name" class="form-label required-field">Nom du local</label>
                                    <input type="text" class="form-control" id="location_name" name="location_name" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label required-field">Adresse</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               id="address" 
                                               name="address" 
                                               required>
                                        <button class="btn btn-outline-secondary" type="button" id="searchAddressBtn">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
                                </div>
                                <div id="locationMap" style="height: 400px;" class="mb-3"></div>
                                <div class="form-text">Déplacez le marqueur pour ajuster la position exacte</div>
                                <input type="hidden" id="latitude" name="latitude" required>
                                <input type="hidden" id="longitude" name="longitude" required>
                            </div>
                        </div>

                        <!-- Routes -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Parcours</h3>
                                <div id="routes-container">
                                    <div class="mb-4">
                                        <h5 class="mb-2">Parcours 1</h5>
                                        <div class="mb-2">
                                            <label class="form-label required-field">Nom du parcours</label>
                                            <input type="text" class="form-control" name="routes[0][name]" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-2">
                                                    <label class="form-label required-field">Distance (km)</label>
                                                    <input type="number" step="0.1" class="form-control" name="routes[0][distance]" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-2">
                                                    <label class="form-label required-field">Dénivelé (m)</label>
                                                    <input type="number" class="form-control" name="routes[0][elevation]">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-2">
                                                    <label class="form-label required-field">Prix (€)</label>
                                                    <input type="number" step="0.01" class="form-control" name="routes[0][price]" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">GPX</label>
                                            <input type="file" class="form-control" name="routes[0][gpx]" accept=".gpx" onchange="handleGpxUpload(this, 0)">
                                        </div>
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="gpx_downloadable_0" name="routes[0][gpx_downloadable]" value="1">
                                                <label class="form-check-label" for="gpx_downloadable_0">Autoriser le téléchargement du GPX</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="addBtn"class="btn btn-outline-primary" onclick="addRoute()">
                                    <i class="bi bi-plus-circle"></i> Ajouter un parcours
                                </button>
                            </div>
                        </div>

                        <!-- Carte GPX avec légende -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Aperçu des parcours</h3>
                                <div id="gpxMap" style="height: 400px;"></div>
                                <div id="gpx-legend" class="mt-3">
                                    <div id="gpx-legend-content" class="d-flex flex-wrap gap-3"></div>
                                </div>
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
                                                <option value="<?= htmlspecialchars($organizer['id']) ?>">
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
                                        <input type="text" class="form-control" id="organizerName" name="organizerName" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerAddress" class="form-label">Adresse</label>
                                        <input type="text" class="form-control" id="organizerAddress" name="organizerAddress">
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerDescription" class="form-label">Description</label>
                                        <textarea class="form-control" id="organizerDescription" name="organizerDescription" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerWebsite" class="form-label">Site web</label>
                                        <input type="url" class="form-control" id="organizerWebsite" name="organizerWebsite">
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerPhone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="organizerPhone" name="organizerPhone">
                                    </div>
                                    <div class="mb-3">
                                        <label for="organizerEmail" class="form-label required-field">Email</label>
                                        <input type="email" class="form-control" id="organizerEmail" name="organizerEmail" required>
                                    </div>
                                    
                                    <!-- Logo Upload -->
                                    <div class="mb-3">
                                        <label for="organizerLogo" class="form-label">Logo</label>
                                        <div class="logo-upload-container">
                                            <img id="logoPreview" class="logo-preview" src="" alt="Logo preview" style="display: none; max-width: 200px; margin-bottom: 10px;">
                                            <input type="file" 
                                                   class="form-control" 
                                                   id="organizerLogo" 
                                                   name="organizerLogo" 
                                                   accept="image/*"
                                                   onchange="handleLogoUpload(this)">
                                        </div>
                                        <div class="form-text">Format recommandé: PNG ou JPG. Taille maximale: 2 Mo</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Contacts supplémentaires</h3>
                                <div id="contacts-container">
                                    <!-- Les contacts seront ajoutés ici dynamiquement -->
                                </div>
                                <button type="button" class="btn btn-outline-primary" onclick="addContactField()">
                                    <i class="bi bi-plus-circle"></i> Ajouter un contact
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="step-content d-none" id="step3">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Prévisualisation de l'événement</h3>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Voici un aperçu de votre événement tel qu'il apparaîtra sur le site.
                                    Vérifiez que toutes les informations sont correctes avant de continuer.
                                </div>
                                <div id="eventPreview" class="mt-4">
                                    <!-- Le contenu sera chargé dynamiquement -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="mt-4 d-flex justify-content-between">
                        <div>
                            <button type="button" id="prevButton" class="btn btn-secondary" onclick="prevStep()">
                                <i class="bi bi-arrow-left"></i> Précédent
                            </button>
                        </div>
                        <div>
                            <button type="button" id="nextButton" class="btn btn-primary" onclick="nextStep()">
                                Suivant <i class="bi bi-arrow-right"></i>
                            </button>
                            <button type="button" id="publishButton" class="btn btn-success d-none">
                                Publier <i class="bi bi-check-lg"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>

<script src="/assets/js/event-images.js"></script>
<script src="/assets/js/event-validation.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser tous les dropdowns Bootstrap
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
});

// Fonction pour mettre à jour l'aperçu de l'image
function updateImagePreview(imageData) {
    if (imageData.mainImage) {
        const mainPreview = document.getElementById('mainImagePreview');
        if (mainPreview) {
            mainPreview.src = imageData.mainImage.path;
            mainPreview.style.display = 'block';
        }
    }
    if (imageData.secondaryImages) {
        const secondaryPreviews = document.getElementById('secondaryImagesPreview');
        if (secondaryPreviews) {
            secondaryPreviews.innerHTML = '';
            const row = document.createElement('div');
            row.classList.add('row', 'g-3');
            secondaryPreviews.appendChild(row);

            imageData.secondaryImages.forEach((image, index) => {
                const col = document.createElement('div');
                col.classList.add('col-md-4');
                
                const imgContainer = document.createElement('div');
                imgContainer.classList.add('secondary-image-container', 'position-relative');
                
                const img = document.createElement('img');
                img.src = image.path;
                img.alt = `Image secondaire ${index + 1}`;
                img.classList.add('img-fluid', 'rounded');
                
                // Ajouter un bouton de suppression
                const deleteBtn = document.createElement('button');
                deleteBtn.classList.add('btn', 'btn-danger', 'btn-sm', 'position-absolute', 'top-0', 'end-0', 'm-2');
                deleteBtn.innerHTML = '×';
                deleteBtn.onclick = async () => {
                    // Supprimer l'image sur le serveur
                    const draftId = document.querySelector('input[name="draftId"]')?.value;
                    if (draftId) {
                        const formData = new FormData();
                        formData.append('draftId', draftId);
                        formData.append('deleteImage', image.id);
                        
                        try {
                            const response = await fetch('/api/events/save_draft.php', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            if (result.success) {
                                updateImagePreview(result);
                            }
                        } catch (error) {
                            console.error('Erreur lors de la suppression:', error);
                        }
                    }
                };
                
                imgContainer.appendChild(img);
                imgContainer.appendChild(deleteBtn);
                col.appendChild(imgContainer);
                row.appendChild(col);
            });

            secondaryPreviews.style.display = imageData.secondaryImages.length > 0 ? 'block' : 'none';
        }
    }
}

// Gestionnaire pour les images secondaires
async function handleSecondaryImagesUpload(event) {
    const input = event.target;
    const files = input.files;
    if (!files || files.length === 0) return;

    // Vérifier qu'on ne dépasse pas 3 images au total
    const existingImages = document.querySelectorAll('#secondaryImagesPreview .secondary-image-container img');
    if (existingImages.length + files.length > 3) {
        showToast('Vous ne pouvez pas ajouter plus de 3 images secondaires', 'warning');
        return;
    }

    const formData = new FormData();
    
    // Ajouter le draftId s'il existe
    const draftId = document.querySelector('input[name="draftId"]')?.value;
    if (draftId) {
        formData.append('draftId', draftId);
    }
    
    // Ajouter les images existantes et leurs IDs
    const existingImagesData = Array.from(existingImages).map(img => ({
        path: img.src,
        id: img.dataset.imageId
    }));
    formData.append('existingSecondaryImages', JSON.stringify(existingImagesData));
    
    // Ajouter les nouvelles images
    Array.from(files).forEach((file, index) => {
        formData.append('secondaryImages[]', file);
    });

    try {
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            // Mettre à jour l'affichage en ajoutant les nouvelles images aux existantes
            updateSecondaryImagesPreview({
                secondaryImages: [
                    ...existingImagesData,
                    ...(data.secondaryImages || [])
                ]
            });
        }
    } catch (error) {
        console.error('Erreur lors de l\'upload:', error);
        showToast('Erreur lors de l\'upload des images', 'error');
    }
    
    // Vider l'input pour éviter l'envoi en double
    input.value = '';
}

// Fonction pour mettre à jour l'affichage des images secondaires
function updateSecondaryImagesPreview(data) {
    const container = document.getElementById('secondaryImagesPreview');
    if (!container) return;

    // Si on reçoit des données du serveur, on met à jour l'affichage
    if (data && data.secondaryImages) {
        container.innerHTML = '';
        const row = document.createElement('div');
        row.classList.add('row', 'g-3');
        container.appendChild(row);

        data.secondaryImages.forEach((image, index) => {
            const col = document.createElement('div');
            col.classList.add('col-md-4');
            
            const imgContainer = document.createElement('div');
            imgContainer.classList.add('secondary-image-container', 'position-relative');
            
            const img = document.createElement('img');
            img.src = image.path;
            img.alt = `Image secondaire ${index + 1}`;
            img.classList.add('img-fluid', 'rounded');
            img.dataset.imageId = image.id; // Stocker l'ID de l'image
            
            // Ajouter un bouton de suppression
            const deleteBtn = document.createElement('button');
            deleteBtn.classList.add('btn', 'btn-danger', 'btn-sm', 'position-absolute', 'top-0', 'end-0', 'm-2');
            deleteBtn.innerHTML = '×';
            deleteBtn.onclick = async () => {
                // Supprimer l'image sur le serveur
                const draftId = document.querySelector('input[name="draftId"]')?.value;
                if (draftId) {
                    const formData = new FormData();
                    formData.append('draftId', draftId);
                    formData.append('deleteImage', image.id);
                    
                    try {
                        const response = await fetch('/api/events/save_draft.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            // Supprimer uniquement l'image concernée
                            imgContainer.remove();
                            if (row.children.length === 0) {
                                container.style.display = 'none';
                            }
                        }
                    } catch (error) {
                        console.error('Erreur lors de la suppression:', error);
                        showToast('Erreur lors de la suppression de l\'image', 'error');
                    }
                }
            };
            
            imgContainer.appendChild(img);
            imgContainer.appendChild(deleteBtn);
            col.appendChild(imgContainer);
            row.appendChild(col);
        });

        container.style.display = data.secondaryImages.length > 0 ? 'block' : 'none';
    }
}

// Fonction pour sauvegarder le brouillon
async function saveDraft() {
    showLoading('Sauvegarde en cours...');

    try {
        const formData = new FormData(document.getElementById('createEventForm'));

        // Ajouter l'image principale
        const mainImageInput = document.getElementById('mainImage');
        if (mainImageInput && mainImageInput.files.length > 0) {
            formData.append('mainImage', mainImageInput.files[0]);
        }

        // Ajouter les images secondaires du tableau
        secondaryImagesArray.forEach((file, index) => {
            formData.append(`secondaryImages[${index}]`, file);
        });

        // Ajouter le logo
        const logoInput = document.getElementById('organizerLogo');
        if (logoInput && logoInput.files.length > 0) {
            formData.append('organizerLogo', logoInput.files[0]);
        }

        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Erreur lors de la sauvegarde');
        }

        hideLoading();
        showToast('Brouillon sauvegardé', 'success');
        
        // Mettre à jour l'ID du brouillon si nécessaire
        const draftIdInput = document.querySelector('input[name="draftId"]');
        if (data.draftId && draftIdInput) {
            draftIdInput.value = data.draftId;
        }

        return data;
    } catch (error) {
        console.error('❌ Erreur lors de la sauvegarde:', error);
        hideLoading();
        showToast(error.message || 'Erreur lors de la sauvegarde', 'error');
        throw error;
    }
}

// Ajouter l'écouteur d'événements pour l'upload d'image secondaire
document.getElementById('secondaryImages').addEventListener('change', handleSecondaryImagesUpload);
</script>
