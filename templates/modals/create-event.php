<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_log("🚀 Début create-event.php");

// Charger le logger avant les dépendances critiques
require_once __DIR__ . '/../../logs/error.log.php';

// Inclure les dépendances dans le bon ordre
try {
    require_once __DIR__ . '/../../config/database.php';
    error_log("✅ database.php chargé");

    require_once __DIR__ . '/../../includes/functions.php';
    error_log("✅ functions.php chargé");

    require_once __DIR__ . '/../../includes/auth_check.php';
    error_log("✅ auth_check.php chargé");

    require_once __DIR__ . '/../../src/Models/EventCategory.php';
    error_log("✅ EventCategory.php chargé");

    require_once __DIR__ . '/../../includes/organizer_profile.php';
    error_log("✅ organizer_profile.php chargé");
} catch (Throwable $e) {
    error_log("❌ Erreur lors du chargement des dépendances : " . $e->getMessage());
    logError('create-event.php', 'Échec du chargement des dépendances', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
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
$openCreateModal = (isset($_GET['create']) && $_GET['create'] == '1');

if ($draft_id) {
    // Mode édition : vérifier que le brouillon existe et appartient à l'utilisateur
    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT id FROM draft_events WHERE id = ? AND user_id = ?");
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

    // Récupérer les catégories du brouillon en mode édition
    $draftCategories = [];
    if ($isEditMode && $draft_id) {
        $stmt = $db->prepare("SELECT category_id FROM draft_event_category_links WHERE draft_event_id = ?");
        $stmt->execute([$draft_id]);
        $draftCategories = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $draftCategories = array_map('intval', $draftCategories);
        error_log("✅ Catégories du brouillon récupérées : " . (count($draftCategories) > 0 ? implode(', ', $draftCategories) : 'aucune'));
    }

} catch (PDOException $e) {
    error_log("❌ Erreur lors de la récupération des informations : " . $e->getMessage());
    header('Location: /?error=database_error');
    exit;
} catch (Exception $e) {
    error_log("❌ Erreur inattendue : " . $e->getMessage());
    header('Location: /?error=unexpected_error');
    exit;
}

?>

<!-- Dépendances CSS -->
<link rel="stylesheet" href="/assets/css/create-event.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- Dépendances JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>
<script src="/assets/js/event-images.js"></script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration du sélecteur de date
    flatpickr(".flatpickr-date", {
        locale: "fr",
        dateFormat: "d/m/Y",
        allowInput: true,
        minDate: "today",
        maxDate: new Date().fp_incr(365),
        altInput: true,
        altFormat: "d/m/Y",
        formatDate: (date) => {
            return date.toLocaleDateString('fr-FR');
        }
    });

    // Configuration des sélecteurs d'heure
    const timeConfig = {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        minuteIncrement: 15,
        defaultHour: 8,
        allowInput: true,
        disableMobile: true,
        static: true,
        placeholder: "HH:MM",
        locale: {
            ...flatpickr.l10ns.fr,
            time_24hr: true
        }
    };

    // Initialisation des sélecteurs d'heure d'inscription
    flatpickr("#registrationOpens", timeConfig);
    flatpickr("#registrationCloses", timeConfig);

    // Écouteur pour l'upload d'images secondaires
    const secondaryImages = document.getElementById('secondaryImages');
    if (secondaryImages) {
        secondaryImages.addEventListener('change', handleSecondaryImagesUpload);
    }
});

// Tableau pour stocker les images secondaires
let secondaryImagesArray = [];

// Fonction pour mettre à jour l'affichage des images secondaires
function updateSecondaryImagesPreview(data) {
    console.log('🔄 Mise à jour des images secondaires avec:', data);
    const container = document.getElementById('secondaryImagesPreview');
    if (!container) return;

    // Récupérer les images existantes
    const existingImages = Array.from(container.querySelectorAll('img')).map(img => ({
        path: img.src,
        id: img.dataset.imageId
    }));
    console.log('📌 Images existantes:', existingImages);

    // Si on reçoit des données du serveur, on ajoute les nouvelles images
    if (data && data.secondaryImages) {
        console.log('📌 Nouvelles images reçues:', data.secondaryImages);
        
        // Fusionner les nouvelles images avec les existantes
        const allImages = [...existingImages];
        data.secondaryImages.forEach(newImage => {
            if (!allImages.some(img => img.id === newImage.id)) {
                allImages.push(newImage);
            }
        });
        console.log('📌 Images après fusion:', allImages);

        // Afficher toutes les images
        container.style.display = 'flex';
        allImages.forEach((image, index) => {
            // Vérifier si l'image existe déjà
            const existingImage = container.querySelector(`img[data-image-id="${image.id}"]`);
            if (!existingImage) {
                console.log('➕ Ajout d\'une nouvelle image:', image);
                
                const col = document.createElement('div');
                col.classList.add('col-md-4');
                
                const imgContainer = document.createElement('div');
                imgContainer.classList.add('secondary-image-container', 'position-relative');
                
                const img = document.createElement('img');
                img.src = image.path;
                img.alt = `Image secondaire ${index + 1}`;
                img.classList.add('img-fluid', 'rounded');
                img.dataset.imageId = image.id;
                
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.classList.add('remove-image-btn');
                removeBtn.innerHTML = '×';
                removeBtn.addEventListener('click', (e) => removeSecondaryImage(e, image.id));
                
                imgContainer.appendChild(img);
                imgContainer.appendChild(removeBtn);
                col.appendChild(imgContainer);
                container.appendChild(col);
            }
        });
    }
}

