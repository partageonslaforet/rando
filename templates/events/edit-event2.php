<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug du DOCUMENT_ROOT
error_log("DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT']);

// Fonction d'autoloading pour les classes avec logs détaillés
spl_autoload_register(function ($class) {
    error_log("Tentative de chargement de la classe: " . $class);
    
    // Convertit le namespace en chemin de fichier
    $class = str_replace('App\\', '', $class);
    $class = str_replace('\\', '/', $class);
    $file = $_SERVER['DOCUMENT_ROOT'] . '/src/' . $class . '.php';
    
    error_log("Tentative de chargement du fichier: " . $file);
    
    if (file_exists($file)) {
        error_log("Fichier trouvé, chargement de: " . $file);
        require_once $file;
    } else {
        error_log("ERREUR: Fichier non trouvé: " . $file);
    }
});

// Inclure les fichiers nécessaires dans l'ordre
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/flash_messages.php';

// Inclure les modèles directement
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Models/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Models/Organization.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Models/EventCategory.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Models/Event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/organizer_profile.php';  // Changé ce chemin uniquement

// Vérifier si la session n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Utiliser la fonction requireLogin() existante
requireLogin();

// Vérifier si un ID d'événement est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /pages/user/my-events.php?error=' . urlencode('ID d\'événement invalide'));
    exit;
}

// Initialisation des variables
$pageTitle = "Modifier l'événement";
$currentStep = 1;
$maxSteps = 4;

// Inclure l'en-tête
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/header-solid.php';
?>

<!-- CSS spécifique -->
<link rel="stylesheet" href="/assets/css/event-form.css">
<link rel="stylesheet" href="/assets/css/event-preview.css">

