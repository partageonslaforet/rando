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
require_once __DIR__ . '/../../includes/config.php';

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
        // Si l'utilisateur n'existe pas dans la base de données, le déconnecter
        session_destroy();
        header('Location: /');
        exit();
    }

    // Vérifier si l'utilisateur est bien un admin
    if ($user['role'] !== 'admin') {
        header('Location: /pages/user/profile.php');
        exit();
    }

    // Récupérer quelques statistiques pour l'admin
    $stats = [
        'total_users' => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'total_events' => $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn(),
        'pending_events' => $pdo->query('SELECT COUNT(*) FROM events WHERE status = "pending"')->fetchColumn(),
        'active_users' => $pdo->query('SELECT COUNT(*) FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn()
    ];

} catch (PDOException $e) {
    error_log("Erreur base de données: " . $e->getMessage());
    header('Location: /?error=db');
    exit();
}

// Titre de la page
$pageTitle = "Administration - Mon Profil";

// Inclure l'en-tête
include __DIR__ . '/../../includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <!-- Profil Admin -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="mb-0">Mon Profil Admin</h2>
                </div>
                <div class="card-body">
                    <p><strong>Nom :</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                    <p><strong>Email :</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Rôle :</strong> <?php echo htmlspecialchars($user['role']); ?></p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        Modifier mon profil
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">Statistiques du Site</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Utilisateurs Total</h5>
                                    <p class="card-text display-4"><?php echo $stats['total_users']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Utilisateurs Actifs</h5>
                                    <p class="card-text display-4"><?php echo $stats['active_users']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Événements Total</h5>
                                    <p class="card-text display-4"><?php echo $stats['total_events']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Événements en Attente</h5>
                                    <p class="card-text display-4"><?php echo $stats['pending_events']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de modification du profil -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">Modifier mon profil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editProfileForm">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">Nouveau mot de passe (optionnel)</label>
                        <input type="password" class="form-control" id="newPassword" name="newPassword">
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword">
                    </div>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
