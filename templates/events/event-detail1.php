<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Définir le chemin racine
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Inclure les fichiers nécessaires
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';


// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonction de journalisation
function detail_log($message) {
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    error_log("[Event Detail] " . $message);
}

detail_log("=== DÉBUT DU TRAITEMENT ===");
detail_log("URI: " . $_SERVER['REQUEST_URI']);
detail_log("Session: " . print_r($_SESSION, true));
detail_log("GET: " . print_r($_GET, true));
detail_log("Document Root: " . $_SERVER['DOCUMENT_ROOT']);
detail_log("Script Filename: " . $_SERVER['SCRIPT_FILENAME']);
detail_log("PHP Self: " . $_SERVER['PHP_SELF']);

// Vérifier si c'est une prévisualisation
$isPreview = isset($isPreview) ? $isPreview : (isset($_GET['preview']) && $_GET['preview'] === 'true');
$isApi = isset($isApi) ? $isApi : (isset($_GET['api']) && $_GET['api'] === 'true');

// Inclure les dépendances nécessaires sauf si on est en mode API
if (!isset($skipInit)) {
    detail_log("Tentative d'inclusion de init.php");
    $initPath = $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';
    detail_log("Chemin init.php: " . $initPath);
    detail_log("Le fichier init.php existe: " . (file_exists($initPath) ? 'oui' : 'non'));
    require_once $initPath;
}

detail_log("Tentative d'inclusion de database.php");
$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
detail_log("Chemin database.php: " . $dbPath);
detail_log("Le fichier database.php existe: " . (file_exists($dbPath) ? 'oui' : 'non'));
require_once $dbPath;

detail_log("Tentative d'inclusion de helpers.php");
$helpersPath = $_SERVER['DOCUMENT_ROOT'] . '/includes/helpers.php';
detail_log("Chemin helpers.php: " . $helpersPath);
detail_log("Le fichier helpers.php existe: " . (file_exists($helpersPath) ? 'oui' : 'non'));
require_once $helpersPath;

