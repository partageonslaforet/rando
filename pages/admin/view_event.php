<?php
/**
 * Consultation détaillée d'un événement en administration.
 */
// Activer l'affichage des erreurs
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $target = '/pages/admin/view_event.php';
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $target .= '?id=' . (int)$_GET['id'];
    }
    header('Location: /?showLogin=1&redirect=' . urlencode($target));
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
require_once __DIR__ . '/../../src/Utils/helpers.php';
require_once __DIR__ . '/../../src/Services/Storage.php';

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
        header('Location: /?error=admin_required');
        exit();
    }

    // Récupérer les détails de l'événement (données brutes pour admin)
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
                    $newStoragePath = Storage::getStoragePath('events', $filename);
                    $newImagePath = Storage::getPublicUrl('events', $filename);
                    
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

    // Charger l'événement normalisé pour l'affichage public (colonne de droite)
    require_once __DIR__ . '/../../src/Services/EventDisplayBuilder.php';
    $builder = new EventDisplayBuilder($pdo);
    $eventDisplay = $builder->build('published', $eventId);
    if (!$eventDisplay) {
        header('Location: /pages/admin/events.php?error=event_not_found');
        exit();
    }

    // Titre de la page
    $pageTitle = "Administration - " . h($eventDisplay['title'] ?? $event['title']);
    // Feuille de style spécifique à la page (injectée via header.php)
    $additionalStyles =
        '<link rel="stylesheet" href="/assets/css/pages/admin/admin-event.css?v=3">' .
        '<link rel="stylesheet" href="/assets/css/pages/events/event-display.css?v=' . (@filemtime((defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/public/assets/css/pages/events/event-display.css') ?: 1) . '">';

    // Logs de diagnostic: vérifier la présence du CSS côté serveur
    try {
        require_once __DIR__ . '/../../logs/error.log.php';
        $fsCandidates = [
            __DIR__ . '/../../public/assets/css/pages/admin/admin-event.css',
            __DIR__ . '/../../assets/css/pages/admin/admin-event.css',
        ];
        $fsExists = [];
        foreach ($fsCandidates as $p) {
            $fsExists[] = [
                'path' => $p,
                'exists' => file_exists($p),
                'readable' => is_readable($p),
                'mtime' => file_exists($p) ? date('c', filemtime($p)) : null,
                'size' => (file_exists($p) && is_readable($p)) ? filesize($p) : null,
            ];
        }
        if (function_exists('logError')) {
            logError('pages/admin/view_event.php', 'Injection additionalStyles et vérification CSS', [
                'additionalStyles' => $additionalStyles,
                'served_url' => '/assets/css/pages/admin/admin-event.css?v=3',
                'fs_candidates' => $fsExists,
                'event_status' => $event['status'],
                'expected_classes' => [
                    'sidebar_card' => 'card mb-4 admin-actions',
                    'approve_btn' => 'btn btn-admin btn-approve',
                    'reject_btn' => 'btn btn-admin btn-reject',
                    'delete_btn' => 'btn btn-admin btn-delete',
                ],
            ]);
        }
    } catch (Throwable $e) {
        error_log('view_event.php: logError indisponible: ' . $e->getMessage());
    }

} catch (PDOException $e) {
    error_log("Erreur BD: " . $e->getMessage());
    header('Location: /pages/admin/events.php?error=db');
    exit();
}

// Inclure l'en-tête (intègre $additionalStyles dans <head>)
include __DIR__ . '/../../templates/layouts/header.php';
?>

<div class="container mt-5 pt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/pages/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo h($event['title']); ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Sidebar admin à gauche -->
        <aside class="col-lg-3">
            <div class="card mb-4 admin-actions">
                <div class="card-body">
                    <h5 class="card-title">Actions administrateur</h5>
                    
                    <div class="d-grid">
                         <button class="btn btn-admin btn-approve" onclick="updateEventStatus(<?php echo $event['id']; ?>, 'approved')">
                            <i class="bi bi-check-circle me-1"></i> Valider
                        </button>
                        <button class="btn btn-admin btn-edit" onclick="editViaModal(<?php echo (int)$event['id']; ?>)">
                            <i class="bi bi-pencil-square me-1"></i> Modifier
                        </button>
                        <button class="btn btn-admin btn-reject" onclick="openRejectModal(<?php echo $event['id']; ?>)">
                            <i class="bi bi-x-circle me-1"></i> Refuser
                        </button>
                        <button class="btn btn-admin btn-delete" onclick="deleteEvent(<?php echo $event['id']; ?>)">
                            <i class="bi bi-trash me-1"></i> Supprimer
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Page événement (rendu public) à droite -->
        <div class="col-lg-9">
            <?php
                // Isoler la variable $event pour le template d'affichage, sans casser celles de la sidebar
                $__event_backup = $event;
                $event = $eventDisplay; // variable attendue par event-display.php
                $mode = 'published';
                include __DIR__ . '/../../templates/events/event-display.php';
                $event = $__event_backup;
                unset($__event_backup);
            ?>
        </div>
    </div>
</div>

<!-- Modal Refus: raison de rejet -->
<div class="modal fade modal-brand" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rejectModalLabel">Motif du refus</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="rejectReason" class="form-label">Veuillez indiquer la raison du refus (optionnel)</label>
          <textarea class="form-control" id="rejectReason" rows="4" placeholder="Ex: informations incomplètes, date invalide, ..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-brand-cancel" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-brand-reject" id="confirmRejectBtn">Confirmer le refus</button>
      </div>
    </div>
  </div>
  </div>

<script>
function deleteEvent(eventId) {
    if (confirm('Voulez-vous vraiment supprimer cet événement ? Cette action est irréversible.')) {
        fetch('/api/admin/events/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                event_id: parseInt(eventId, 10)
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Erreur serveur');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.href = '/pages/admin/events.php?message=event_deleted';
            } else {
                console.error('Erreur serveur:', data);
                alert(data.message || 'Une erreur est survenue lors de la suppression');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert(error.message || 'Une erreur est survenue lors de la suppression');
        });
    }
}

function updateEventStatus(eventId, status, rejectionReason) {
    if (!confirm(`Voulez-vous vraiment ${status === 'approved' ? 'approuver' : status === 'rejected' ? 'rejeter' : 'remettre en attente'} cet événement ?`)) {
        return;
    }

    const data = {
        event_id: parseInt(eventId, 10),
        status: status
    };
    if (status === 'rejected' && typeof rejectionReason === 'string') {
        const rr = rejectionReason.trim();
        if (rr.length > 0) {
            data.rejection_reason = rr;
        }
    }

    console.log('Envoi des données:', data);

    fetch('/api/admin/events/update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || 'Erreur serveur');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            console.error('Erreur serveur:', data);
            alert(data.message || 'Une erreur est survenue');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert(error.message || 'Une erreur est survenue lors de la mise à jour du statut');
    });
}

