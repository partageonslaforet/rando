<?php
/**
 * Liste des événements de l'utilisateur connecté.
 */
// Inclure les fonctions
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash_messages.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../logs/error.log.php';

// Démarrer la session et vérifier la connexion
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireLogin();

// Initialisation des listes (sécurise l'affichage en cas d'erreur)
$events = [];
$totalEvents = 0;
$approvedEvents = [];
$pendingEvents = [];
$draftEvents = [];
$rejectedEvents = [];
$expiredEvents = [];
$currentTab = 'all';
$filteredEvents = [];

try {
    // Connexion à la base de données
    require_once __DIR__ . '/../../config/database.php';
    $db = getConnection();

    // Suppression d'un brouillon
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_draft_id'])) {
        if (verifyCsrf($_POST['csrf_token'] ?? '')) {
            $stmt = $db->prepare('DELETE FROM draft_events WHERE id = ? AND user_id = ?');
            $stmt->execute([(int)$_POST['delete_draft_id'], $_SESSION['user_id']]);
            addFlashMessage('success', 'Brouillon supprimé avec succès.');
        } else {
            addFlashMessage('danger', 'Token de sécurité invalide.');
        }
        header('Location: /pages/user/my-events.php' . (isset($_GET['status']) ? '?status=' . $_GET['status'] : ''));
        exit;
    }

    // Annulation d'un événement publié (propriétaire)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_event_id'])) {
        if (verifyCsrf($_POST['csrf_token'] ?? '')) {
            $reason = trim($_POST['cancellation_reason'] ?? '');
            $eventId = (int)$_POST['cancel_event_id'];
            $userId = (int)$_SESSION['user_id'];

            try {
                $stmt = $db->prepare("UPDATE events SET is_cancelled = 1, cancelled_at = NOW(), cancellation_reason = ? WHERE id = ? AND user_id = ? AND status IN ('approved', 'expired') AND (is_cancelled = 0 OR is_cancelled IS NULL)");
                $stmt->execute([$reason, $eventId, $userId]);

                $rowCount = $stmt->rowCount();
                if ($rowCount > 0) {
                    $check = $db->prepare('SELECT id, status, is_cancelled, cancellation_reason FROM events WHERE id = ?');
                    $check->execute([$eventId]);
                    $eventState = $check->fetch(PDO::FETCH_ASSOC);
                    logError('pages/user/my-events.php', 'Annulation réussie', [
                        'event_id' => $eventId,
                        'rowCount' => $rowCount,
                        'event_state' => $eventState
                    ]);

                    // Notifier les abonnés de l'annulation (type 'cancelled'
                    // détecté via is_cancelled). Ne bloque jamais la redirection.
                    try {
                        require_once __DIR__ . '/../../src/Services/Subscribers.php';
                        $subscribers = new Subscribers();
                        $notifResult = $subscribers->notifyEvent($eventId);
                        logError('pages/user/my-events.php', 'Notifications abonnés (annulation)', [
                            'event_id' => $eventId,
                            'type' => $notifResult['type'] ?? null,
                            'sent' => $notifResult['sent'] ?? 0,
                            'skipped' => $notifResult['skipped'] ?? 0,
                        ]);
                    } catch (Throwable $e) {
                        logError('pages/user/my-events.php', 'Erreur notifications abonnés (annulation)', [
                            'event_id' => $eventId,
                            'exception' => $e->getMessage()
                        ]);
                    }

                    addFlashMessage('success', 'L\'événement a été annulé.');
                } else {
                    $check = $db->prepare('SELECT id, user_id, status, is_cancelled FROM events WHERE id = ?');
                    $check->execute([$eventId]);
                    $eventState = $check->fetch(PDO::FETCH_ASSOC) ?: ['not_found' => true];
                    logError('pages/user/my-events.php', 'Annulation impossible', [
                        'event_id' => $eventId,
                        'user_id' => $userId,
                        'event_state' => $eventState,
                        'reason' => $reason
                    ]);
                    addFlashMessage('danger', 'Impossible d\'annuler cet événement.');
                }
            } catch (Throwable $e) {
                logError('pages/user/my-events.php', 'Erreur annulation', [
                    'event_id' => $eventId,
                    'user_id' => $userId,
                    'exception' => $e->getMessage()
                ]);
                addFlashMessage('danger', 'Impossible d\'annuler cet événement.');
            }
        } else {
            addFlashMessage('danger', 'Token de sécurité invalide.');
        }
        header('Location: /pages/user/my-events.php' . (isset($_GET['status']) ? '?status=' . $_GET['status'] : ''));
        exit;
    }

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
        header('Location: /pages/admin');
        exit();
    } elseif ($user['role'] === 'user' && strpos($_SERVER['REQUEST_URI'], '/pages/admin/') !== false) {
        header('Location: /pages/user/my-events.php');
        exit();
    }

    // Récupérer les événements publiés
    $stmt = $db->prepare('
        SELECT * FROM events 
        WHERE user_id = ? 
        ORDER BY date DESC, start_time DESC
    ');
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les brouillons (non encore publies)
    $stmt2 = $db->prepare('
        SELECT d.* FROM draft_events d
        WHERE d.user_id = ? 
          AND (d.status IS NULL OR d.status != \'published\')
          AND NOT (
              d.original_event_id IS NULL
              AND EXISTS (
                  SELECT 1 FROM events e
                  WHERE e.user_id = d.user_id
                    AND e.title = d.title
                    AND e.date = d.date
              )
          )
        ORDER BY d.updated_at DESC
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
        return ($event['status'] ?? '') === 'approved';
    });
    $pendingEvents = array_filter($events, function($event) {
        return ($event['status'] ?? '') === 'pending';
    });
    $draftEvents = array_filter($events, function($event) {
        return ($event['status'] ?? '') === 'draft';
    });
    $rejectedEvents = array_filter($events, function($event) {
        return ($event['status'] ?? '') === 'rejected';
    });

    // Échus: statut 'expired' OU approuvés dont la date est passée
    $today = new DateTime('today');
    $expiredEvents = array_filter($events, function($event) use ($today) {
        $status = $event['status'] ?? '';
        if ($status === 'expired') return true;
        if ($status === 'approved' && !empty($event['date'])) {
            try { return (new DateTime($event['date'])) < $today; } catch (Exception $e) { return false; }
        }
        return false;
    });

    // Filtrer selon l'onglet actif
    $currentTab = $_GET['status'] ?? 'all';
    $allowedTabs = ['all', 'draft', 'pending', 'approved', 'rejected', 'expired'];
    if (!in_array($currentTab, $allowedTabs, true)) {
        $currentTab = 'all';
    }
    $filteredEvents = $events;
    if ($currentTab !== 'all') {
        if ($currentTab === 'expired') {
            $todayF = new DateTime('today');
            $filteredEvents = array_filter($events, function($event) use ($todayF) {
                $status = $event['status'] ?? '';
                if ($status === 'expired') return true;
                if ($status === 'approved' && !empty($event['date'])) {
                    try { return (new DateTime($event['date'])) < $todayF; } catch (Exception $e) { return false; }
                }
                return false;
            });
        } else {
            $filteredEvents = array_filter($events, function($event) use ($currentTab) {
                return ($event['status'] ?? '') === $currentTab;
            });
        }
    }

} catch (Exception $e) {
    error_log('Erreur my-events.php: ' . $e->getMessage());
    addFlashMessage('danger', "Une erreur est survenue lors de la récupération de vos événements.");
}