try {
    $pdo = getConnection();
    $eventId = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$eventId) {
        detail_log("ID d'événement non valide");
        throw new Exception("ID d'événement non valide");
    }

    detail_log("Traitement de l'événement ID: " . $eventId . " (Preview: " . ($isPreview ? 'Oui' : 'Non') . ")");

    if ($isPreview) {
        // Code pour la prévisualisation
        $stmt = $pdo->prepare("SELECT * FROM event_drafts WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            detail_log("Prévisualisation non trouvée pour ID: " . $eventId);
            throw new Exception("Prévisualisation non trouvée");
        }

        // Récupérer les parcours de la prévisualisation
        $stmt = $pdo->prepare("SELECT * FROM events_draft_parcours WHERE draft_id = ?");
        $stmt->execute([$eventId]);
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $event['routes'] = $routes;

    } else {
        // Code pour les événements publiés
        $query = "
            SELECT 
                e.*,
                ei_main.storage_path as main_image,
                GROUP_CONCAT(DISTINCT ei_sec.storage_path SEPARATOR '|') as secondary_images,
                u.name as organizer_name,
                u.email as organizer_email,
                o.description as organizer_description,
                o.logo_path as organizer_logo,
                o.phone as organizer_phone,
                o.website as organizer_website,
                o.name as organization_name,
                c.name as category_name,
                c.icon as category_icon,
                c.color as category_color
            FROM events e
            LEFT JOIN (
                SELECT event_id, storage_path 
                FROM event_images 
                WHERE is_main = 1
            ) ei_main ON e.id = ei_main.event_id
            LEFT JOIN (
                SELECT event_id, storage_path 
                FROM event_images 
                WHERE is_main = 0
            ) ei_sec ON e.id = ei_sec.event_id
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN organizer_profiles o ON u.id = o.user_id
            LEFT JOIN event_categories c ON e.category_id = c.id
            WHERE e.id = :id
            GROUP BY e.id";

        detail_log("Exécution de la requête SQL pour l'événement ID: " . $eventId);
        $stmt = $pdo->prepare($query);
        $stmt->execute(['id' => $eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            detail_log("Aucune donnée trouvée pour l'événement ID: " . $eventId);
            throw new Exception("Événement non trouvé");
        }

        detail_log("Données de l'événement récupérées: " . print_r($event, true));

        // Vérifier les permissions
        $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
        $isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['user_id'];
        
        detail_log("Vérification des permissions:");
        detail_log("- Admin: " . ($isAdmin ? 'Oui' : 'Non'));
        detail_log("- Propriétaire: " . ($isOwner ? 'Oui' : 'Non'));
        detail_log("- Statut de l'événement: " . $event['status']);
        detail_log("- ID utilisateur connecté: " . ($_SESSION['user_id'] ?? 'non connecté'));
        detail_log("- ID propriétaire de l'événement: " . $event['user_id']);

        if ($event['status'] !== 'approved') {
            detail_log(" L'événement n'est pas approuvé");
        }
        if (!$isAdmin) {
            detail_log(" L'utilisateur n'est pas admin");
        }
        if (!$isOwner) {
            detail_log(" L'utilisateur n'est pas propriétaire");
        }

        if ($event['status'] !== 'approved' && !$isAdmin && !$isOwner) {
            detail_log(" Accès refusé - Événement non approuvé et utilisateur non autorisé");
            header('Location: /');
            exit;
        }

        // Vérifier la date
        $eventDate = strtotime($event['date']);
        $now = time();
        detail_log("Vérification des dates:");
        detail_log("- Date de l'événement: " . date('Y-m-d', $eventDate));
        detail_log("- Date actuelle: " . date('Y-m-d', $now));
        detail_log("- Timestamp événement: " . $eventDate);
        detail_log("- Timestamp actuel: " . $now);

        if ($eventDate < $now) {
            detail_log(" L'événement est passé");
        }
        if (!$isAdmin) {
            detail_log(" L'utilisateur n'est pas admin");
        }
        if (!$isOwner) {
            detail_log(" L'utilisateur n'est pas propriétaire");
        }

        if ($eventDate < $now && !$isAdmin && !$isOwner) {
            detail_log(" Accès refusé - Événement passé");
            header('Location: /');
            exit;
        }

        // Récupérer les parcours dans une requête séparée
        $query = "
            SELECT id, name, distance, elevation, gpx_file, price
            FROM event_parcours
            WHERE event_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$eventId]);
        $event['routes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        detail_log("Parcours récupérés: " . print_r($event['routes'], true));
    }

    detail_log("Routes trouvées: " . print_r($event['routes'], true));

    // Ajouter le style spécifique à la page de détail
    $additionalStyles = '<link rel="stylesheet" href="/assets/css/event-detail.css">';
    $additionalStyles .= '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';

    // Si ce n'est pas une prévisualisation API, inclure le header
    if (!$isApi) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
    }

} catch (Exception $e) {
    detail_log("Erreur: " . $e->getMessage());
    detail_log("Trace: " . $e->getTraceAsString());
    $_SESSION['error'] = $e->getMessage();
    header('Location: /');
    exit;
}

detail_log("=== FIN DU TRAITEMENT ===");
?>

