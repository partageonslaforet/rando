<?php
/**
 * Gestion des événements par l'administrateur.
 */
// Activer l'affichage des erreurs
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log pour debug
error_log("=== DÉBUT EVENTS.PHP ===");

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug des variables de session
error_log("Session ID dans events: " . session_id());
error_log("Session data dans events: " . print_r($_SESSION, true));

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: /?showLogin=1&redirect=' . urlencode('/pages/admin/events.php'));
    exit();
}

error_log("User ID dans events: " . $_SESSION['user_id']);

// Inclure la configuration
require_once __DIR__ . '/../../includes/config.php';
error_log("Configuration chargée");

try {
    // Connexion à la base de données
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    error_log("DSN: " . $dsn);
    error_log("DB_USER: " . DB_USER);
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5, // Timeout de 5 secondes
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    error_log("Connexion à la base de données réussie");

    // Test de la connexion
    $pdo->query('SELECT 1');
    error_log("Test de connexion réussi");

    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    error_log("Requête préparée pour user_id: " . $_SESSION['user_id']);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    error_log("User data dans events: " . print_r($user, true));

    if (!$user) {
        error_log("ERREUR events: Utilisateur non trouvé dans la BD");
        session_destroy();
        header('Location: /');
        exit();
    }

    if ($user['role'] !== 'admin') {
        error_log("ERREUR events: L'utilisateur n'est pas admin (role = " . $user['role'] . ")");
        header('Location: /?error=admin_required');
        exit();
    }

    error_log("Utilisateur admin confirmé dans events");

    // Récupérer la structure de la table events
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM events")->fetchAll(PDO::FETCH_COLUMN);
        error_log("Colonnes de la table events: " . print_r($columns, true));
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des colonnes: " . $e->getMessage());
    }

    // Récupérer tous les événements avec une requête plus simple
    $stmt = $pdo->query('
        SELECT 
            e.id,
            e.title,
            e.description,
            e.date,
            e.start_time,
            e.end_time,
            e.location,
            e.venue,
            e.coordinates,
            e.difficulty,
            e.max_participants,
            e.organisation,
            e.status,
            e.created_at,
            e.updated_at,
            u.id as user_id,
            u.name as creator_name,
            c.code as category
        FROM events e
        LEFT JOIN users u ON e.user_id = u.id
        LEFT JOIN event_categories c ON e.category_id = c.id
        ORDER BY e.date DESC, e.start_time ASC
    ');
    $events = $stmt->fetchAll();
    error_log("Événements récupérés dans events: " . count($events));

} catch (PDOException $e) {
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();
    error_log("ERREUR BD dans events (code: $errorCode): " . $errorMessage);
    error_log("Trace: " . $e->getTraceAsString());
    
    // Gérer les erreurs spécifiques
    switch($errorCode) {
        case 2002: // Connection refused
            error_log("Erreur de connexion au serveur MySQL");
            break;
        case 1045: // Access denied
            error_log("Erreur d'authentification MySQL");
            break;
        case 1049: // Unknown database
            error_log("Base de données inconnue");
            break;
        case '42S22': // Column not found
            error_log("Colonne manquante dans la table");
            break;
        default:
            error_log("Erreur MySQL non gérée");
    }
    
    // Rediriger avec un message d'erreur plus spécifique
    header('Location: /?error=db&code=' . urlencode($errorCode));
    exit();
}

// Titre de la page
$pageTitle = "Administration - Gestion des événements";
error_log("Page title défini: " . $pageTitle);

// Inclure l'en-tête
error_log("=== CHARGEMENT DU HEADER DANS EVENTS ===");
include __DIR__ . '/../../templates/layouts/header.php';
error_log("Header chargé avec succès dans events");
?>