// Fonction pour gérer l'upload des images secondaires
async function handleSecondaryImagesUpload(event) {
    const files = event.target.files;
    if (!files || files.length === 0) return;
    
    // Vérifier la taille de chaque fichier
    for (let file of files) {
        if (file.size > 2 * 1024 * 1024) {
            showToast(`L'image ${file.name} ne doit pas dépasser 2Mo`, 'warning');
            event.target.value = '';
            return;
        }
    }
    
    try {
        const formData = new FormData(document.getElementById('createEventForm'));
        
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Erreur lors de l\'upload');
        }
        
        // Mettre à jour l'affichage avec les nouvelles images
        updateSecondaryImagesPreview(result);
        
        // Vider l'input pour permettre de sélectionner à nouveau le même fichier
        event.target.value = '';
        
    } catch (error) {
        console.error('❌ Erreur lors de l\'upload:', error);
        showToast(error.message || 'Erreur lors de l\'upload des images', 'error');
        event.target.value = '';
    }
}

// Fonction pour supprimer une image secondaire
async function removeSecondaryImage(event, imageId) {
    event?.preventDefault();
    event?.stopPropagation();
    
    console.log('🔄 Tentative de suppression de l\'image secondaire:', imageId);
    
    if (!imageId) {
        console.error('❌ Erreur: ID de l\'image non fourni');
        showToast('Erreur lors de la suppression de l\'image', 'error');
        return;
    }
    
    try {
        console.log('📝 Préparation du FormData pour la suppression');
        const formData = new FormData(document.getElementById('createEventForm'));
        formData.append('deleteImage', imageId);
        
        console.log('🌐 Envoi de la requête de suppression');
        const response = await fetch('/api/events/save_draft.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Erreur lors de la suppression');
        }
        
        // Supprimer l'élément du DOM
        const imageElement = document.querySelector(`img[data-image-id="${imageId}"]`);
        if (imageElement) {
            const container = imageElement.closest('.col-md-4');
            if (container) {
                container.remove();
            }
        }
        
        showToast('Image supprimée avec succès', 'success');
        
    } catch (error) {
        console.error('❌ Erreur lors de la suppression:', error);
        showToast(error.message || 'Erreur lors de la suppression de l\'image', 'error');
    }
}

</script>

<div id="loading-overlay" style="display: none;">
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Chargement...</span>
        </div>
        <div class="loading-text">Chargement...</div>
    </div>
</div>

