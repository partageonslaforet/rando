<?php
// Inclure les fonctions
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash_messages.php';

// Démarrer la session et vérifier la connexion
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireLogin();

try {
    // Connexion à la base de données
    require_once __DIR__ . '/../../config/database.php';
    $db = getConnection();

    // Récupérer les informations de l'utilisateur
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([getCurrentUserId()]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: /?login=required');
        exit;
    }

    // Vérifier le rôle et rediriger si nécessaire
    if ($user['role'] === 'admin' && strpos($_SERVER['REQUEST_URI'], '/pages/user/') !== false) {
        header('Location: /pages/admin/my-events.php');
        exit();
    } elseif ($user['role'] === 'user' && strpos($_SERVER['REQUEST_URI'], '/pages/admin/') !== false) {
        header('Location: /pages/user/my-events.php');
        exit();
    }

    // Récupérer les événements de l'utilisateur
    $stmt = $db->prepare('
        SELECT * FROM events 
        WHERE user_id = ? 
        ORDER BY date DESC, start_time DESC
    ');
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les statistiques
    $totalEvents = count($events);
    $approvedEvents = array_filter($events, function($event) {
        return $event['status'] === 'approved';
    });
    $pendingEvents = array_filter($events, function($event) {
        return $event['status'] === 'pending';
    });

} catch (Exception $e) {
    error_log('Erreur my-events.php: ' . $e->getMessage());
    addFlashMessage('danger', "Une erreur est survenue lors de la récupération de vos événements.");
}

// Fonction pour obtenir le badge selon le statut
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return '<span class="badge bg-success">Approuvé</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejeté</span>';
        default:
            return '<span class="badge bg-warning text-dark">En attente</span>';
    }
}

// Fonction pour formater la date
function formatEventDate($date) {
    $formatter = new IntlDateFormatter(
        'fr_FR',
        IntlDateFormatter::LONG,
        IntlDateFormatter::NONE,
        null,
        null,
        'dd MMMM yyyy'
    );
    return $formatter->format(strtotime($date));
}

// Fonction pour formater l'heure
function formatTime($time) {
    return substr($time, 0, 5);
}

// Inclure l'en-tête
$pageTitle = "Mes Événements";
require_once __DIR__ . '/../../includes/header-solid.php';
?>

<link rel="stylesheet" href="/assets/css/my-events.css">

<div class="events-container mt-5 pt-5">
    <!-- En-tête -->
    <div class="events-header text-center">
        <div class="container">
            <h1>Mes Événements</h1>
            <p>Gérez vos événements et suivez leur statut</p>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="events-stats">
        <div class="stat-card">
            <h3><?= count($events) ?></h3>
            <p>Total des événements</p>
        </div>
        <div class="stat-card">
            <h3><?= count($approvedEvents) ?></h3>
            <p>Événements approuvés</p>
        </div>
        <div class="stat-card">
            <h3><?= count($pendingEvents) ?></h3>
            <p>En attente d'approbation</p>
        </div>
    </div>

    <?php if (empty($events)): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-plus"></i>
            <h3>Aucun événement créé</h3>
            <p>Commencez à organiser des événements dès maintenant !</p>
            <a href="/templates/events/create-event.php" class="btn btn-primary create-event-btn">
                <i class="bi bi-plus-circle"></i> Créer mon premier événement
            </a>
        </div>
    <?php else: ?>
        <div class="events-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Catégorie</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($event['title']) ?></strong>
                                </td>
                                <td><?= formatEventDate($event['date']) ?></td>
                                <td><?= formatTime($event['start_time']) ?></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?= ucfirst($event['category']) ?>
                                    </span>
                                </td>
                                <td><?= getStatusBadge($event['status']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/templates/events/event-detail.php?id=<?= $event['id'] ?>" 
                                            onclick="console.log('Clicking view button for event ID: <?= $event['id'] ?>')"
                                            class="btn btn-sm btn-outline-primary btn-action">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                        <?php if ($event['status'] === 'approved'): ?>
                                            <a href="/templates/events/edit-event.php?id=<?= $event['id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary btn-action">
                                                <i class="bi bi-pencil"></i> Modifier
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="/templates/events/create-event.php" class="btn btn-primary create-event-btn">
                <i class="bi bi-plus-circle"></i> Créer un nouvel événement
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
