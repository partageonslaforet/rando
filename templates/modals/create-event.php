<?php
/**
 * localisation: templates/modals/create-event.php
 * Role: Modale : Create Event
 * Usage: Fenetre modale de creation d evenement
 * Dépendances: Aucune
 */

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

    require_once __DIR__ . '/../../src/Models/organizer_profile.php';
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
    header('Location: /?showLogin=1&redirect=' . urlencode($_SERVER['REQUEST_URI']));
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
            header('Location: /?login=required&error=' . urlencode('Brouillon non trouvé ou non autorisé'));
            exit;
        }
        error_log("✅ Brouillon vérifié (ID: $draft_id)");
        $isEditMode = true;
    } catch (Exception $e) {
        error_log("❌ Erreur lors de la vérification du brouillon : " . $e->getMessage());
        header('Location: /?login=required&error=' . urlencode('Erreur lors de la vérification du brouillon'));
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
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Location: /?showLogin=1');
        exit;
    }

    // Récupérer les catégories actives
    $categoryManager = new EventCategory($db);
    $categories = $categoryManager->getAllActive();
    error_log("✅ Catégories récupérées : " . count($categories));

    // Récupérer les profils organisateur de l'utilisateur
    $organizerProfile = new OrganizerProfile($db, $_SESSION['user_id']);
    $organizerProfiles = $organizerProfile->getByUserId($_SESSION['user_id']);
    error_log("✅ Profils organisateur récupérés : " . count($organizerProfiles));

    $draft = [];

    // Récupérer les catégories du brouillon en mode édition
    $draftCategories = [];
    if ($isEditMode && $draft_id) {
        $stmt = $db->prepare("SELECT category_id FROM draft_event_category_links WHERE draft_event_id = ?");
        $stmt->execute([$draft_id]);
        $draftCategories = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $draftCategories = array_map('intval', $draftCategories);
        error_log("✅ Catégories du brouillon récupérées : " . (count($draftCategories) > 0 ? implode(', ', $draftCategories) : 'aucune'));

        $stmt = $db->prepare("SELECT * FROM draft_events WHERE id = ? AND user_id = ?");
        $stmt->execute([$draft_id, $_SESSION['user_id']]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        error_log("✅ Données du brouillon récupérées");

        $stmt = $db->prepare("SELECT * FROM draft_parcours WHERE event_id = ? ORDER BY id");
        $stmt->execute([$draft_id]);
        $draftRoutes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        error_log("✅ Parcours du brouillon récupérés : " . count($draftRoutes));

        // Si l'organisateur n'est pas présent sur le brouillon mais qu'on connaît l'événement d'origine, tenter de le récupérer depuis events
        try {
            $origId = isset($draft['original_event_id']) ? (int)$draft['original_event_id'] : 0;
            if ($origId && (empty($draft['organizer_id']) || empty($draft['organisation']))) {
                $ost = $db->prepare("SELECT organizer_id, organisation, user_id FROM events WHERE id = ?");
                $ost->execute([$origId]);
                if ($row = $ost->fetch(PDO::FETCH_ASSOC)) {
                    if (empty($draft['organizer_id']) && !empty($row['organizer_id'])) {
                        $draft['organizer_id'] = (int)$row['organizer_id'];
                    }
                    if (empty($draft['organisation']) && !empty($row['organisation'])) {
                        $draft['organisation'] = $row['organisation'];
                    }
                    // Fallback via le premier profil organisateur du propriétaire de l'événement
                    if (empty($draft['organizer_id']) && !empty($row['user_id'])) {
                        try {
                            $pst = $db->prepare("SELECT id, name, email FROM organizer_profiles WHERE user_id = ? ORDER BY id ASC LIMIT 1");
                            $pst->execute([(int)$row['user_id']]);
                            if ($prof = $pst->fetch(PDO::FETCH_ASSOC)) {
                                $draft['organizer_id'] = (int)$prof['id'];
                                if (empty($draft['organisation']) && !empty($prof['name'])) {
                                    $draft['organisation'] = $prof['name'];
                                }
                                if (function_exists('logError')) {
                                    logError('create-event.php', 'Organizer fallback from owner profile', [
                                        'draft_id' => $draft_id,
                                        'event_user_id' => (int)$row['user_id'],
                                        'fallback_organizer_id' => (int)$prof['id'],
                                        'fallback_organizer_name' => $prof['name'] ?? null,
                                    ]);
                                }
                            }
                        } catch (Throwable $e) {
                            if (function_exists('logError')) {
                                logError('create-event.php', 'Owner profile fallback failed', [
                                    'draft_id' => $draft_id,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }
                    if (function_exists('logError')) {
                        logError('create-event.php', 'Organizer backfilled from original event', [
                            'draft_id' => $draft_id,
                            'original_event_id' => $origId,
                            'draft.organizer_id' => $draft['organizer_id'] ?? null,
                            'draft.organisation' => $draft['organisation'] ?? null,
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('create-event.php', 'Backfill organizer failed', [
                    'draft_id' => $draft_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        // Pré-remplir les contacts depuis draft_contacts
        $prefillContacts = [];
        try {
            $cst = $db->prepare("SELECT name, email, phone FROM draft_contacts WHERE event_id = ? ORDER BY id ASC");
            $cst->execute([$draft_id]);
            $prefillContacts = $cst->fetchAll(PDO::FETCH_ASSOC) ?: [];
            error_log("✅ Contacts du brouillon récupérés: " . count($prefillContacts));
        } catch (Throwable $e) {
            error_log("❌ Erreur récupération contacts brouillon: " . $e->getMessage());
        }
    } else {
        $draftRoutes = [];
        $prefillContacts = [];
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

$draftLat = $draftLng = '';
$draftMeetingLat = $draftMeetingLng = '';
if (!empty($draft['coordinates'])) {
    list($draftLat, $draftLng) = array_map('trim', explode(',', $draft['coordinates'], 2));
}
if (!empty($draft['meeting_coordinates'])) {
    list($draftMeetingLat, $draftMeetingLng) = array_map('trim', explode(',', $draft['meeting_coordinates'], 2));
}

?>

<!-- Dépendances CSS -->
<link rel="stylesheet" href="/assets/css/create-event.css">
<link rel="stylesheet" href="/assets/css/event-display.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- Dépendances JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>
<script src="/assets/js/events/event-images.js"></script>


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

    // NOTE : la gestion de l'upload/suppression des images (principale et secondaires)
    // est centralisée dans /assets/js/event-images.js (une seule source, un seul listener
    // par input, pour éviter les doubles soumissions).
});
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
                        <!-- <nav class="page-breadcrumb" aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="/user/my-events.php">Mes événements</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Créer un événement</li>
                            </ol>
                        </nav> -->
                        <div class="auth-icon" aria-hidden="true">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <h1 class="page-title">Créer un événement</h1>
                        <p class="page-subtitle">Complétez les informations essentielles. Vous pourrez enregistrer un brouillon à tout moment.</p>
                    </div>
                </div>

                <div class="container py-2">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Steps -->
                <div class="steps mb-5 three-steps">
                    <a class="step active" data-step="1" data-title="L'événement" href="#step1">
                        <span class="step-button">1</span>
                    </a>
                    <a class="step" data-step="2" data-title="Lieu et parcours" href="#step2">
                        <span class="step-button">2</span>
                    </a>
                    <a class="step" data-step="3" data-title="Photos et aperçu" href="#step3">
                        <span class="step-button">3</span>
                    </a>
                </div>

                <!-- Progress Bar -->
                <!-- <div class="progress mb-4">
                    <div id="progressBar" class="progress-bar" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                </div> -->

                <!-- Form -->
                <form id="createEventForm" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <?php if ($isEditMode): ?>
                        <!-- Champ caché pour le draftId en mode édition -->
                        <input type="hidden" name="draftId" id="draftId" value="<?php echo htmlspecialchars($draft_id); ?>">
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
                                    <label for="title" class="form-label required-field">Titre de l'événement</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($draft['title'] ?? '') ?>" placeholder="Ex. Randonnée familiale en forêt de Soignes" required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="Présentez brièvement l'activité, le public visé et les informations importantes..." required><?= htmlspecialchars($draft['description'] ?? '') ?></textarea>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label required-field">Catégories</label>
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
                                            <label for="date" class="form-label required-field">Date</label>
                                            <input type="text" class="form-control flatpickr-date" id="date" name="date" value="<?= !empty($draft['date']) ? date('d/m/Y', strtotime($draft['date'])) : '' ?>" placeholder="jj / mm / aaaa" required>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <label for="registrationOpens" class="form-label">Ouverture des inscriptions</label>
                                                <input type="text" class="form-control time-picker-input" id="registrationOpens" name="registrationOpens" value="<?= !empty($draft['registration_opens']) ? substr($draft['registration_opens'], 0, 5) : '' ?>" placeholder="00:00" required>
                                            </div>
                                            <div class="col-6">
                                                <label for="registrationCloses" class="form-label">Fermeture des inscriptions</label>
                                                <input type="text" class="form-control time-picker-input" id="registrationCloses" name="registrationCloses" value="<?= !empty($draft['registration_closes']) ? substr($draft['registration_closes'], 0, 5) : '' ?>" placeholder="00:00" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Adresse du jour -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <!-- <h3 class="card-title">Adresse du jour</h3> -->
                                <p class="form-intro">Indiquez le point de rendez-vous de l'activité.</p>
                                <div class="mb-3">
                                    <label for="meeting_name" class="form-label">Nom du local</label>
                                    <input type="text" class="form-control" id="meeting_name" name="meeting_name" value="<?= htmlspecialchars($draft['meeting_name'] ?? '') ?>" placeholder="Ex. Parking de l'église">
                                </div>
                                <div class="mb-3">
                                    <label for="meeting_address" class="form-label required-field">Adresse du point de rendez-vous</label>
                                    <input type="text" class="form-control" id="meeting_address" name="meeting_address" value="<?= htmlspecialchars($draft['meeting_address'] ?? '') ?>" placeholder="Rue, numéro, localité" required>
                                </div>
                                <input type="hidden" id="meeting_city" name="meeting_city" value="<?= htmlspecialchars($draft['meeting_city'] ?? '') ?>">
                                <input type="hidden" id="meeting_coordinates" name="meeting_coordinates" value="<?= htmlspecialchars($draft['meeting_coordinates'] ?? '') ?>">
                                <div class="mb-3">
                                    <div id="meetingMap" style="height: 300px; border-radius: 8px; width: 100%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-content d-none" id="step2">
                        <!-- Routes -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <!-- <h3 class="card-title">Parcours</h3> -->
                                <div id="routes-container">
                                    <?php
                                    $routesToDisplay = !empty($draftRoutes) ? $draftRoutes : [[]];
                                    foreach ($routesToDisplay as $index => $route):
                                        $routeName = htmlspecialchars($route['name'] ?? '');
                                        $routeDistance = !empty($route['distance']) ? htmlspecialchars($route['distance']) : '';
                                        $routeElevation = !empty($route['elevation_gain']) ? htmlspecialchars($route['elevation_gain']) : '';
                                        $routePrice = !empty($route['price']) && $route['price'] != '0.00' ? htmlspecialchars($route['price']) : '';
                                        $routeDesc = htmlspecialchars($route['description'] ?? '');
                                        $routeCategoryId = (int) ($route['category_id'] ?? 0);
                                        $routeDownloadable = !empty($route['gpx_downloadable']);
                                    ?>
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5 class="mb-0">Parcours <?= (int) $index + 1 ?></h5>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRoute(this)" data-route-index="<?= (int) $index ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Nom du parcours</label>
                                            <input type="text" class="form-control" name="routes[<?= (int) $index ?>][name]" value="<?= $routeName ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label required-field">Catégorie du parcours</label>
                                            <select class="form-select" name="routes[<?= (int) $index ?>][category_id]" required>
                                                <option value="">Choisir une catégorie</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= (int) $cat['id'] ?>" <?= $routeCategoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="row">
                                            <div class="mb-2">
                                                <label class="form-label">GPX <small class="form-text text-muted">(Importer un GPX rempli automatiquement la distance et le dénivelé)</small></label>
                                                <div class="input-group">
                                                    <input type="file" class="form-control" name="routes[<?= (int) $index ?>][gpx]" accept=".gpx" onchange="handleGpxUpload(this, <?= (int) $index ?>)">
                                                    <button type="button" class="btn btn-outline-danger" onclick="removeGpx(<?= (int) $index ?>)" title="Supprimer le GPX">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                <input type="hidden" name="routes[<?= (int) $index ?>][gpx_file]" value="<?= htmlspecialchars($route['gpx_file'] ?? '') ?>">
                                                <div class="gpx-file-label" id="gpxFileLabel<?= (int) $index ?>">
                                                    <?php if (!empty($route['gpx_file'])): ?>
                                                        Fichier : <?= htmlspecialchars(basename($route['gpx_file'])) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="gpx_downloadable_<?= (int) $index ?>" name="routes[<?= (int) $index ?>][gpx_downloadable]" value="1" <?= $routeDownloadable ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="gpx_downloadable_<?= (int) $index ?>">Autoriser le téléchargement du GPX</label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-2">
                                                    <label class="form-label required-field">Distance (km)</label>
                                                    <input type="number" step="0.1" class="form-control" name="routes[<?= (int) $index ?>][distance]" value="<?= $routeDistance ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-2">
                                                    <label class="form-label">Dénivelé (m)</label>
                                                    <input type="number" class="form-control" name="routes[<?= (int) $index ?>][elevation]" value="<?= $routeElevation ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-2">
                                                    <label class="form-label">Prix (€)</label>
                                                    <input type="number" step="0.01" class="form-control" name="routes[<?= (int) $index ?>][price]" value="<?= $routePrice ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Description du parcours</label>
                                            <textarea class="form-control" name="routes[<?= (int) $index ?>][description]" rows="3"><?= $routeDesc ?></textarea>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
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
                    </div>

                    <!-- Step 3 -->
                    <div class="step-content d-none" id="step3">
                        <!-- Images Upload -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Photos de l'événement</h3>

                                <div class="row g-4 photo-section">
                                    <!-- Image principale -->
                                    <div class="col-md-4 main-photo-col">
                                        <label class="form-label">
                                            <i class="bi bi-star-fill text-warning"></i> Photo de couverture
                                        </label>
                                        <div class="main-image-container">
                                            <img id="mainImagePreview" class="main-image-preview" style="display: none;">
                                            <label class="image-upload-button">
                                                <i class="bi bi-upload"></i>
                                                <span>Choisir l'image</span>
                                                <input type="file"
                                                       id="mainImage"
                                                       name="mainImage"
                                                       accept="image/*"
                                                       class="hidden">
                                            </label>
                                            <span class="main-image-badge"></span>
                                        </div>
                                        <p class="image-help">Carré recommandé. 5 Mo max.</p>
                                    </div>

                                    <!-- Images secondaires -->
                                    <div class="col-md-8 secondary-photos-col">
                                        <label class="form-label">Galerie secondaire</label>
                                        <div class="secondary-images-container">
                                            <div id="secondaryImagesPreview" class="secondary-images-preview"></div>
                                            <label class="secondary-images-add">
                                                <i class="bi bi-plus-lg"></i>
                                                <span>Ajouter</span>
                                                <input type="file"
                                                       id="secondaryImages"
                                                       name="secondaryImages[]"
                                                       accept="image/*"
                                                       multiple
                                                       class="hidden">
                                            </label>
                                        </div>
                                        <p class="image-help">Jusqu'à 3 images. 1920×1080 recommandé, 5 Mo max.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Choix de l'organisateur -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Organisateur</h3>
                                <div class="mb-3">
                                    <label for="organizerId" class="form-label">Profil organisateur</label>
                                    <select class="form-select" id="organizerId" name="organizerId">
                                        <option value="">Moi-même / compte principal</option>
                                        <option value="new" <?= (empty($draft['organizer_id']) && !empty($draft['organisation'])) ? 'selected' : '' ?>>+ Nouvel organisateur</option>
                                        <?php
                                        // Si le brouillon référence un organizer_id qui n'appartient pas au user courant, l'afficher quand même
                                        $selectedOrganizerId = isset($draft['organizer_id']) ? (int)$draft['organizer_id'] : 0;
                                        $ownedIds = array_map(function($p){ return (int)($p['id'] ?? 0); }, $organizerProfiles ?? []);
                                        $externalOrganizer = null;
                                        if ($selectedOrganizerId && !in_array($selectedOrganizerId, $ownedIds, true)) {
                                            try {
                                                $ost = $db->prepare("SELECT id, name, email FROM organizer_profiles WHERE id = ?");
                                                $ost->execute([$selectedOrganizerId]);
                                                $externalOrganizer = $ost->fetch(PDO::FETCH_ASSOC) ?: null;
                                            } catch (Throwable $e) { /* ignore */ }
                                        }
                                        if ($externalOrganizer): ?>
                                            <option value="<?= (int)$externalOrganizer['id'] ?>" selected>
                                                <?= htmlspecialchars($externalOrganizer['name']) ?>
                                                <?= !empty($externalOrganizer['email']) ? '(' . htmlspecialchars($externalOrganizer['email']) . ')' : '' ?>
                                            </option>
                                        <?php endif; ?>
                                        <?php foreach ($organizerProfiles as $profile): ?>
                                            <option value="<?= (int) $profile['id'] ?>" <?= (!empty($draft['organizer_id']) && (int) $draft['organizer_id'] === (int) $profile['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($profile['name']) ?>
                                                <?= !empty($profile['email']) ? '(' . htmlspecialchars($profile['email']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" class="form-control mt-2 <?= (empty($draft['organizer_id']) && !empty($draft['organisation'])) ? '' : 'd-none' ?>" id="organizerName" name="organizerName" placeholder="Nom du nouvel organisateur" value="<?= empty($draft['organizer_id']) && !empty($draft['organisation']) ? htmlspecialchars($draft['organisation']) : '' ?>">
                                    <div class="form-text">
                                        <a href="/pages/user/profile.php?tab=organizer" target="_blank">Gérer mes profils organisateur</a>
                                    </div>
                                    <?php if (function_exists('logError')) { logError('create-event.php', 'Organizer select rendered', [ 'draft_id' => $draft_id ?? null, 'selected_organizer_id' => $draft['organizer_id'] ?? null, 'organisation' => $draft['organisation'] ?? null ]); } ?>
                                </div>
                            </div>
                        </div>
                        <!-- Contact Information -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">Contacts supplémentaires</h3>
                                <div id="contacts-container">
                                    <?php if (!empty($prefillContacts)): ?>
                                        <?php foreach ($prefillContacts as $idx => $ct): ?>
                                            <div class="contact-field border rounded p-3 mb-3">
                                                <div class="d-flex justify-content-end">
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.contact-field').remove()">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Nom</label>
                                                    <input type="text" class="form-control" name="contacts[<?= (int)$idx ?>][name]" value="<?= htmlspecialchars($ct['name'] ?? '') ?>">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="contacts[<?= (int)$idx ?>][email]" value="<?= htmlspecialchars($ct['email'] ?? '') ?>">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label placeholder="+32 473 12 34 5">Téléphone</label>
                                                    <input type="tel" class="form-control" name="contacts[<?= (int)$idx ?>][phone]" value="<?= htmlspecialchars($ct['phone'] ?? '') ?>">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary" onclick="addContactField()">
                                    <i class="bi bi-plus-circle"></i> Ajouter un contact
                                </button>
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
                            <button type="button" id="nextButton" class="btn btn-secondary" onclick="nextStep()">
                                Continuer : Parcours <i class="bi bi-arrow-right"></i>
                            </button>
                            <button type="button" id="publishButton" class="btn btn-success d-none" onclick="event.preventDefault(); submitEvent();" aria-busy="false">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                <span class="btn-label">Publier</span>
                                <i class="bi bi-check-lg"></i>
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
<?php require_once __DIR__ . '/../../templates/layouts/footer.php'; ?>
<?php endif; ?>

<script>
const routeCategories = <?= json_encode(array_map(function($c) { return ['id' => (int)$c['id'], 'name' => $c['name']]; }, $categories), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>

<!-- Scripts -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
<script src="/assets/js/events/event-validation.js"></script>
<script src="/assets/js/events/event-display.js"></script>
<script src="/assets/js/events/event-maps.js"></script>
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
    const cityInput = document.getElementById('meeting_city');
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

    // Place un marqueur initial si les coordonnées ou l'adresse sont déjà renseignées
    const initialCoords = coordsInput && coordsInput.value ? coordsInput.value.split(',').map(parseFloat) : null;
    if (initialCoords && initialCoords.length === 2 && !isNaN(initialCoords[0]) && !isNaN(initialCoords[1])) {
        setMeetingMarker(initialCoords[0], initialCoords[1]);
    } else if (addressInput && addressInput.value.trim()) {
        addressInput.dispatchEvent(new Event('change'));
    }

    function extractCity(data) {
        const addr = data && data.address ? data.address : {};
        return addr.city || addr.town || addr.village || addr.municipality || addr.hamlet || '';
    }

    function extractCityFromText(address) {
        if (!address) return '';
        const match = address.match(/\b\d{4,5}\s+(.+)$/);
        if (match) {
            return match[1].trim();
        }
        const parts = address.split(/[,\s]+/);
        return parts[parts.length - 1].trim();
    }

    if (addressInput) {
        addressInput.addEventListener('change', function() {
            const address = addressInput.value.trim();
            if (!address) return;
            if (cityInput) {
                cityInput.value = extractCityFromText(address);
            }
            fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(address) + '&limit=1&format=json&addressdetails=1')
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        setMeetingMarker(parseFloat(data[0].lat), parseFloat(data[0].lon));
                        if (cityInput) {
                            const nominatimCity = extractCity(data[0]);
                            if (nominatimCity) {
                                cityInput.value = nominatimCity;
                            }
                        }
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
                    if (cityInput && data) {
                        cityInput.value = extractCity(data);
                    }
                })
                .catch(error => console.error('Géocodage inverse impossible:', error));
        }
    });
});
</script>