<div class="modal fade" id="createEventModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="createEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" id="createEventDialog">
        <div class="modal-content">
            <div class="modal-header">
                <!-- <h2 class="modal-title" id="createEventModalLabel">Créer un événement</h2> -->
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="create-event-header">
                    <div class="container">
                        <nav class="page-breadcrumb" aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="/user/my-events.php">Mes événements</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Créer un événement</li>
                            </ol>
                        </nav>
                        <h1 class="page-title">Créer un événement</h1>
                        <p class="page-subtitle">Complétez les informations essentielles. Vous pourrez enregistrer un brouillon à tout moment.</p>
                    </div>
                </div>

                <div class="container py-2">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Steps -->
                <div class="steps mb-5 three-steps">
                    <div class="step active" data-step="1" data-title="L'événement">
                        <button class="step-button" onclick="setStep(1)">1</button>
                    </div>
                    <div class="step" data-step="2" data-title="Lieu et parcours">
                        <button class="step-button" onclick="setStep(2)">2</button>
                    </div>
                    <div class="step" data-step="3" data-title="Photos et aperçu">
                        <button class="step-button" onclick="setStep(3)">3</button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <!-- <div class="progress mb-4">
                    <div id="progressBar" class="progress-bar" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                </div> -->

                <!-- Form -->
                <form id="createEventForm" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <?php if ($isEditMode): ?>
                        <!-- Champ caché pour le draftId en mode édition -->
                        <input type="hidden" name="draftId" value="<?php echo htmlspecialchars($draft_id); ?>">
                    <?php else: ?>
                        <!-- Champ caché pour le draftId en mode création -->
                        <input type="hidden" name="draftId" id="draftId">
                    <?php endif; ?>
                    
                    <!-- Conteneur d'erreurs global -->
                    <div class="alert alert-danger d-none" id="globalErrorContainer" role="alert">
                        <ul class="list-unstyled mb-0" id="globalErrorList"></ul>
                    </div>

                    <!-- Step 1 -->
                    <div class="step-content" id="step1">
                        <!-- Basic Information -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <p class="form-intro">Décrivez votre activité afin que les participants puissent facilement la trouver.</p>

                                <div class="mb-3">
                                    <label for="title" class="form-label">Titre de l'événement</label>
                                    <input type="text" class="form-control" id="title" name="title" placeholder="Ex. Randonnée familiale en forêt de Soignes" required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="Présentez brièvement l'activité, le public visé et les informations importantes..." required></textarea>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Catégories</label>
                                        <div class="category-checkboxes d-flex flex-column align-items-start">
                                            <?php foreach ($categories as $cat): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="cat_<?= (int) $cat['id'] ?>" name="categories[]" value="<?= (int) $cat['id'] ?>" <?= in_array((int) $cat['id'], $draftCategories) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="cat_<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="invalid-feedback">Veuillez choisir au moins une catégorie</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date" class="form-label">Date</label>
                                            <input type="text" class="form-control flatpickr-date" id="date" name="date" placeholder="jj / mm / aaaa" required>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <label for="registrationOpens" class="form-label">Ouverture des inscriptions</label>
                                                <input type="text" class="form-control time-picker-input" id="registrationOpens" name="registrationOpens" placeholder="00:00" required>
                                            </div>
                                            <div class="col-6">
                                                <label for="registrationCloses" class="form-label">Fermeture des inscriptions</label>
                                                <input type="text" class="form-control time-picker-input" id="registrationCloses" name="registrationCloses" placeholder="00:00" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="organizerId" name="organizerId" value="">

                        <!-- Adresse du jour -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <!-- <h3 class="card-title">Adresse du jour</h3> -->
                                <p class="form-intro">Indiquez le point de rendez-vous de l'activité.</p>
                                <div class="mb-3">
                                    <label for="meeting_name" class="form-label">Nom du local</label>
                                    <input type="text" class="form-control" id="meeting_name" name="meeting_name" placeholder="Ex. Parking de l'église">
                                </div>
                                <div class="mb-3">
                                    <label for="meeting_address" class="form-label">Adresse</label>
                                    <input type="text" class="form-control" id="meeting_address" name="meeting_address" placeholder="Rue, numéro, localité">
                                </div>
                                <input type="hidden" id="meeting_coordinates" name="meeting_coordinates">
                                <div class="mb-3">
                                    <div id="meetingMap" style="height: 300px; border-radius: 8px; width: 100%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-content d-none" id="step2">
                        <!-- Location -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Localisation</h3>
                                
                                <div class="form-group mb-3">
                                    <label for="location_name" class="form-label">Nom du local</label>
                                    <input type="text" class="form-control" id="location_name" name="location_name" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Adresse</label>
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
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5 class="mb-0">Parcours 1</h5>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRoute(this)" data-route-index="0">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Nom du parcours</label>
                                            <input type="text" class="form-control" name="routes[0][name]" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-2">
                                                    <label class="form-label">Distance (km)</label>
                                                    <input type="number" step="0.1" class="form-control" name="routes[0][distance]" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-2">
                                                    <label class="form-label">Dénivelé (m)</label>
                                                    <input type="number" class="form-control" name="routes[0][elevation]">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-2">
                                                    <label class="form-label">Prix (€)</label>
                                                    <input type="number" step="0.50" class="form-control" name="routes[0][price]" required>
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
                                <button type="button" id="addBtn" class="btn btn-outline-primary" onclick="addRoute()">
                                    <i class="bi bi-plus-circle"></i> Ajouter un parcours
                                </button>
                            </div>
                        </div>

                        <!-- Carte GPX avec légende -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Aperçu des parcours</h3>
                                <div class="mb-2">
                                    <div id="gpxMap" style="height: 400px; margin-bottom: 1rem; border-radius: 0.5rem;"></div>
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
                                                   class="hidden">
                                        </label>
                                    </div>
                                    <div class="form-text">Cette image sera affichée en couverture de votre événement. Format recommandé: carré. Poids maximum: 5 Mo</div>
                                </div>

                                <!-- Images secondaires -->
                                <div class="mb-3">
                                    <label class="form-label">Images secondaires</label>
                                    <div class="secondary-images-container">
                                        <div id="secondaryImagesPreview" style="display: none;"></div>
                                        <div class="secondary-images-input">
                                            <label class="image-upload-button">
                                                <i class="bi bi-upload"></i>
                                                <span>Choisir des images</span>
                                                <input type="file" 
                                                       id="secondaryImages"
                                                       name="secondaryImages[]"
                                                       accept="image/*"
                                                       multiple
                                                       class="hidden">
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-text">Format recommandé: 1920x1080px. Poids maximum: 2 Mo par image</div>
                                </div>
                            </div>
                        </div>

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
                    <div class="mt-4 form-navigation">
                        <div class="form-nav-left">
                            <button type="button" id="prevButton" class="btn btn-secondary" onclick="prevStep()">
                                <i class="bi bi-arrow-left"></i> Précédent
                            </button>
                            <button type="button" id="saveDraftButton" class="btn btn-outline-success" onclick="saveDraft()">
                                <i class="bi bi-save"></i> Enregistrer en brouillon
                            </button>
                        </div>
                        <!-- <button type="button" id="nextStepIcon" class="btn-next-step" onclick="nextStep()" aria-label="Continuer">
                            <i class="bi bi-arrow-down-circle-fill"></i>
                        </button> -->
                        <div class="form-nav-right">
                            <button type="button" id="nextButton" class="btn btn-primary" onclick="nextStep()">
                                Continuer : lieu et parcours <i class="bi bi-arrow-right"></i>
                            </button>
                            <button type="button" id="publishButton" class="btn btn-success d-none" onclick="event.preventDefault(); submitEvent();">
                                Publier <i class="bi bi-check-lg"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($createModalOnly) || !$createModalOnly): ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php endif; ?>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
