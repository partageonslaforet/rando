<?php
/**
 * Tableau de bord administrateur.
 */
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

// Inclure la configuration et les dépendances
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/Models/EventCategory.php';
require_once __DIR__ . '/../../src/Utils/helpers.php';

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

    if (!$user) {
        session_destroy();
        header('Location: /');
        exit();
    }

    if ($user['role'] !== 'admin') {
        header('Location: /pages/user/profile.php');
        exit();
    }

    // Récupérer les statistiques avec vérification des erreurs
    try {
        $stats = [
            'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'total_events' => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
            'pending_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'pending'")->fetchColumn(),
            'active_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'")->fetchColumn()
        ];
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des statistiques : " . $e->getMessage());
        $stats = [
            'total_users' => 0,
            'total_events' => 0,
            'pending_events' => 0,
            'active_events' => 0
        ];
    }

    try {
        $stats['total_organizations'] = $pdo->query("SELECT COUNT(*) FROM organizer_profiles")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur total_organizations : " . $e->getMessage());
        $stats['total_organizations'] = 0;
    }

    try {
        $stats['total_event_views'] = $pdo->query("SELECT COALESCE(SUM(view_count), 0) FROM events")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur total_event_views : " . $e->getMessage());
        $stats['total_event_views'] = 0;
    }

    // Récupérer les événements récents avec leurs catégories
    try {
        $recentEvents = $pdo->query("
            SELECT e.*, u.name as organizer_name, c.name as category_name, c.icon as category_icon, c.color as category_color
            FROM events e 
            JOIN users u ON e.user_id = u.id 
            LEFT JOIN event_categories c ON e.category_id = c.id
            ORDER BY e.created_at DESC 
            LIMIT 10
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des événements récents : " . $e->getMessage());
        $recentEvents = [];
    }

    // Récupérer toutes les catégories
    $categoryManager = new EventCategory($pdo);
    $categories = $categoryManager->getAll();
    // Tri alphabétique sur le nom uniquement (insensible à la casse)
    if (is_array($categories)) {
        usort($categories, function($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
    }

} catch (PDOException $e) {
    error_log("Erreur base de données: " . $e->getMessage());
    header('Location: /?error=db');
    exit();
}

// Titre de la page
$pageTitle = "Administration";
$additionalStyles = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.css">' . "\n"
                  . '<link rel="stylesheet" href="/assets/css/dashboard.css">' . "\n"
                  . '<link rel="stylesheet" href="/assets/css/admin-dashboard.css">';

// Inclure l'en-tête
include __DIR__ . '/../../templates/layouts/header-solid.php';
?>

<div class="container mt-4">
  <!-- Hero -->
  <div class="dash-hero d-flex justify-content-between align-items-center">
    <h1>Tableau de bord</h1>
    <div class="hero-actions">
      <!-- <a href="/?create=1" class="btn btn-success btn-pill"><i class="bi bi-plus-lg"></i> Créer un événement</a>
      <a href="/pages/admin/events.php" class="btn btn-outline-secondary btn-pill ms-2"><i class="bi bi-list-ul"></i> Tous les événements</a> -->
    </div>
  </div>

  <!-- Summary tiles -->
  <div class="summary-tiles">
    <div class="summary-card">
      <div class="summary-icon users"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="summary-label">UTILISATEURS INSCRITS</div>
        <div class="summary-value"><?php echo (int)$stats['total_users']; ?></div>
      </div>
    </div>
    <div class="summary-card">
      <div class="summary-icon orgs"><i class="bi bi-building-fill"></i></div>
      <div>
        <div class="summary-label">ORGANISATIONS INSCRITES</div>
        <div class="summary-value"><?php echo (int)$stats['total_organizations']; ?></div>
      </div>
    </div>
    <div class="summary-card">
      <div class="summary-icon views"><i class="bi bi-eye-fill"></i></div>
      <div>
        <div class="summary-label">PAGES ÉVÉNEMENTS VUES</div>
        <div class="summary-value"><?php echo (int)$stats['total_event_views']; ?></div>
      </div>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-icon users"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="kpi-label">UTILISATEURS</div>
        <div class="kpi-value"><?php echo (int)$stats['total_users']; ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon events"><i class="bi bi-calendar-event-fill"></i></div>
      <div>
        <div class="kpi-label">ÉVÉNEMENTS</div>
        <div class="kpi-value"><?php echo (int)$stats['total_events']; ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon pending"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="kpi-label">EN ATTENTE</div>
        <div class="kpi-value"><?php echo (int)$stats['pending_events']; ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon active"><i class="bi bi-check2-circle"></i></div>
      <div>
        <div class="kpi-label">ACTIFS</div>
        <div class="kpi-value"><?php echo (int)$stats['active_events']; ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon categories"><i class="bi bi-tags-fill"></i></div>
      <div>
        <div class="kpi-label">CATÉGORIES</div>
        <div class="kpi-value"><?php echo is_array($categories) ? count($categories) : 0; ?></div>
      </div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="dash-toolbar">
    <div class="pills">
      <button type="button" class="pill active" data-filter="all">Tous</button>
      <button type="button" class="pill" data-filter="pending">En attente</button>
      <button type="button" class="pill" data-filter="approved">Actifs</button>
      <button type="button" class="pill" data-filter="rejected">Rejetés</button>
      <button type="button" class="pill" data-filter="expired">Échus</button>
    </div>
    <div class="search"><input type="search" id="dashSearch" class="form-control" placeholder="Rechercher par titre ou organisateur…"></div>
  </div>

  <div class="row g-4">
    <aside class="col-lg-3">
      <div class="dash-sidenav">
        <nav class="nav flex-column">
          <a href="#" class="nav-link active" data-target="section-events"><i class="bi bi-calendar-event me-1"></i> Événements</a>
          <a href="#" class="nav-link" data-target="section-categories"><i class="bi bi-tags-fill me-1"></i> Catégories</a>
        </nav>
      </div>
    </aside>

    <main class="col-lg-9">
      <!-- Section Événements -->
      <section id="section-events" data-section>
        <!-- Événements récents -->
        <div class="card">
          <div class="card-body">
            <h5 class="card-title mb-3"><i class="bi bi-clock-history"></i> Événements récents</h5>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>DATE</th>
                    <th>TITRE</th>
                    <th>ORGANISATEUR</th>
                    <th>STATUT</th>
                    <th class="text-end">ACTIONS</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($recentEvents as $event): 
                  $status = strtolower($event['status'] ?? 'pending');
                  $badgeClass = $status === 'approved' ? 'status-approved' : ($status === 'rejected' ? 'status-rejected' : 'status-pending');
                  $dateTxt = !empty($event['date']) ? date('d/m/Y', strtotime($event['date'])) : '-';
                  $titleTxt = h($event['title'] ?? 'Sans titre');
                  $orgTxt = h($event['organizer_name'] ?? '-');
                  $isExpired = !empty($event['date']) && (strtotime($event['date']) < strtotime(date('Y-m-d')));
                ?>
                  <tr data-status="<?= $status ?>" data-title="<?= $titleTxt ?>" data-org="<?= $orgTxt ?>" data-expired="<?= $isExpired ? '1' : '0' ?>">
                    <td><?= $dateTxt ?></td>
                    <td>
                      <?= $titleTxt ?><br>
                      <small class="text-muted">
                        <?php
                          $rawIcon = $event['category_icon'] ?? '';
                          $iconClass = 'bi bi-tree';
                          if ($rawIcon) {
                            if (str_starts_with($rawIcon, 'fa')) {
                              $iconClass = (str_contains($rawIcon, 'fa-') && !str_contains($rawIcon, 'fa-solid') && !str_starts_with($rawIcon, 'fas ')) ? ('fa-solid ' . $rawIcon) : $rawIcon;
                            } elseif (str_starts_with($rawIcon, 'bi-')) {
                              $iconClass = 'bi ' . $rawIcon;
                            }
                          }
                        ?>
                        <i class="<?= htmlspecialchars($iconClass) ?>" style="color: <?= htmlspecialchars($event['category_color'] ?? '') ?>"></i>
                        <?= h($event['category_name'] ?? '') ?>
                      </small>
                    </td>
                    <td><?= $orgTxt ?></td>
                    <td><span class="status-badge <?= $badgeClass ?>"><?php if ($status === 'approved'): ?>Actif<?php elseif ($status === 'rejected'): ?>Rejeté<?php else: ?>En attente<?php endif; ?></span></td>
                    <td class="text-end">
                      <a href="/pages/admin/view_event.php?id=<?= (int)$event['id'] ?>" class="btn btn-sm btn-view btn-pill me-2"><i class="bi bi-eye"></i> Voir</a>
                      <button type="button" class="btn btn-sm btn-delete btn-pill" onclick="deleteEvent(<?= (int)$event['id'] ?>)"><i class="bi bi-trash"></i> Suppr.</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($recentEvents)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-4">Aucun événement récent</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- Section Catégories -->
      <section id="section-categories" class="d-none" data-section>
        <div class="card">
          <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0">Gestion des catégories</h5>
            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#categoryModal">
              <i class="bi bi-plus"></i> Nouvelle catégorie
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Icône</th>
                    <th>Couleur</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="categoriesTableBody">
                  <?php foreach ($categories as $category): ?>
                    <tr data-id="<?= htmlspecialchars($category['id']) ?>">
                      <td><?= htmlspecialchars($category['code']) ?></td>
                      <td><?= htmlspecialchars($category['name']) ?></td>
                      <td>
                        <?php 
                          $rawIcon = $category['icon'] ?? '';
                          if ($rawIcon) {
                            $iconClass = 'bi bi-tree';
                            if (str_starts_with($rawIcon, 'fa')) {
                              $iconClass = (str_contains($rawIcon, 'fa-') && !str_contains($rawIcon, 'fa-solid') && !str_starts_with($rawIcon, 'fas '))
                                  ? ('fa-solid ' . $rawIcon)
                                  : $rawIcon;
                            } elseif (str_starts_with($rawIcon, 'bi-')) {
                              $iconClass = 'bi ' . $rawIcon;
                            }
                            echo '<i class="' . htmlspecialchars($iconClass) . '"></i>';
                          }
                        ?>
                      </td>
                      <td>
                        <?php if ($category['color']): ?>
                          <span class="color-preview" style="background-color: <?= htmlspecialchars($category['color']) ?>"></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn btn-sm btn-outline-primary edit-category"
                                data-id="<?= $category['id'] ?>"
                                data-code="<?= htmlspecialchars($category['code']) ?>"
                                data-name="<?= htmlspecialchars($category['name']) ?>"
                                data-icon="<?= htmlspecialchars($category['icon'] ?? '') ?>"
                                data-color="<?= htmlspecialchars($category['color'] ?? '') ?>">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-category" data-id="<?= $category['id'] ?>">
                          <i class="bi bi-trash"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

    <!-- Modal pour les catégories -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="categoryForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Catégorie</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="category_id" name="id">
                        
                        <div class="mb-3">
                            <label for="category_code" class="form-label">Code</label>
                            <input type="text" class="form-control" id="category_code" name="code" required>
                            <div class="form-text">Code unique pour identifier la catégorie (ex: hiking)</div>
                        </div>

                        <div class="mb-3">
                            <label for="category_name" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="category_name" name="name" required>
                            <div class="form-text">Nom affiché aux utilisateurs (ex: Randonnée)</div>
                        </div>

                        <div class="mb-3">
                            <label for="category_icon" class="form-label">Icône</label>
                            <input type="text" class="form-control" id="category_icon" name="icon">
                            <div class="form-text">Classe d'icône Bootstrap (ex: bi-bicycle)</div>
                        </div>

                        <div class="mb-3">
                            <label for="category_color" class="form-label">Couleur</label>
                            <input type="color" class="form-control" id="category_color" name="color">
                            <div class="form-text">Couleur pour le badge de la catégorie</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-secondary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script src="/assets/js/admin/categories.js"></script>
    <script src="/assets/js/admin/admin-dashboard.js"></script>
    <script>
    function updateEventStatus(eventId, status) {
        if (!confirm('Êtes-vous sûr de vouloir ' + (status === 'approved' ? 'approuver' : 'rejeter') + ' cet événement ?')) {
            return;
        }

        fetch('/api/admin/events/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                event_id: eventId,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    }

    function deleteEvent(eventId) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')) {
            return;
        }

        fetch('/api/admin/events/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                event_id: eventId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    }

    // Ancien filtre retiré (remplacé par admin-dashboard.js)
    </script>

</div>
    <?php include __DIR__ . '/../../templates/layouts/footer.php'; ?>
