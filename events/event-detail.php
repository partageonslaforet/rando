<?php
// Démarrer la session avant tout output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fonction de journalisation qui affiche directement
function detail_log($message) {
    // Échapper les caractères spéciaux et les retours à la ligne
    $escaped = str_replace(
        ["\n", "\r", "'", '"'],
        ['\n', '\r', "\'", '\"'],
        $message
    );
    echo "<script>console.log('$escaped');</script>\n";
    echo "<!-- DEBUG DETAIL: " . htmlspecialchars($message) . " -->\n";
}

detail_log('🚀 Démarrage de event-detail.php');

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../../config/database.php';
$pdo = getConnection();
detail_log('✅ Connexion à la base de données établie');

// Vérifier si c'est une prévisualisation
$isPreview = isset($_GET['preview']) && $_GET['preview'] === 'true';
detail_log('📌 Mode prévisualisation: ' . ($isPreview ? 'oui' : 'non'));

// Vérifier si l'ID est fourni
if (!isset($_GET['id'])) {
    header('Location: /');
    exit;
}

$eventId = $_GET['id'];
detail_log('📌 ID: ' . $eventId);

try {
    // Construire la requête en fonction du mode
    if ($isPreview) {
        detail_log('🔍 Récupération du brouillon...');
        $query = "SELECT * FROM event_drafts WHERE id = :id AND status = 'draft'";
    } else {
        detail_log('🔍 Récupération de l\'événement...');
        $query = "
            SELECT 
                e.*,
                GROUP_CONCAT(DISTINCT CASE WHEN ei.is_main = 1 THEN ei.image_path END) as main_image,
                GROUP_CONCAT(DISTINCT CASE WHEN ei.is_main = 0 THEN ei.image_path END SEPARATOR '|') as secondary_images,
                o.name as organizer_name,
                o.description as organizer_description,
                o.logo_path as organizer_logo,
                o.email as organizer_email,
                o.phone as organizer_phone,
                o.website as organizer_website
            FROM events e
            LEFT JOIN event_images ei ON e.id = ei.event_id
            LEFT JOIN organizer_profiles o ON e.user_id = o.user_id
            WHERE e.id = :id
            GROUP BY e.id";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        detail_log('❌ Événement non trouvé');
        die("<div class='alert alert-danger'>Événement non trouvé</div>");
    }

    if ($isPreview) {
        $event['main_image'] = '/assets/images/events/default-event.jpg';
        $event['secondary_images'] = null;
    }

    if (!empty($event['coordinates'])) {
        list($event['latitude'], $event['longitude']) = explode(',', $event['coordinates']);
    }

} catch (Exception $e) {
    $error = $e->getMessage();
    detail_log('❌ Erreur: ' . $error);
    die("<div class='alert alert-danger'>Une erreur est survenue: $error</div>");
}

// Définir le titre de la page et les styles additionnels
$pageTitle = isset($event['title']) ? $event['title'] : 'Détail de l\'événement';
$additionalStyles = '<link rel="stylesheet" href="/assets/css/event-detail.css">';

// Inclure le header
require_once __DIR__ . '/../../includes/header-solid.php';

// Préparer les images
$mainImage = !empty($event['main_image']) ? $event['main_image'] : '/assets/images/events/default-event.jpg';
$secondaryImages = !empty($event['secondary_images']) ? array_filter(explode('|', $event['secondary_images'])) : [];
?>

<div class="container">
    <div class="page-container">
        <!-- Main Content -->
        <main class="main-content">
            <!-- Event Details Section -->
            <div class="event-details">
                <div class="event-header animate-fade-in">
                    <h1 class="event-title"><?= htmlspecialchars($event['title']) ?></h1>
                    
                    <!-- Meta Information -->
                    <div class="event-meta">
                        <?php if (!empty($event['date'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?= date('d/m/Y', strtotime($event['date'])) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($event['category'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <span><?= htmlspecialchars($event['category']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($event['location'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($event['location']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($event['city'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-location-arrow"></i>
                            <span><?= htmlspecialchars($event['city']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($event['description'])): ?>
                <div class="event-description animate-fade-in">
                    <?= nl2br(htmlspecialchars($event['description'])) ?>
                </div>
                <?php endif; ?>
                
                <!-- Map -->
                <?php if (!empty($event['latitude']) && !empty($event['longitude'])): ?>
                <div class="event-map animate-fade-in" id="eventMap"></div>
                <?php endif; ?>
            </div>

            <!-- Gallery Section -->
            <?php if (!empty($mainImage) || !empty($secondaryImages)): ?>
            <div class="gallery-section">
                <h2 class="section-title">Galerie photos</h2>
                <div class="gallery-grid">
                    <!-- Main image -->
                    <?php if (!empty($mainImage)): ?>
                    <div class="main-image animate-fade-in">
                        <a href="<?= htmlspecialchars($mainImage) ?>" data-fancybox="gallery">
                            <img src="<?= htmlspecialchars($mainImage) ?>" 
                                 alt="<?= htmlspecialchars($event['title']) ?>" 
                                 class="img-fluid">
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Secondary images -->
                    <?php if (!empty($secondaryImages)): ?>
                    <div class="secondary-images">
                        <?php foreach ($secondaryImages as $index => $image): ?>
                        <div class="secondary-image animate-fade-in">
                            <a href="<?= htmlspecialchars($image) ?>" data-fancybox="gallery">
                                <img src="<?= htmlspecialchars($image) ?>" 
                                     alt="Image <?= $index + 2 ?> - <?= htmlspecialchars($event['title']) ?>" 
                                     class="img-fluid">
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>

        <!-- Sidebar -->
        <?php if (!empty($event['organizer_name'])): ?>
        <aside class="sidebar animate-fade-in">
            <div class="organizer-section">
                <div class="organizer-header">
                    <?php if (!empty($event['organizer_logo'])): ?>
                        <img src="<?= htmlspecialchars($event['organizer_logo']) ?>" 
                             alt="Logo <?= htmlspecialchars($event['organizer_name']) ?>" 
                             class="organizer-logo">
                    <?php endif; ?>
                    <div class="organizer-info">
                        <h3><?= htmlspecialchars($event['organizer_name']) ?></h3>
                        <?php if (!empty($event['organizer_description'])): ?>
                            <p><?= htmlspecialchars($event['organizer_description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="organizer-contact">
                    <?php if (!empty($event['organizer_email'])): ?>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:<?= htmlspecialchars($event['organizer_email']) ?>">
                            <?= htmlspecialchars($event['organizer_email']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($event['organizer_phone'])): ?>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <a href="tel:<?= htmlspecialchars($event['organizer_phone']) ?>">
                            <?= htmlspecialchars($event['organizer_phone']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($event['organizer_website'])): ?>
                    <div class="contact-item">
                        <i class="fas fa-globe"></i>
                        <a href="<?= htmlspecialchars($event['organizer_website']) ?>" target="_blank">
                            Site web
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($event['latitude']) && !empty($event['longitude'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the map
    var map = L.map('eventMap').setView([<?= $event['latitude'] ?>, <?= $event['longitude'] ?>], 13);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Add a marker
    L.marker([<?= $event['latitude'] ?>, <?= $event['longitude'] ?>]).addTo(map);
});
</script>
<?php endif; ?>