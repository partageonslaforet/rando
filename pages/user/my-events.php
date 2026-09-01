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

    // Initialisation des listes (sécurise l'affichage en cas d'erreur)
    $events = [];
    $totalEvents = 0;
    $approvedEvents = [];
    $pendingEvents = [];
    $draftEvents = [];
    $rejectedEvents = [];
    $currentTab = 'all';
    $filteredEvents = [];

    // Récupérer les événements publiés
    $stmt = $db->prepare('
        SELECT * FROM events 
        WHERE user_id = ? 
        ORDER BY date DESC, start_time DESC
    ');
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les brouillons
    $stmt2 = $db->prepare('
        SELECT * FROM draft_events 
        WHERE user_id = ? 
        ORDER BY date DESC, updated_at DESC
    ');
    $stmt2->execute([$user['id']]);
    $drafts = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($drafts as &$draft) {
        $draft['status'] = 'draft';
    }
    unset($draft);

    $events = array_merge($events, $drafts);

    // Récupérer les statistiques
    $totalEvents = count($events);
    $approvedEvents = array_filter($events, function($event) {
        return $event['status'] === 'approved';
    });
    $pendingEvents = array_filter($events, function($event) {
        return $event['status'] === 'pending';
    });
    $draftEvents = array_filter($events, function($event) {
        return $event['status'] === 'draft';
    });
    $rejectedEvents = array_filter($events, function($event) {
        return $event['status'] === 'rejected';
    });

    // Filtrer selon l'onglet actif
    $currentTab = $_GET['status'] ?? 'all';
    $allowedTabs = ['all', 'draft', 'pending', 'approved', 'rejected'];
    if (!in_array($currentTab, $allowedTabs, true)) {
        $currentTab = 'all';
    }
    $filteredEvents = $events;
    if ($currentTab !== 'all') {
        $filteredEvents = array_filter($events, function($event) use ($currentTab) {
            return $event['status'] === $currentTab;
        });
    }

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
        case 'draft':
            return '<span class="badge bg-secondary">Brouillon</span>';
        default:
            return '<span class="badge bg-warning text-dark">En attente</span>';
    }
}

// Fonction pour formater la date
function formatEventDate($date) {
    if (empty($date)) {
        return '—';
    }
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
    return empty($time) ? '—' : substr($time, 0, 5);
}

// Inclure l'en-tête
$pageTitle = "Mes Événements";
require_once __DIR__ . '/../../includes/header-solid.php';
?>

<link rel="stylesheet" href="/assets/css/my-events.css">

<main class="my-events-container">
    <nav class="my-events-breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="/pages/user/profile.php">Mon compte</a></li>
            <li><span aria-current="page">Mes événements</span></li>
        </ol>
    </nav>

    <section class="my-events-hero" aria-labelledby="events-hero-title">
        <div class="my-events-hero-text">
            <h1 id="events-hero-title">Mes événements</h1>
            <p>Créez, suivez et gérez les événements que vous proposez.</p>
        </div>
        <a href="/?create=1" class="btn btn-create">
            <i class="bi bi-plus" aria-hidden="true"></i>
            Créer un événement
        </a>
    </section>

    <section class="my-events-card" aria-labelledby="events-section-title">
        <div class="my-events-card-header">
            <h2 id="events-section-title">Mes événements</h2>
            <div class="my-events-counts">
                <span><?= count($approvedEvents) ?> publiés</span>
                <span class="dot" aria-hidden="true"></span>
                <span><?= count($pendingEvents) ?> en validation</span>
            </div>
        </div>

        <nav class="my-events-tabs" aria-label="Filtrer les événements">
            <?php
            $tabs = [
                'all'   => ['Tous', $totalEvents],
                'draft' => ['Brouillons', count($draftEvents)],
                'pending'  => ['En validation', count($pendingEvents)],
                'approved' => ['Publiés', count($approvedEvents)],
                'rejected' => ['Refusés', count($rejectedEvents)],
            ];
            foreach ($tabs as $status => $tab):
                $isActive = $currentTab === $status;
            ?>
                <a class="my-events-tab <?= $isActive ? 'active' : '' ?>"
                   href="?status=<?= $status ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <?= $tab[0] ?> (<?= $tab[1] ?>)
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="my-events-content">
            <?php if (empty($filteredEvents)): ?>
                <div class="my-events-empty">
                    <span class="my-events-empty-icon" aria-hidden="true">+</span>
                    <h3>Créez votre premier événement</h3>
                    <p>Proposez une randonnée, un parcours VTT ou une activité nature. Vous pourrez l’enregistrer en brouillon avant de le soumettre.</p>
                    <a href="/?create=1" class="btn btn-create">
                        <i class="bi bi-plus" aria-hidden="true"></i>
                        Créer un événement
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
                                <?php foreach ($filteredEvents as $event): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($event['title']) ?></strong>
                                        </td>
                                        <td><?= formatEventDate($event['date']) ?></td>
                                        <td><?= formatTime($event['start_time'] ?? $event['registration_opens'] ?? null) ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= htmlspecialchars(ucfirst($event['category'] ?? '')) ?>
                                            </span>
                                        </td>
                                        <td><?= getStatusBadge($event['status']) ?></td>
                                        <td>
                                            <?php if ($event['status'] === 'draft'): ?>
                                                <span class="text-muted">Brouillon</span>
                                            <?php else: ?>
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
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
