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
require_once '/home/cool5792/rando.partageonslaforet.be/includes/config.php';
require_once '/home/cool5792/rando.partageonslaforet.be/src/Models/EventCategory.php';

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

    // Vérifier si l'utilisateur est bien un admin
    if ($user['role'] !== 'admin') {
        header('Location: /pages/user/profile.php');
        exit();
    }

    // Récupérer tous les utilisateurs avec leurs statistiques
    $stmt = $pdo->query('
        SELECT u.*, 
               COUNT(DISTINCT e.id) as total_events,
               MAX(u.last_login) as last_login_date
        FROM users u
        LEFT JOIN events e ON u.id = e.user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ');
    $users = $stmt->fetchAll();

    // Récupérer toutes les catégories
    $categoryManager = new EventCategory($pdo);
    $categories = $categoryManager->getAll();

} catch (PDOException $e) {
    error_log("Erreur base de données: " . $e->getMessage());
    header('Location: /?error=db');
    exit();
}

// Titre de la page
$pageTitle = "Administration - Gestion des utilisateurs";

// Inclure l'en-tête
include '/home/cool5792/rando.partageonslaforet.be/includes/header-solid.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Administration</h1>
            <a href="/pages/admin/dashboard.php" class="btn btn-secondary">Retour au Dashboard</a>
        </div>

        <!-- Navigation -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= empty($_GET['tab']) || $_GET['tab'] === 'users' ? 'active' : '' ?>" 
                   id="users-tab" data-bs-toggle="tab" href="#users" role="tab">
                    <i class="bi bi-people-fill"></i> Utilisateurs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'categories' ? 'active' : '' ?>" 
                   id="categories-tab" data-bs-toggle="tab" href="#categories" role="tab">
                    <i class="bi bi-tags-fill"></i> Catégories
                </a>
            </li>
        </ul>

        <!-- Contenu des onglets -->
        <div class="tab-content" id="adminTabsContent">
            <!-- Utilisateurs -->
            <div class="tab-pane fade <?= empty($_GET['tab']) || $_GET['tab'] === 'users' ? 'show active' : '' ?>" 
                 id="users" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Statut</th>
                                        <th>Événements</th>
                                        <th>Dernière connexion</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?php echo $u['id']; ?></td>
                                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                    <?php echo htmlspecialchars($u['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $u['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                                <span class="badge bg-<?php echo $u['is_verified'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $u['is_verified'] ? 'Vérifié' : 'Non vérifié'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $u['total_events']; ?> événements</td>
                                            <td>
                                                <?php echo $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Jamais'; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                                        <button type="button" class="btn btn-sm <?php echo $u['is_active'] ? 'btn-danger' : 'btn-success'; ?>" 
                                                                onclick="updateUserStatus(<?php echo $u['id']; ?>, <?php echo $u['is_active'] ? 'false' : 'true'; ?>)">
                                                            <?php echo $u['is_active'] ? 'Désactiver' : 'Activer'; ?>
                                                        </button>
                                                        <button type="button" class="btn btn-sm <?php echo $u['role'] === 'admin' ? 'btn-warning' : 'btn-info'; ?>" 
                                                                onclick="updateUserRole(<?php echo $u['id']; ?>, '<?php echo $u['role'] === 'admin' ? 'user' : 'admin'; ?>')">
                                                            <?php echo $u['role'] === 'admin' ? 'Retirer admin' : 'Faire admin'; ?>
                                                        </button>
                                                    <?php endif; ?>
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
            <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'categories' ? 'show active' : '' ?>" 
                 id="categories" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Gestion des catégories</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                            <i class="bi bi-plus"></i> Nouvelle catégorie
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
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

    <style>
    .color-preview {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script src="/assets/js/admin/categories.js"></script>
    <script>
    function updateUserStatus(userId, isActive) {
        if (confirm('Êtes-vous sûr de vouloir ' + (isActive ? 'activer' : 'désactiver') + ' cet utilisateur ?')) {
            fetch('/api/users/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    is_active: isActive
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
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
    }

    function updateUserRole(userId, role) {
        if (confirm('Êtes-vous sûr de vouloir ' + (role === 'admin' ? 'promouvoir' : 'rétrograder') + ' cet utilisateur ?')) {
            fetch('/api/users/update_role.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    role: role
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
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
    }

    // Conserver l'onglet actif après un rechargement
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab) {
            const tabEl = document.querySelector(`#adminTabs a[href="#${tab}"]`);
            if (tabEl) {
                new bootstrap.Tab(tabEl).show();
            }
        }

        // Mettre à jour l'URL quand on change d'onglet
        document.querySelectorAll('#adminTabs a[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                const id = e.target.getAttribute('href').substring(1);
                const url = new URL(window.location);
                url.searchParams.set('tab', id);
                window.history.pushState({}, '', url);
            });
        });
    });
    </script>

    <?php include '/home/cool5792/rando.partageonslaforet.be/includes/footer.php'; ?>
</body>
</html>
