<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log pour debug
error_log("=== DÉBUT DASHBOARD.PHP ===");

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug des variables de session
error_log("Session ID dans dashboard: " . session_id());
error_log("Session data dans dashboard: " . print_r($_SESSION, true));

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    error_log("ERREUR dashboard: Utilisateur non connecté");
    header('Location: /');
    exit();
}

error_log("User ID dans dashboard: " . $_SESSION['user_id']);

// Inclure la configuration
require_once '/home/cool5792/rando.partageonslaforet.be/includes/config.php';

try {
    // Connexion à la base de données
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    error_log("Connexion BD réussie dans dashboard");

    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    error_log("User data dans dashboard: " . print_r($user, true));

    if (!$user) {
        error_log("ERREUR dashboard: Utilisateur non trouvé dans la BD");
        session_destroy();
        header('Location: /');
        exit();
    }

    if ($user['role'] !== 'admin') {
        error_log("ERREUR dashboard: L'utilisateur n'est pas admin (role = " . $user['role'] . ")");
        header('Location: /pages/user/profile.php');
        exit();
    }

    error_log("Utilisateur admin confirmé dans dashboard");

    // Récupérer les statistiques
    $stats = [
        'total_users' => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'total_events' => $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn()
    ];
    error_log("Stats récupérées dans dashboard: " . print_r($stats, true));

} catch (PDOException $e) {
    error_log("ERREUR BD dans dashboard: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    header('Location: /?error=db');
    exit();
}

// Titre de la page
$pageTitle = "Administration - Dashboard";

// Inclure l'en-tête
error_log("=== CHARGEMENT DU HEADER DANS DASHBOARD ===");
include '/home/cool5792/rando.partageonslaforet.be/includes/header.php';
error_log("Header chargé avec succès dans dashboard");

?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Dashboard Administration</h1>
        <div>
            <a href="/pages/admin/users.php" class="btn btn-primary me-2" onclick="console.log('Clic sur Gérer les Utilisateurs')">
                <i class="fas fa-users"></i> Gérer les Utilisateurs
            </a>
            <a href="/pages/admin/events.php" class="btn btn-primary" onclick="console.log('Clic sur Gérer les Événements - URL:', this.href)">
                <i class="fas fa-calendar"></i> Gérer les Événements
            </a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">
                        <i class="fas fa-users text-primary mb-3"></i>
                        Utilisateurs Total
                    </h5>
                    <p class="card-text display-1 fw-bold text-primary"><?php echo $stats['total_users']; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">
                        <i class="fas fa-calendar text-success mb-3"></i>
                        Événements Total
                    </h5>
                    <p class="card-text display-1 fw-bold text-success"><?php echo $stats['total_events']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Informations supplémentaires -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">Actions disponibles</h5>
            <div class="row">
                <div class="col-md-6">
                    <ul class="list-group">
                        <li class="list-group-item">
                            <i class="fas fa-user-cog text-primary"></i>
                            Gestion des utilisateurs :
                            <ul class="mt-2">
                                <li>Voir tous les utilisateurs</li>
                                <li>Modifier les rôles</li>
                                <li>Gérer les accès</li>
                            </ul>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="list-group">
                        <li class="list-group-item">
                            <i class="fas fa-calendar-check text-success"></i>
                            Gestion des événements :
                            <ul class="mt-2">
                                <li>Voir tous les événements</li>
                                <li>Modérer les événements</li>
                                <li>Gérer les catégories</li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Inclure le footer
require_once '/home/cool5792/rando.partageonslaforet.be/includes/footer.php';
?>
