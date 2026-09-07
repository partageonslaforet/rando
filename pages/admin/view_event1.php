<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /pages/admin/events.php');
    exit();
}

$eventId = (int)$_GET['id'];

// Inclure la configuration
require_once __DIR__ . '/../../includes/config.php';

try {
    // Connexion à la base de données
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        header('Location: /pages/user/profile.php');
        exit();
    }

    // Récupérer les détails de l'événement
    $stmt = $pdo->prepare('
        SELECT 
            e.*,
            u.name as creator_name,
            u.email as creator_email
        FROM events e
        LEFT JOIN users u ON e.user_id = u.id
        WHERE e.id = ?
    ');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        header('Location: /pages/admin/events.php?error=event_not_found');
        exit();
    }

    // Traitement de la mise à jour des chemins
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_paths'])) {
        try {
            // 1. Mise à jour des images d'événements pour cet événement
            $stmt = $pdo->prepare("
                SELECT id, image_path, storage_path 
                FROM event_images 
                WHERE event_id = ? AND storage_path IS NULL
            ");
            $stmt->execute([$eventId]);
            $images = $stmt->fetchAll();
            
            if (count($images) > 0) {
                $updateStmt = $pdo->prepare("
                    UPDATE event_images 
                    SET storage_path = :storage_path,
                        image_path = :image_path
                    WHERE id = :id
                ");
                
                foreach ($images as $image) {
                    $filename = basename($image['image_path']);
                    $newStoragePath = '/uploads/events/' . $filename;
                    $newImagePath = 'https://rando.partageonslaforet.be/uploads/events/' . $filename;
                    
                    $updateStmt->execute([
                        'id' => $image['id'],
                        'storage_path' => $newStoragePath,
                        'image_path' => $newImagePath
                    ]);
                }
                
                $updateMessage = count($images) . " image(s) mise(s) à jour avec succès.";
                $updateSuccess = true;
            } else {
                $updateMessage = "Aucune image à mettre à jour pour cet événement.";
                $updateSuccess = true;
            }
        } catch (Exception $e) {
            $updateMessage = "Erreur lors de la mise à jour : " . $e->getMessage();
            $updateSuccess = false;
        }
    }

    // Titre de la page
    $pageTitle = "Administration - " . htmlspecialchars($event['title']);

} catch (PDOException $e) {
    error_log("Erreur BD: " . $e->getMessage());
    header('Location: /pages/admin/events.php?error=db');
    exit();
}

// Inclure l'en-tête
include __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-5 pt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/pages/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/pages/admin/events.php">Gestion des événements</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($event['title']); ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-8">
            <h1 class="mb-4"><?php echo htmlspecialchars($event['title']); ?></h1>
            
            <?php if (!empty($event['description'])): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Description</h5>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Informations</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($event['date'])); ?></p>
                            <p>
                                <strong>Horaire:</strong> 
                                <?php 
                                    echo date('H:i', strtotime($event['start_time']));
                                    if (!empty($event['end_time'])) {
                                        echo ' - ' . date('H:i', strtotime($event['end_time']));
                                    }
                                ?>
                            </p>
                            <p><strong>Lieu:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
                            <?php if (!empty($event['venue'])): ?>
                                <p><strong>Lieu précis:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <strong>Catégorie:</strong>
                                <span class="badge bg-<?php 
                                    echo $event['category'] === 'running' ? 'primary' : 
                                        ($event['category'] === 'hiking' ? 'success' : 'info'); 
                                ?>">
                                    <?php echo ucfirst($event['category']); ?>
                                </span>
                            </p>
                            <?php if (!empty($event['difficulty'])): ?>
                                <p>
                                    <strong>Difficulté:</strong>
                                    <span class="badge bg-<?php 
                                        echo $event['difficulty'] === 'easy' ? 'success' : 
                                            ($event['difficulty'] === 'medium' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($event['difficulty']); ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($event['max_participants'])): ?>
                                <p><strong>Participants maximum:</strong> <?php echo $event['max_participants']; ?></p>
                            <?php endif; ?>
                            <p><strong>Organisation:</strong> <?php echo htmlspecialchars($event['organisation']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($event['coordinates'])): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Carte</h5>
                        <div id="eventMap" style="height: 400px;"></div>
                    </div>
                </div>

                <script>
                    // Attendre que Leaflet soit chargé
                    window.addEventListener('load', function() {
                        // S'assurer que la div existe
                        const mapDiv = document.getElementById('eventMap');
                        if (!mapDiv) return;

                        // S'assurer que L (Leaflet) est disponible
                        if (typeof L === 'undefined') {
                            console.error('Leaflet n\'est pas chargé');
                            return;
                        }

                        try {
                            const coordinates = '<?php echo $event['coordinates']; ?>'.split(',');
                            const lat = parseFloat(coordinates[0]);
                            const lng = parseFloat(coordinates[1]);
                            
                            if (!isNaN(lat) && !isNaN(lng)) {
                                // Vérifier si une carte existe déjà
                                if (window.eventMap) {
                                    window.eventMap.remove();
                                }

                                // Créer la nouvelle carte
                                window.eventMap = L.map('eventMap').setView([lat, lng], 13);
                                
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    attribution: '© OpenStreetMap contributors'
                                }).addTo(window.eventMap);

                                L.marker([lat, lng]).addTo(window.eventMap)
                                    .bindPopup('<?php echo htmlspecialchars($event['title']); ?>');
                            }
                        } catch (error) {
                            console.error('Erreur lors de l\'initialisation de la carte:', error);
                        }
                    });
                </script>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Actions administrateur</h5>
                    
                    <?php if (isset($updateMessage)): ?>
                        <div class="alert alert-<?php echo $updateSuccess ? 'success' : 'danger'; ?> mb-3">
                            <?php echo $updateMessage; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" class="mb-3">
                        <button type="submit" name="update_paths" class="btn btn-warning btn-sm w-100 mb-2">
                            Mettre à jour les chemins d'images
                        </button>
                    </form>
                    
                    <button class="btn btn-danger btn-sm w-100" 
                            onclick="deleteEvent(<?php echo $eventId; ?>)"
                            title="Supprimer l'événement">
                        Supprimer l'événement
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Informations complémentaires</h5>
                    <p class="mb-1"><strong>Créé par:</strong> <?php echo htmlspecialchars($event['creator_name'] ?? 'Anonyme'); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($event['creator_email'] ?? 'Non renseigné'); ?></p>
                    <p class="mb-1"><strong>Créé le:</strong> <?php echo date('d/m/Y H:i', strtotime($event['created_at'])); ?></p>
                    <?php if (!empty($event['updated_at'])): ?>
                        <p class="mb-1"><strong>Dernière modification:</strong> <?php echo date('d/m/Y H:i', strtotime($event['updated_at'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteEvent(eventId) {
    if (confirm('Voulez-vous vraiment supprimer cet événement ? Cette action est irréversible.')) {
        fetch(`/api/events/${eventId}/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/pages/admin/events.php';
            } else {
                alert('Erreur lors de la suppression de l\'événement');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue');
        });
    }
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