// Fonction pour obtenir le badge selon le statut (+ affichage "Échu" J+1 pour approuvé)
function getStatusBadge(?string $status, bool $isCancelled = false, ?string $eventDate = null): string {
    if ($isCancelled) {
        return '<span class="badge bg-cancelled">Annulé</span>';
    }
    // Si approuvé mais date passée (J+1), afficher Échu
    if ($status === 'approved' && !empty($eventDate)) {
        try {
            $event = new DateTime($eventDate);
            $today = new DateTime('today');
            if ($event < $today) {
                return '<span class="badge bg-expired">Échu</span>';
            }
        } catch (Exception $e) {
            // ignore parsing errors, fallback to normal approved
        }
    }
    switch ($status) {
        case 'expired':
            return '<span class="badge bg-expired">Échu</span>';
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
function formatEventDate(?string $date): string {
    if (empty($date)) {
        return '—';
    }
    $timestamp = strtotime($date);
    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            null,
            null,
            'dd MMMM yyyy'
        );
        return $formatter->format($timestamp);
    }
    $months = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'
    ];
    return date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

// Fonction pour formater l'heure
function formatTime(?string $time): string {
    return empty($time) ? '—' : substr($time, 0, 5);
}

// Inclure l'en-tête
$pageTitle = "Mes Événements";
require_once __DIR__ . '/../../templates/layouts/header-solid.php';
?>