<div class="container mt-4">
    <!-- En-tête avec image -->
    <div class="event-header" style="background-image: url('<?= !empty($event['main_image']) ? $event['main_image'] : '/assets/images/events/default-event.jpg' ?>');">
        <div class="event-header-content">
            <h1><?= htmlspecialchars($event['title'] ?? '') ?></h1>
            <?php if (!empty($event['category_name'])): ?>
                <div class="category-badge">
                    <span class="badge" style="background-color: <?= htmlspecialchars($event['category_color']) ?>">
                        <i class="bi <?= htmlspecialchars($event['category_icon']) ?>"></i>
                        <?= htmlspecialchars($event['category_name']) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-8">
            <!-- Description avec informations pratiques -->
            <div class="info-card">
                <h2><i class="bi bi-info-circle"></i> Description</h2>
                
                <!-- Informations pratiques -->
                <div class="practical-info mb-4">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <i class="bi bi-geo-alt"></i> Lieu : <?= htmlspecialchars($event['location']) ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <i class="bi bi-pin-map"></i> Adresse : <?= htmlspecialchars($event['venue']) ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <i class="bi bi-clock"></i> Début : <?= substr($event['start_time'], 0, 5) ?>
                        </div>
                        <?php if ($event['end_time'] && $event['end_time'] !== '00:00:00'): ?>
                        <div class="col-md-6 mb-2">
                            <i class="bi bi-clock-history"></i> Fin : <?= substr($event['end_time'], 0, 5) ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($event['category_name'])): ?>
                        <div class="col-md-6 mb-2">
                            <i class="bi <?= htmlspecialchars($event['category_icon']) ?>"></i> Catégorie : 
                            <span class="badge" style="background-color: <?= htmlspecialchars($event['category_color']) ?>">
                                <?= htmlspecialchars($event['category_name']) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <div class="event-description">
                    <?= nl2br(htmlspecialchars($event['description'])) ?>
                </div>
            </div>

            <!-- Parcours disponibles -->
            <?php if (!empty($event['routes'])): ?>
            <div class="info-card">
                <h2><i class="bi bi-map"></i> Parcours disponibles</h2>
                <div class="routes-list">
                    <?php foreach ($event['routes'] as $route): ?>
                        <div class="route-item">
                            <h3><?= htmlspecialchars($route['name']) ?></h3>
                            <div class="route-details">
                                <?php if (!empty($route['distance'])): ?>
                                    <span><i class="bi bi-signpost-2"></i> <?= number_format($route['distance'], 1, ',', ' ') ?> km</span>
                                <?php endif; ?>
                                <?php if (!empty($route['elevation'])): ?>
                                    <span><i class="bi bi-graph-up"></i> <?= number_format($route['elevation'], 0, ',', ' ') ?> m D+</span>
                                <?php endif; ?>
                                <?php if (!empty($route['price'])): ?>
                                    <span><i class="bi bi-tag"></i> <?= number_format($route['price'], 2, ',', ' ') ?> €</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($route['gpx_file'])): ?>
                                <a href="<?= htmlspecialchars($route['gpx_file']) ?>" class="btn btn-sm btn-outline-primary" download>
                                    <i class="bi bi-download"></i> Télécharger le GPX
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Carte -->
            <div class="info-card">
                <h2><i class="bi bi-pin-map"></i> Localisation</h2>
                <div id="map"></div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Organisateur -->
            <div class="info-card">
                <h2><i class="bi bi-person-circle"></i> Organisateur</h2>
                <div class="organizer-info">
                    <?php if (!empty($event['organizer_logo'])): ?>
                        <img src="<?= htmlspecialchars($event['organizer_logo']) ?>" alt="Logo organisateur" class="organizer-logo mb-3">
                    <?php endif; ?>
                    
                    <?php if (!empty($event['organization_name'])): ?>
                        <h3><?= htmlspecialchars($event['organization_name']) ?></h3>
                    <?php endif; ?>
                    
                    <?php if (!empty($event['organizer_description'])): ?>
                        <p class="mb-3"><?= nl2br(htmlspecialchars($event['organizer_description'])) ?></p>
                    <?php endif; ?>
                    
                    <div class="organizer-contact">
                        <?php if (!empty($event['organizer_email'])): ?>
                            <p><i class="bi bi-envelope"></i> <a href="mailto:<?= htmlspecialchars($event['organizer_email']) ?>"><?= htmlspecialchars($event['organizer_email']) ?></a></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($event['organizer_phone'])): ?>
                            <p><i class="bi bi-telephone"></i> <a href="tel:<?= htmlspecialchars($event['organizer_phone']) ?>"><?= htmlspecialchars($event['organizer_phone']) ?></a></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($event['organizer_website'])): ?>
                            <p><i class="bi bi-globe"></i> <a href="<?= htmlspecialchars($event['organizer_website']) ?>" target="_blank"><?= htmlspecialchars($event['organizer_website']) ?></a></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($event['organizer_address'])): ?>
                            <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['organizer_address']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Parcours -->
            <div class="info-card">
                <h2><i class="bi bi-map"></i> Parcours disponibles</h2>
                <?php if (!empty($event['routes'])): ?>
                    <?php foreach ($event['routes'] as $route): ?>
                        <div class="track-card mb-3">
                            <h3><?= htmlspecialchars($route['name']) ?></h3>
                            <div class="track-info">
                                <div class="track-stat">
                                    <i class="bi bi-arrows-move"></i>
                                    <?= number_format((float)$route['distance'], 1) ?> km
                                </div>
                                <div class="track-stat">
                                    <i class="bi bi-graph-up"></i>
                                    <?= isset($route['elevation']) ? (int)$route['elevation'] : 0 ?> m D+
                                </div>
                                <?php if (!empty($route['price'])): ?>
                                <div class="track-stat">
                                    <i class="bi bi-tag"></i>
                                    <?= number_format((float)$route['price'], 2) ?> €
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($route['gpx_file'])): ?>
                            <div class="track-actions">
                                <a href="<?= htmlspecialchars($route['gpx_file']) ?>" class="btn btn-sm btn-outline-primary" download>
                                    <i class="bi bi-download"></i> GPX
                                </a>
                                <button class="btn btn-sm btn-outline-secondary" onclick="showTrackOnMap('<?= htmlspecialchars($route['gpx_file']) ?>')">
                                    <i class="bi bi-eye"></i> Voir
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Aucun parcours disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($isPreview): ?>
<div class="preview-buttons-container">
    <div class="preview-buttons">
        <button type="button" class="btn btn-secondary" onclick="prevStep()">Retour</button>
        <button type="button" class="btn btn-primary" onclick="submitEvent()">Publier</button>
    </div>
