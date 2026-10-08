<?php
/**
 * localisation: templates/events/event.php
 * Role: Template evenement : Event
 * Usage: Affichage d un evenement
 * Dépendances: includes/config.php, src/Models/Event.php
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../logs/error.log.php';
require_once __DIR__ . '/../../src/Models/Event.php';

// Récupérer l'ID de l'événement
$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (defined('DEBUG') && DEBUG) {
    logError('templates/events/event.php', 'template enter', [
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'event_id' => $eventId
    ]);
}
if (!$eventId) {
    header('Location: /templates/events/events.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
$eventManager = new Event($pdo);

try {
    error_log(" Récupération de l'événement #$eventId");
    $event = $eventManager->getById($eventId);
    
    if (!$event) {
        error_log(" Événement #$eventId non trouvé");
        throw new Exception('Événement non trouvé');
    }
    
    error_log(" Données de l'événement : " . json_encode($event));
    
    // Récupérer l'organisateur
    $organizer = $eventManager->getOrganizer($event['organizer_id']);
    error_log(" Données de l'organisateur : " . json_encode($organizer));
    
    // Récupérer les participants
    $participants = $eventManager->getParticipants($eventId);
    error_log(" Nombre de participants : " . count($participants));
    
    // Récupérer les commentaires
    $comments = $eventManager->getComments($eventId);
    error_log(" Nombre de commentaires : " . count($comments));
    
    // Vérifier si l'utilisateur actuel participe
    $isParticipating = false;
    if (isLoggedIn()) {
        $isParticipating = $eventManager->isUserParticipating($eventId, $_SESSION['user_id']);
        error_log(" Utilisateur participant : " . ($isParticipating ? "Oui" : "Non"));
    }

    // Vérifier les parcours
    if (!empty($event['routes'])) {
        error_log(" Parcours disponibles : " . count($event['routes']));
        foreach ($event['routes'] as $route) {
            error_log(" Parcours : " . json_encode($route));
        }
    } else {
        error_log(" Aucun parcours trouvé pour cet événement");
    }
    
} catch (Exception $e) {
    error_log(" Erreur lors de la récupération de l'événement : " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: /templates/events/events.php');
    exit;
}

require_once '../templates/layouts/header.php';
?>

<div class="container py-4">
    <!-- En-tête de l'événement -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-3"><?php echo htmlspecialchars($event['title']); ?></h1>
            <div class="d-flex align-items-center mb-3">
                <span class="badge bg-<?php echo $event['category'] === 'running' ? 'danger' : 
                    ($event['category'] === 'hiking' ? 'success' : 
                    ($event['category'] === 'cycling' ? 'info' : 'secondary')); ?> me-2">
                    <?php echo !empty($event['category_name']) ? htmlspecialchars($event['category_name']) : 'Non classé'; ?>
                </span>
                <span class="text-muted">
                    <i class="bi bi-calendar me-1"></i>
                    <?php echo date('d/m/Y', strtotime($event['date'])); ?>
                    à <?php echo date('H:i', strtotime($event['start_time'])); ?>
                </span>
            </div>
        </div>
        <div class="col-md-4 text-md-end">
            <?php if (isLoggedIn()): ?>
                <?php if ($event['organizer_id'] === $_SESSION['user_id']): ?>
                    <a href="/templates/events/edit-event.php?id=<?php echo $eventId; ?>" 
                       class="btn btn-outline-primary me-2">
                        <i class="bi bi-pencil"></i> Modifier
                    </a>
                <?php else: ?>
                    <form action="/api/events/participants.php" method="POST" class="d-inline-block">
                        <input type="hidden" name="action" 
                               value="<?php echo $isParticipating ? 'leave' : 'join'; ?>">
                        <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                        <button type="submit" class="btn btn-<?php echo $isParticipating ? 
                            'outline-danger' : 'primary'; ?>">
                            <?php if ($isParticipating): ?>
                                <i class="bi bi-x-circle"></i> Se désinscrire
                            <?php else: ?>
                                <i class="bi bi-check-circle"></i> Participer
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">
                    Se connecter pour participer
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Détails de l'événement -->
        <div class="col-md-8">
            <!-- Carousel d'images -->
            <?php if (!empty($event['images'])): ?>
                <div id="eventCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php foreach ($event['images'] as $index => $image): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($image['url']); ?>" 
                                     class="d-block w-100" alt="Image de l'événement">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($event['images']) > 1): ?>
                        <button class="carousel-control-prev" type="button" 
                                data-bs-target="#eventCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Précédent</span>
                        </button>
                        <button class="carousel-control-next" type="button" 
                                data-bs-target="#eventCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Suivant</span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Description -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Description</h5>
                    <p class="card-text"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                </div>
            </div>

            <!-- Parcours GPX -->
            <?php if (!empty($event['gpx_file'])): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Parcours</h5>
                        <div id="map" class="map-h-400"></div>
                        <div class="mt-3">
                            <a href="<?php echo htmlspecialchars($event['gpx_file']); ?>" 
                               class="btn btn-outline-primary btn-sm" download>
                                <i class="bi bi-download"></i> Télécharger le fichier GPX
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Commentaires -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Commentaires</h5>
                    
                    <?php if (isLoggedIn()): ?>
                        <form id="commentForm" class="mb-4">
                            <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                            <div class="mb-3">
                                <textarea class="form-control" name="content" rows="3" 
                                          placeholder="Votre commentaire..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Commenter</button>
                        </form>
                    <?php endif; ?>

                    <div id="commentsList">
                        <?php if (empty($comments)): ?>
                            <p class="text-muted">Aucun commentaire pour le moment.</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <img src="<?php echo !empty($comment['user_avatar']) ? 
                                            htmlspecialchars($comment['user_avatar']) : 
                                            '/assets/images/default-avatar.png'; ?>" 
                                             class="rounded-circle" width="40" height="40" 
                                             alt="Avatar de <?php echo htmlspecialchars($comment['user_name']); ?>">
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($comment['user_name']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Informations pratiques -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Informations pratiques</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-geo-alt text-primary"></i>
                            <?php echo htmlspecialchars($event['location']); ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-clock text-primary"></i>
                            <?php echo date('H:i', strtotime($event['start_time'])); ?>
                            <?php if (!empty($event['end_time'])): ?>
                                - <?php echo date('H:i', strtotime($event['end_time'])); ?>
                            <?php endif; ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-speedometer2 text-primary"></i>
                            Distance : <?php echo $event['distance']; ?> km
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-graph-up text-primary"></i>
                            Dénivelé : <?php echo $event['elevation']; ?> m
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-star text-primary"></i>
                            Difficulté : 
                            <span class="badge bg-<?php echo $event['difficulty'] === 'easy' ? 'success' : 
                                ($event['difficulty'] === 'medium' ? 'warning' : 'danger'); ?>">
                                <?php echo $event['difficulty'] === 'easy' ? 'Facile' : 
                                    ($event['difficulty'] === 'medium' ? 'Moyen' : 'Difficile'); ?>
                            </span>
                        </li>
                        <?php if ($event['price'] > 0): ?>
                            <li class="mb-2">
                                <i class="bi bi-tag text-primary"></i>
                                Prix : <?php echo number_format($event['price'], 2); ?> €
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Organisateur -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Organisateur</h5>
                    <div class="d-flex align-items-center">
                        <img src="<?php echo !empty($organizer['avatar_url']) ? 
                            htmlspecialchars($organizer['avatar_url']) : 
                            '/assets/images/default-avatar.png'; ?>" 
                             class="rounded-circle me-3" width="50" height="50" 
                             alt="Avatar de <?php echo htmlspecialchars($organizer['name']); ?>">
                        <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($organizer['name']); ?></h6>
                            <small class="text-muted">
                                Membre depuis <?php echo date('m/Y', strtotime($organizer['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Participants -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Participants</h5>
                    <p class="text-muted">
                        <?php echo count($participants); ?> 
                        <?php if (!empty($event['max_participants'])): ?>
                            / <?php echo $event['max_participants']; ?>
                        <?php endif; ?>
                        participant<?php echo count($participants) > 1 ? 's' : ''; ?>
                    </p>
                    
                    <?php if (!empty($participants)): ?>
                        <div class="participants-list">
                            <?php foreach ($participants as $participant): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <img src="<?php echo !empty($participant['avatar_url']) ? 
                                        htmlspecialchars($participant['avatar_url']) : 
                                        '/assets/images/default-avatar.png'; ?>" 
                                         class="rounded-circle me-2" width="30" height="30" 
                                         alt="Avatar de <?php echo htmlspecialchars($participant['name']); ?>">
                                    <span><?php echo htmlspecialchars($participant['name']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Aucun participant pour le moment.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($event['gpx_file'])): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser la carte
    const map = L.map('map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: ' OpenStreetMap contributors'
    }).addTo(map);

    // Charger le fichier GPX
    new L.GPX("<?php echo htmlspecialchars($event['gpx_file']); ?>", {
        async: true,
        marker_options: {
            startIconUrl: '/assets/images/pin-start.png',
            endIconUrl: '/assets/images/pin-end.png',
            shadowUrl: '/assets/images/pin-shadow.png'
        }
    }).on('loaded', function(e) {
        map.fitBounds(e.target.getBounds());
    }).addTo(map);
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du formulaire de commentaire
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            try {
                const response = await fetch('/api/events/comments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        event_id: this.querySelector('[name="event_id"]').value,
                        content: this.querySelector('[name="content"]').value
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    // Recharger la page pour afficher le nouveau commentaire
                    location.reload();
                } else {
                    alert(data.message || 'Erreur lors de l\'ajout du commentaire');
                }
            } catch (error) {
                alert('Erreur de connexion au serveur');
            }
        });
    }
});
</script>

<?php require_once '../templates/layouts/footer.php'; ?>
