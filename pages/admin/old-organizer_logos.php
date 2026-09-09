<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté et est admin
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

    // Vérifier si l'utilisateur est admin
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        header('Location: /');
        exit();
    }

    // Traitement de la mise à jour
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_logos'])) {
        try {
            $pdo->beginTransaction();

            // Récupérer tous les logos qui n'ont pas encore de storage_path
            $stmt = $pdo->query("
                SELECT id, logo_path 
                FROM organizer_profiles 
                WHERE storage_path IS NULL 
                AND logo_path IS NOT NULL
            ");
            $logos = $stmt->fetchAll();

            if (count($logos) > 0) {
                $updateStmt = $pdo->prepare("
                    UPDATE organizer_profiles 
                    SET storage_path = :storage_path,
                        logo_path = :logo_path
                    WHERE id = :id
                ");

                foreach ($logos as $logo) {
                    $filename = basename($logo['logo_path']);
                    $newStoragePath = '/uploads/organizers/' . $filename;
                    $newLogoPath = 'https://rando.partageonslaforet.be/uploads/organizers/' . $filename;

                    $updateStmt->execute([
                        'id' => $logo['id'],
                        'storage_path' => $newStoragePath,
                        'logo_path' => $newLogoPath
                    ]);
                }

                $pdo->commit();
                $updateMessage = count($logos) . " logo(s) mis à jour avec succès.";
                $updateSuccess = true;
            } else {
                $updateMessage = "Aucun logo à mettre à jour.";
                $updateSuccess = true;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $updateMessage = "Erreur lors de la mise à jour : " . $e->getMessage();
            $updateSuccess = false;
        }
    }

    // Récupérer la liste des organisateurs
    $stmt = $pdo->query("
        SELECT op.*, u.name as user_name, u.email
        FROM organizer_profiles op
        JOIN users u ON op.user_id = u.id
        ORDER BY op.name ASC
    ");
    $organizers = $stmt->fetchAll();

} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}

// Titre de la page
$pageTitle = "Gestion des logos d'organisateurs";
require_once '../../templates/layouts/header.php';
?>

<div class="container mt-5 pt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/pages/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Logos d'organisateurs</li>
        </ol>
    </nav>

    <div class="row mb-4">
        <div class="col">
            <h1><?php echo $pageTitle; ?></h1>
        </div>
        <div class="col-auto">
            <form method="post" class="d-inline">
                <button type="submit" name="update_logos" class="btn btn-warning">
                    Mettre à jour tous les chemins de logos
                </button>
            </form>
        </div>
    </div>

    <?php if (isset($updateMessage)): ?>
        <div class="alert alert-<?php echo $updateSuccess ? 'success' : 'danger'; ?> mb-4">
            <?php echo $updateMessage; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Logo</th>
                    <th>Nom</th>
                    <th>Utilisateur</th>
                    <th>Chemin du logo</th>
                    <th>Chemin de stockage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($organizers as $organizer): ?>
                    <tr>
                        <td>
                            <?php if ($organizer['logo_path']): ?>
                                <img src="<?php echo htmlspecialchars($organizer['logo_path']); ?>" 
                                     alt="Logo" 
                                     style="max-width: 50px; max-height: 50px;">
                            <?php else: ?>
                                <span class="text-muted">Pas de logo</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($organizer['name']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($organizer['user_name']); ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($organizer['email']); ?></small>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($organizer['logo_path'] ?? 'Non défini'); ?>
                            </small>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($organizer['storage_path'] ?? 'Non défini'); ?>
                            </small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../templates/layouts/footer.php'; ?>