<script src="/assets/js/event-validation.js"></script>
<script src="/assets/js/event-maps.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration des champs d'heure avec Flatpickr
    const timeConfig = {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        minuteIncrement: 5,
        defaultHour: 8,
        disableMobile: true,
        allowInput: true,
        static: true,
        locale: {
            time_24hr: true,
            meridiem: false
        }
    };

    // Configuration des champs de date
    const dateConfig = {
        dateFormat: "d/m/Y",
        allowInput: true,
        locale: "fr",
        minDate: "today"
    };

    // Initialisation des champs de date
    flatpickr(".flatpickr-date", dateConfig);

    <?php if (!empty($openCreateModal)): ?>
    // Ouvrir le modal automatiquement si demandé
    const createEventModal = document.getElementById('createEventModal');
    if (createEventModal && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(createEventModal).show();
    }
    <?php endif; ?>
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const meetingMapEl = document.getElementById('meetingMap');
    if (!meetingMapEl || typeof L === 'undefined') return;

    const meetingMap = L.map(meetingMapEl).setView([50.5039, 4.4699], 8);
    window.meetingMap = meetingMap;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(meetingMap);

    const createEventModalEl = document.getElementById('createEventModal');
    if (createEventModalEl) {
        createEventModalEl.addEventListener('shown.bs.modal', function() {
            if (window.meetingMap) window.meetingMap.invalidateSize();
        });
    }

    let meetingMarker = null;
    const addressInput = document.getElementById('meeting_address');
    const coordsInput = document.getElementById('meeting_coordinates');

    function setMeetingMarker(lat, lng) {
        if (meetingMarker) {
            meetingMap.removeLayer(meetingMarker);
        }
        meetingMarker = L.marker([lat, lng]).addTo(meetingMap);
        meetingMap.setView([lat, lng], 15);
        if (coordsInput) {
            coordsInput.value = lat + ',' + lng;
        }
    }

    if (addressInput) {
        addressInput.addEventListener('change', function() {
            const address = addressInput.value.trim();
            if (!address) return;
            fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(address) + '&limit=1&format=json')
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        setMeetingMarker(parseFloat(data[0].lat), parseFloat(data[0].lon));
                    }
                })
                .catch(error => console.error('Géocodage impossible:', error));
        });
    }

    meetingMap.on('click', function(e) {
        setMeetingMarker(e.latlng.lat, e.latlng.lng);
        if (addressInput) {
            fetch('https://nominatim.openstreetmap.org/reverse?lat=' + e.latlng.lat + '&lon=' + e.latlng.lng + '&zoom=18&format=json')
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        addressInput.value = data.display_name;
                    }
                })
                .catch(error => console.error('Géocodage inverse impossible:', error));
        }
    });
});
</script>
