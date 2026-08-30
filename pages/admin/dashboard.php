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

// Inclure la configuration et les dépendances
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/Models/EventCategory.php';
require_once __DIR__ . '/../../includes/helpers.php';

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

} catch (PDOException $e) {
    error_log("Erreur base de données: " . $e->getMessage());
    header('Location: /?error=db');
    exit();
}

// Titre de la page
$pageTitle = "Administration";

// Inclure l'en-tête
include __DIR__ . '/../../includes/header-solid.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.css" rel="stylesheet">
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 3rem 0;
            margin-bottom: 2rem;
            color: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2a5298;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            padding: 1rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            color: #2a5298;
            background: rgba(42, 82, 152, 0.1);
            border-radius: 8px;
        }

        .nav-tabs .nav-link.active {
            color: #2a5298;
            background: rgba(42, 82, 152, 0.1);
            border-radius: 8px;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }

        .color-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .event-card {
            border: none;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .event-card:hover {
            transform: translateY(-3px);
        }

        .event-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- En-tête du dashboard -->
        <div class="dashboard-header text-center">
            <h1 class="mb-4">Dashboard Administration</h1>
            <div class="d-flex justify-content-center gap-3">
                <a href="/pages/admin/organizer_logos.php" class="btn btn-light">
                    <i class="bi bi-images"></i> Logos
                </a>
                <a href="/pages/admin/users.php" class="btn btn-light">
                    <i class="bi bi-people-fill"></i> Utilisateurs
                </a>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-icon text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                    <div class="stats-label">Utilisateurs</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-icon text-success">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['total_events']; ?></div>
                    <div class="stats-label">Événements</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-icon text-warning">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['pending_events']; ?></div>
                    <div class="stats-label">En attente</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-icon text-info">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['active_events']; ?></div>
                    <div class="stats-label">Actifs</div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="events-tab" data-bs-toggle="tab" href="#events" role="tab">
                    <i class="bi bi-calendar-event"></i> Événements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="categories-tab" data-bs-toggle="tab" href="#categories" role="tab">
                    <i class="bi bi-tags-fill"></i> Catégories
                </a>
            </li>
        </ul>

        <!-- Contenu des onglets -->
        <div class="tab-content" id="adminTabsContent">
            <!-- Événements -->
            <div class="tab-pane fade show active" id="events" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <h5 class="mb-0">Événements récents</h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary active" data-status="all">Tous</button>
                            <button type="button" class="btn btn-outline-primary" data-status="pending">En attente</button>
                            <button type="button" class="btn btn-outline-primary" data-status="approved">Actifs</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Titre</th>
                                        <th>Organisateur</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="eventsTableBody">
                                    <?php foreach ($recentEvents as $event): ?>
                                        <tr data-status="<?php echo $event['status']; ?>">
                                            <td><?php echo date('d/m/Y', strtotime($event['date'])); ?></td>
                                            <td>
                                                <?php echo h($event['title']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi <?php echo $event['category_icon']; ?>" 
                                                       style="color: <?php echo $event['category_color']; ?>"></i>
                                                    <?php echo h($event['category_name']); ?>
                                                </small>
                                            </td>
                                            <td><?php echo h($event['organizer_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $event['status'] === 'pending' ? 'warning' : 
                                                        ($event['status'] === 'approved' ? 'success' : 'secondary'); 
                                                    ?>">
                                                    <?php 
                                                        switch($event['status']) {
                                                            case 'pending':
                                                                echo 'En attente';
                                                                break;
                                                            case 'approved':
                                                                echo 'Actif';
                                                                break;
                                                            default:
                                                                echo ucfirst($event['status']);
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="/pages/admin/view_event.php?id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($event['status'] === 'pending'): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-success"
                                                                onclick="updateEventStatus(<?php echo $event['id']; ?>, 'approved')">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger"
                                                            onclick="deleteEvent(<?php echo $event['id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Catégories -->
            <div class="tab-pane fade" id="categories" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <h5 class="mb-0">Gestion des catégories</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                            <i class="bi bi-plus"></i> Nouvelle catégorie
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;"></th>
                                        <th>Code</th>
                                        <th>Nom</th>
                                        <th>Icône</th>
                                        <th>Couleur</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="categoriesTableBody">
                                    <?php foreach ($categories as $category): ?>
                                        <tr data-id="<?= htmlspecialchars($category['id']) ?>">
                                            <td>
                                                <i class="bi bi-grip-vertical handle" style="cursor: move;"></i>
                                            </td>
                                            <td><?= htmlspecialchars($category['code']) ?></td>
                                            <td><?= htmlspecialchars($category['name']) ?></td>
                                            <td>
                                                <?php if ($category['icon']): ?>
                                                    <i class="bi <?= htmlspecialchars($category['icon']) ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($category['color']): ?>
                                                    <span class="color-preview" style="background-color: <?= htmlspecialchars($category['color']) ?>"></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input toggle-category" 
                                                           type="checkbox" 
                                                           <?= $category['active'] ? 'checked' : '' ?>
                                                           data-id="<?= $category['id'] ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary edit-category"
                                                        data-id="<?= $category['id'] ?>"
                                                        data-code="<?= htmlspecialchars($category['code']) ?>"
                                                        data-name="<?= htmlspecialchars($category['name']) ?>"
                                                        data-icon="<?= htmlspecialchars($category['icon'] ?? '') ?>"
                                                        data-color="<?= htmlspecialchars($category['color'] ?? '') ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger delete-category"
                                                        data-id="<?= $category['id'] ?>">
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
            </div>
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
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script src="/assets/js/admin/categories.js"></script>
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

    // Filtres des événements
    document.addEventListener('DOMContentLoaded', function() {
        function filterEvents(status) {
            const rows = document.querySelectorAll('#eventsTableBody tr');
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        filterEvents('all');

        document.querySelectorAll('.btn-group .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.parentElement.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterEvents(this.dataset.status);
            });
        });
    });
    </script>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
