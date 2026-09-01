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

    // Récupérer la catégorie du brouillon en mode édition
    $draftCategory = null;
    if ($isEditMode && $draft_id) {
        $stmt = $db->prepare("SELECT category FROM draft_events WHERE id = ? AND user_id = ?");
        $stmt->execute([$draft_id, $_SESSION['user_id']]);
        $draftRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $draftCategory = $draftRow['category'] ?? null;
        error_log("✅ Catégorie du brouillon récupérée : " . ($draftCategory ?: 'aucune'));
    }

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
require_once __DIR__ . '/../../includes/header.php';
error_log("✅ Header inclus");

?>

<!-- Dépendances CSS -->
<link rel="stylesheet" href="/assets/css/create-event.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- Dépendances JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>
<script src="/assets/js/event-images.js"></script>

<style>
/* Styles pour les champs requis */
/* Pas d'astérisque visuel sur les labels */

.progress-bar {
    background-color: var(--primary-color);
}

/* Styles pour le chargement */
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

/* Styles pour les images secondaires */
#secondaryImagesPreview {
    display: flex !important;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

#secondaryImagesPreview .col-md-4 {
    flex: 0 0 auto;
    width: calc(33.333% - 10px);
    padding: 0;
    margin: 0;
}

.secondary-image-container {
    position: relative;
    width: 100%;
    padding-top: 75%; /* Ratio 4:3 */
    overflow: hidden;
}

.secondary-image-container img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.secondary-image-container .remove-image-btn {
    position: absolute;
    top: 5px;
    right: 5px;
    z-index: 2;
}

/* Styles pour les inputs time */
input[type="time"]::-webkit-datetime-edit-ampm-field {
    display: none;
}

input[type="time"] {
    -webkit-appearance: textfield;
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>

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

    // Initialisation des sélecteurs d'heure
    flatpickr("#registration_start_time", timeConfig);
    flatpickr("#registration_end_time", timeConfig);

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

<div class="min-h-screen background-color">
    <div class="create-event-header">
        <div class="container">
            <nav class="page-breadcrumb" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/user/my-events.php">Mes événements</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Créer un événement</li>
                </ol>
            </nav>
            <h1 class="page-title">Créer un événement</h1>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
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
                <div class="progress mb-4">
                    <div id="progressBar" class="progress-bar" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

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
                                    <div class="col-md-4">
                                        <label for="category" class="form-label">Catégorie</label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="" disabled selected>Choisir une catégorie</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['code'] ?? '') ?>" <?= ($draftCategory === $cat['code']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Veuillez choisir une catégorie</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="date" class="form-label">Date</label>
                                        <input type="text" class="form-control flatpickr-date" id="date" name="date" placeholder="jj/mm/aaaa" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="startTime" class="form-label">Heure de départ</label>
                                        <input type="text" class="form-control time-picker-input" id="startTime" name="startTime" placeholder="09:00" required>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="registrationOpens" class="form-label">Ouverture des inscriptions</label>
                                        <input type="text" class="form-control flatpickr-date" id="registrationOpens" name="registrationOpens" placeholder="jj/mm/aaaa" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="registrationCloses" class="form-label">Fermeture des inscriptions</label>
                                        <input type="text" class="form-control flatpickr-date" id="registrationCloses" name="registrationCloses" placeholder="jj/mm/aaaa" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Organizer Information -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Organisateur</h3>
                                
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
                                        <label for="organizerName" class="form-label">Nom de l'organisation</label>
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
                                        <label for="organizerEmail" class="form-label">Email</label>
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
                    <div class="mt-4 d-flex justify-content-between form-navigation">
                        <div>
                            <button type="button" id="prevButton" class="btn btn-secondary" onclick="prevStep()">
                                <i class="bi bi-arrow-left"></i> Précédent
                            </button>
                            <button type="button" id="saveDraftButton" class="btn btn-outline-success" onclick="saveDraft()">
                                <i class="bi bi-save"></i> Enregistrer en brouillon
                            </button>
                        </div>
                        <div>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

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

    // Initialisation des champs d'heure
    flatpickr("#startTime", timeConfig);

    // Configuration des champs de date
    const dateConfig = {
        dateFormat: "d/m/Y",
        allowInput: true,
        locale: "fr",
        minDate: "today"
    };

    // Initialisation des champs de date
    flatpickr(".flatpickr-date", dateConfig);
});
</script>