<?php
// Récupération des données de l'événement
try {
    $db = getConnection();
    
    // Récupérer l'événement original pour préremplir
    $stmt = $db->prepare("
        SELECT e.*, ei.image_path as main_image,
               GROUP_CONCAT(DISTINCT esi.image_path) as secondary_images
        FROM events e
        LEFT JOIN event_images ei ON e.id = ei.event_id AND ei.is_main = 1
        LEFT JOIN event_images esi ON e.id = esi.event_id AND esi.is_main = 0
        WHERE e.id = ?
        GROUP BY e.id
    ");
    $stmt->execute([$_GET['id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception("Événement non trouvé");
    }

    // Variables nécessaires pour le template
    $isDraft = false;
    $originalEventId = $_GET['id'];

    // Convertir les images secondaires en tableau
    $event['secondary_images'] = $event['secondary_images'] 
        ? explode(',', $event['secondary_images']) 
        : [];

    // Récupérer les données nécessaires pour le formulaire
    $categoryManager = new EventCategory($db);
    $categories = $categoryManager->getAllActive();
    
    $organizerManager = new OrganizerProfile($db);
    $organizers = $organizerManager->getByUserId($_SESSION['user_id']);

} catch (Exception $e) {
    header('Location: /pages/user/my-events.php?error=' . urlencode('Erreur lors de la récupération de l\'événement'));
    exit;
}
?>

<div class="container mt-5 pt-5">
    <div class="progress-container">
        <div class="progress">
            <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="step-indicators">
            <div class="step active" data-step="1">
                <span class="step-number">1</span>
                <span class="step-title">Informations de l'événement</span>
            </div>
            <div class="step" data-step="2">
                <span class="step-number">2</span>
                <span class="step-title">Informations de l'organisateur</span>
            </div>
            <div class="step" data-step="3">
                <span class="step-number">3</span>
                <span class="step-title">Prévisualisation</span>
            </div>
            <div class="step" data-step="4">
                <span class="step-number">4</span>
                <span class="step-title">Enregistrement</span>
            </div>
        </div>
    </div>

    <form id="editEventForm" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="original_event_id" value="<?= $originalEventId ?>">
        
        <!-- Étape 1 : Informations de l'événement -->
        <div class="step-content" id="step1">
            <h3>Informations de l'événement</h3>
            
            <div class="mb-3">
                <label for="title">Titre de l'événement</label>
                <input type="text" class="form-control" id="title" name="title" 
                       value="<?= htmlspecialchars($event['title']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="category">Catégorie</label>
                <select class="form-control" id="category" name="category" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" 
                                <?= ($event['category'] == $category['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" 
                          rows="5" required><?= htmlspecialchars($event['description']) ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="date">Date</label>
                    <input type="date" class="form-control" id="date" name="date" 
                           value="<?= $event['date'] ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="start_time">Heure de début</label>
                    <input type="time" class="form-control" id="start_time" name="start_time" 
                           value="<?= $event['start_time'] ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="end_time">Heure de fin</label>
                    <input type="time" class="form-control" id="end_time" name="end_time" 
                           value="<?= $event['end_time'] ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="location">Lieu</label>
                <input type="text" class="form-control" id="location" name="location" 
                       value="<?= htmlspecialchars($event['location']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="meeting_point">Point de rendez-vous</label>
                <input type="text" class="form-control" id="meeting_point" name="meeting_point" 
                       value="<?= htmlspecialchars($event['meeting_point']) ?>" required>
            </div>

            <div class="mb-3">
                <label>Images actuelles</label>
                <div class="current-images">
                    <?php if ($event['main_image']): ?>
                        <div class="main-image">
                            <img src="<?= htmlspecialchars($event['main_image']) ?>" alt="Image principale">
                            <p>Image principale</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($event['secondary_images'])): ?>
                        <div class="secondary-images">
                            <?php foreach ($event['secondary_images'] as $image): ?>
                                <div class="secondary-image">
                                    <img src="<?= htmlspecialchars($image) ?>" alt="Image secondaire">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="main_image">Nouvelle image principale</label>
                <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*">
            </div>

            <div class="mb-3">
                <label for="secondary_images">Nouvelles images secondaires</label>
                <input type="file" class="form-control" id="secondary_images" name="secondary_images[]" 
                       accept="image/*" multiple>
            </div>
        </div>

        <!-- Étape 2 : Informations de l'organisateur -->
        <div class="step-content" id="step2" style="display: none;">
            <h3>Informations de l'organisateur</h3>
            
            <div class="mb-3">
                <label for="organizer_profile">Profil organisateur</label>
                <select class="form-control" id="organizer_profile" name="organizer_profile" required>
                    <?php foreach ($organizers as $organizer): ?>
                        <option value="<?= $organizer['id'] ?>"
                                <?= ($event['organizer_id'] == $organizer['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($organizer['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="max_participants">Nombre maximum de participants</label>
                <input type="number" class="form-control" id="max_participants" name="max_participants" 
                       value="<?= $event['max_participants'] ?>" required min="1">
            </div>

            <div class="mb-3">
                <label for="difficulty">Niveau de difficulté</label>
                <select class="form-control" id="difficulty" name="difficulty" required>
                    <option value="easy" <?= ($event['difficulty'] == 'easy') ? 'selected' : '' ?>>Facile</option>
                    <option value="medium" <?= ($event['difficulty'] == 'medium') ? 'selected' : '' ?>>Moyen</option>
                    <option value="hard" <?= ($event['difficulty'] == 'hard') ? 'selected' : '' ?>>Difficile</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="equipment_needed">Équipement nécessaire</label>
                <textarea class="form-control" id="equipment_needed" name="equipment_needed" 
                          rows="3"><?= htmlspecialchars($event['equipment_needed']) ?></textarea>
            </div>
        </div>

        <!-- Étape 3 : Prévisualisation -->
        <div class="step-content" id="step3" style="display: none;">
            <h3>Prévisualisation</h3>
            <div id="preview-container">
                <!-- La prévisualisation sera chargée ici via JavaScript -->
            </div>
        </div>

        <!-- Navigation entre les étapes -->
        <div class="step-navigation">
            <button type="button" class="btn btn-secondary prev-step" style="display: none;">Précédent</button>
            <button type="button" class="btn btn-primary next-step">Suivant</button>
            <button type="submit" class="btn btn-success submit-event" style="display: none;">Valider les modifications</button>
        </div>
    </form>
</div>

<!-- Scripts spécifiques -->
<script src="/assets/js/event-form-validation.js"></script>
<script src="/assets/js/event-preview.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>