<!-- Ajouter Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<div class="container mt-5 pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestion des Événements</h1>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Date & Heure</th>
                    <th>Lieu</th>
                    <th>Catégorie</th>
                    <th>Organisation</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($event['title'] ?? 'Sans titre'); ?>
                            <?php if (!empty($event['description'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($event['description'], 0, 100)) . (strlen($event['description']) > 100 ? '...' : ''); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                                echo date('d/m/Y', strtotime($event['date']));
                                echo '<br><small class="text-muted">';
                                echo date('H:i', strtotime($event['start_time']));
                                if (!empty($event['end_time'])) {
                                    echo ' - ' . date('H:i', strtotime($event['end_time']));
                                }
                                echo '</small>';
                            ?>
                        </td>
                        <td>
                            <?php 
                                echo htmlspecialchars($event['location']);
                                if (!empty($event['venue'])) {
                                    echo '<br><small class="text-muted">' . htmlspecialchars($event['venue']) . '</small>';
                                }
                            ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $event['category'] === 'running' ? 'primary' : 
                                    ($event['category'] === 'hiking' ? 'success' : 'info'); 
                            ?>">
                                <?php echo ucfirst($event['category']); ?>
                            </span>
                            <?php if (!empty($event['difficulty'])): ?>
                                <br>
                                <small class="badge bg-<?php 
                                    echo $event['difficulty'] === 'easy' ? 'success' : 
                                        ($event['difficulty'] === 'medium' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($event['difficulty']); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($event['organisation'] ?? 'Non défini'); ?>
                            <?php if (!empty($event['max_participants'])): ?>
                                <br><small class="text-muted">Max: <?php echo $event['max_participants']; ?> participants</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $event['status'] === 'approved' ? 'success' : 
                                    ($event['status'] === 'rejected' ? 'danger' : 'warning'); 
                            ?>">
                                <?php 
                                    echo $event['status'] === 'approved' ? 'Approuvé' : 
                                        ($event['status'] === 'rejected' ? 'Rejeté' : 'En attente'); 
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-info btn-sm me-1" 
                                        onclick="viewEvent(<?php echo $event['id']; ?>)"
                                        title="Voir les détails">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-primary btn-sm me-1" 
                                        onclick="editEvent(<?php echo $event['id']; ?>)"
                                        title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($event['status'] !== 'approved'): ?>
                                    <button class="btn btn-success btn-sm me-1" 
                                            onclick="updateEventStatus(<?php echo $event['id']; ?>, 'approved')"
                                            title="Approuver">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($event['status'] !== 'rejected'): ?>
                                    <button class="btn btn-warning btn-sm me-1" 
                                            onclick="updateEventStatus(<?php echo $event['id']; ?>, 'rejected')"
                                            title="Rejeter">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deleteEvent(<?php echo $event['id']; ?>)"
                                        title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function viewEvent(eventId) {
    window.location.href = `/pages/admin/view_event.php?id=${eventId}`;
}

function editEvent(eventId) {
    window.location.href = `/pages/admin/edit_event.php?id=${eventId}`;
}

function updateEventStatus(eventId, status) {
    const action = status === 'approved' ? 'approuver' : 'rejeter';
    console.log(`Tentative de ${action} l'événement ${eventId}`);
    
    if (confirm(`Voulez-vous vraiment ${action} cet événement ?`)) {
        const url = `/api/admin/update_event_status.php?id=${eventId}`;
        const data = { status: status };
        
        console.log('Envoi requête à:', url);
        console.log('Données:', data);

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('Status de la réponse:', response.status);
            return response.text();
        })
        .then(rawResponse => {
            console.log('Réponse brute:', rawResponse);
            const response = JSON.parse(rawResponse);
            console.log('Réponse parsée:', response);

            if (response.logs) {
                console.group('📝 Logs du serveur:');
                response.logs.forEach(log => console.log(log));
                console.groupEnd();
            }

            if (response.success) {
                console.log('Mise à jour réussie, rechargement de la page');
                window.location.reload();
            } else {
                console.error('Erreur:', response.message);
                alert('Erreur: ' + response.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors de la communication avec le serveur');
        });
    }
}

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
                location.reload();
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

console.log('Page de gestion des événements chargée');
</script>

<?php
include __DIR__ . '/../../templates/layouts/footer.php';
?>