<link rel="stylesheet" href="/assets/css/pages/user/my-events.css">

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
                'all'      => ['Tous', $totalEvents],
                'draft'    => ['Brouillons', count($draftEvents)],
                'pending'  => ['En validation', count($pendingEvents)],
                'approved' => ['Publiés', count($approvedEvents)],
                'rejected' => ['Refusés', count($rejectedEvents)],
                'expired'  => ['Échus', count($expiredEvents)],
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
                        <table class="table table-modern align-middle" id="my-events-table">
                            <thead>
                                <tr>
                                    <th><button type="button" class="sort-toggle" data-sort="title" aria-label="Trier par titre">Titre <span class="arrows"><span class="caret up">▲</span><span class="caret down">▼</span></span></button></th>
                                    <th><button type="button" class="sort-toggle" data-sort="date" aria-label="Trier par date">Date <span class="arrows"><span class="caret up">▲</span><span class="caret down">▼</span></span></button></th>
                                    <th><button type="button" class="sort-toggle" data-sort="status" aria-label="Trier par statut">Statut <span class="arrows"><span class="caret up">▲</span><span class="caret down">▼</span></span></button></th>
                                    <th><button type="button" class="sort-toggle" data-sort="recorded" aria-label="Trier par enregistrement">Enregistré le <span class="arrows"><span class="caret up">▲</span><span class="caret down">▼</span></span></button></th>
                                    <th class="text-start ps-1">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="myev-compact">
                                <?php foreach ($filteredEvents as $event): ?>
                                    <?php
                                        // Préparer timestamp d'enregistrement (created_at sinon updated_at)
                                        $ts = $event['created_at'] ?? $event['updated_at'] ?? null;
                                        $tsOut = '';
                                        $tsEpoch = '';
                                        if ($ts) {
                                            try { $dt = new DateTime($ts); $tsOut = $dt->format('d/m/Y H:i'); $tsEpoch = $dt->getTimestamp(); } catch (Exception $e) { $tsOut = ''; $tsEpoch = ''; }
                                        }
                                        // Titre pour tri
                                        $titleText = trim((string)($event['title'] ?? ''));
                                        // Date de l'événement pour tri (draft -> created_at/date, sinon date)
                                        $eventDateStr = ($event['status'] === 'draft') ? ($event['created_at'] ?? $event['date'] ?? null) : ($event['date'] ?? null);
                                        $eventDateEpoch = '';
                                        if ($eventDateStr) { $eventDateEpoch = strtotime($eventDateStr) ?: ''; }
                                        $statusText = (string)($event['status'] ?? '');
                                    ?>
                                    <tr class="<?= filter_var($event['is_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'event-cancelled' : '' ?>" data-title="<?= htmlspecialchars($titleText) ?>" data-date-ts="<?= htmlspecialchars($eventDateEpoch) ?>" data-status="<?= htmlspecialchars($statusText) ?>" data-recorded-ts="<?= htmlspecialchars($tsEpoch) ?>">
                                        <td class="cell-title">
                                            <?php if ($event['status'] === 'draft'): ?>
                                                <a href="/?create=1&draft_id=<?= (int)$event['id'] ?>" class="draft-title">
                                                    <?= htmlspecialchars($event['title'] ?? '') ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="title-text"><?= htmlspecialchars($event['title'] ?? '') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cell-date">
                                            <?php if ($event['status'] === 'draft'): ?>
                                                <?= formatEventDate($event['created_at'] ?? $event['date']) ?>
                                            <?php else: ?>
                                                <?= formatEventDate($event['date']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cell-status"><?= getStatusBadge($event['status'], filter_var($event['is_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN), $event['date'] ?? null) ?></td>
                                        <td class="cell-recorded"><span class="myev-recorded"><?= htmlspecialchars($tsOut) ?></span></td>
                                        <td class="text-start ps-1">
                                            <?php if ($event['status'] === 'draft'): ?>
                                                <button type="button" class="btn btn-sm btn-action btn-delete" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-draft-id="<?= (int)$event['id'] ?>" title="Supprimer">
                                                    <i class="bi bi-trash"></i><span>Supprimer</span>
                                                </button>
                                            <?php else: ?>
                                                <div class="btn-actions">
                                                    <a href="/event/<?= $event['id'] ?>"
                                                       class="btn btn-sm btn-action btn-view" title="Voir">
                                                        <i class="bi bi-eye"></i><span>Voir</span>
                                                    </a>
                                                    <?php if ($event['status'] === 'approved'): ?>
                                                        <button type="button"
                                                                class="btn btn-sm btn-action btn-edit btn-edit-published"
                                                                data-event-id="<?= (int)$event['id'] ?>" title="Modifier">
                                                            <i class="bi bi-pencil"></i><span>Modifier</span>
                                                        </button>
                                                        <?php if (!filter_var($event['is_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN)): ?>
                                                            <button type="button"
                                                                    class="btn btn-sm btn-action btn-cancel"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#cancelEventModal"
                                                                    data-cancel-id="<?= (int)$event['id'] ?>"
                                                                    data-cancel-title="<?= htmlspecialchars($event['title'] ?? '') ?>"
                                                                    title="Annuler l'événement">
                                                                <i class="bi bi-x-circle"></i><span>Annuler</span>
                                                            </button>
                                                        <?php endif; ?>
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

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">Supprimer le brouillon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p>Es-tu sûr de vouloir supprimer ce brouillon ? Cette action est irréversible.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="POST" action="/pages/user/my-events.php<?= $currentTab !== 'all' ? '?status=' . $currentTab : '' ?>" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" id="deleteDraftId" name="delete_draft_id" value="">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'annulation d'événement -->
<div class="modal fade" id="cancelEventModal" tabindex="-1" aria-labelledby="cancelEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/pages/user/my-events.php<?= $currentTab !== 'all' ? '?status=' . $currentTab : '' ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelEventModalLabel">Annuler l'événement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>Es-tu sûr de vouloir annuler <strong id="cancelEventTitle"></strong> ?</p>
                    <div class="mb-3">
                        <label for="cancellationReason" class="form-label">Motif d'annulation (optionnel)</label>
                        <textarea class="form-control" id="cancellationReason" name="cancellation_reason" rows="3" maxlength="500" placeholder="Expliquez rapidement pourquoi l'événement est annulé"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Retour</button>
                    <?= csrfField() ?>
                    <input type="hidden" id="cancelEventId" name="cancel_event_id" value="">
                    <button type="submit" class="btn btn-cancel-confirm">Confirmer l'annulation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/user/my-events.js"></script>

<?php require_once __DIR__ . '/../../templates/layouts/footer.php'; ?>
