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

// Inclure la configuration
require_once '/home/cool5792/rando.partageonslaforet.be/includes/config.php';

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
        header('Location: /pages/user/profile-solid.php');
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

} catch (PDOException $e) {
    error_log("Erreur base de données: " . $e->getMessage());
    header('Location: /?error=db');
    exit();
}

// Titre de la page
$pageTitle = "Administration - Gestion des utilisateurs";

// Inclure l'en-tête
include '/home/cool5792/rando.partageonslaforet.be/includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
</head>
<body>
    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Gestion des utilisateurs</h1>
            <a href="/pages/admin/dashboard.php" class="btn btn-secondary">Retour au Dashboard</a>
        </div>

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
    </script>

    <?php include '/home/cool5792/rando.partageonslaforet.be/includes/footer.php'; ?>
</body>
</html>