function openRejectModal(eventId) {
    const modalEl = document.getElementById('rejectModal');
    const reasonEl = document.getElementById('rejectReason');
    const confirmBtn = document.getElementById('confirmRejectBtn');
    if (!modalEl || !reasonEl || !confirmBtn) return;

    reasonEl.value = '';
    const modal = new bootstrap.Modal(modalEl);
    confirmBtn.onclick = function() {
        const reason = reasonEl.value || '';
        updateEventStatus(eventId, 'rejected', reason);
        modal.hide();
    };
    modal.show();
}

// Ouvrir l'éditeur direct sans créer de brouillon
function editViaModal(eventId) {
    try { sessionStorage.setItem('adminEdit','1'); } catch(_) {}
    window.location.href = `/templates/events/edit-event.php?id=${encodeURIComponent(eventId)}`;
}
</script>

<!-- Scripts d'affichage de l'événement (carte, traces GPX) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js"></script>
<script src="/assets/js/events/event-display.js?v=<?= @filemtime((defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/public/assets/js/events/event-display.js') ?: 1 ?>"></script>

<!-- Modale de confirmation & Toasts -->
<div class="modal fade modal-confirm" id="confirmActionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmActionTitle">Confirmer l’action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <p id="confirmActionMessage">Êtes-vous sûr ?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn" id="confirmActionBtn">Confirmer</button>
      </div>
    </div>
  </div>
 </div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="adminToastContainer"></div>

<!-- Actions admin (confirmations et toasts) -->
<script src="/assets/js/admin/admin-actions.js"></script>

<?php
include __DIR__ . '/../../templates/layouts/footer.php';
?>