</div>
<?php endif; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>

<script>
// Initialisation de la carte
document.addEventListener('DOMContentLoaded', function() {
    
    // Récupérer les coordonnées
    var coordinates = '<?= $event['coordinates'] ?>'.split(',').map(Number);
    
    if (coordinates.length === 2 && !isNaN(coordinates[0]) && !isNaN(coordinates[1])) {
        var map = L.map('map').setView(coordinates, 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: ' OpenStreetMap contributors'
        }).addTo(map);

        // Marqueur principal
        L.marker(coordinates)
            .addTo(map)
            .bindPopup("<?= htmlspecialchars($event['location'] . ' - ' . $event['venue']) ?>");

        // Fonction pour afficher les traces GPX
        window.showTrackOnMap = function(gpxUrl) {
            
            // Nettoyer les traces existantes
            map.eachLayer((layer) => {
                if (layer instanceof L.GPX) {
                    map.removeLayer(layer);
                }
            });

            // Charger la nouvelle trace
            new L.GPX(gpxUrl, {
                async: true,
                marker_options: {
                    startIconUrl: null,  // Désactiver les icônes par défaut
                    endIconUrl: null,    // Désactiver les icônes par défaut
                    shadowUrl: null      // Désactiver l'ombre
                },
                polyline_options: {
                    color: '#990047',
                    weight: 3,
                    opacity: 0.7
                }
            }).on('loaded', function(e) {
                map.fitBounds(e.target.getBounds());
            }).on('error', function(err) {
                console.error('Erreur de chargement GPX:', err);
                alert('Erreur lors du chargement du tracé GPX');
            }).addTo(map);
        };
    } else {
        console.error('Coordonnées invalides:', coordinates);
        document.getElementById('map').innerHTML = '<div class="alert alert-warning">Coordonnées non disponibles</div>';
    }
});
</script>

<?php 
// Si ce n'est pas une prévisualisation API, inclure le footer
if (!$isApi) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
}
?>

<?php
// Fonction pour obtenir le libellé de la catégorie en français
function getCategoryLabel($category) {
    switch ($category) {
        case 'running':
            return 'Course à pied';
        case 'hiking':
            return 'Randonnée';
        case 'cycling':
            return 'Vélo';
        default:
            return 'Autre';
    }
}
?>