<?php
$pageTitle = "Mise à jour des chemins de stockage";
require_once '../../includes/header.php';

// Vérifier que l'utilisateur est admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /login.php');
    exit;
}

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_paths'])) {
    try {
        // Connexion à la base de données
        $db = getConnection();
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        $messages = [];
        
        try {
            // 1. Mise à jour des images d'événements
            $stmt = $db->query("SELECT id, image_path, storage_path FROM event_images WHERE storage_path IS NULL");
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($images) > 0) {
                $updateStmt = $db->prepare("
                    UPDATE event_images 
                    SET storage_path = :storage_path,
                        image_path = :image_path
                    WHERE id = :id
                ");
                
                foreach ($images as $image) {
                    $filename = basename($image['image_path']);
                    $newStoragePath = '/uploads/events/' . $filename;
                    $newImagePath = 'https://rando.partageonslaforet.be/uploads/events/' . $filename;
                    
                    $updateStmt->execute([
                        'id' => $image['id'],
                        'storage_path' => $newStoragePath,
                        'image_path' => $newImagePath
                    ]);
                }
                $messages[] = count($images) . " images d'événements mises à jour.";
            } else {
                $messages[] = "Aucune image d'événement à mettre à jour.";
            }
            
            // 2. Mise à jour des logos d'organisateurs
            $stmt = $db->query("SELECT id, logo_path FROM organizer_profiles WHERE storage_path IS NULL AND logo_path IS NOT NULL");
            $logos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($logos) > 0) {
                $updateStmt = $db->prepare("
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
                $messages[] = count($logos) . " logos d'organisateurs mis à jour.";
            } else {
                $messages[] = "Aucun logo d'organisateur à mettre à jour.";
            }
            
            // Valider les changements
            $db->commit();
            $success = true;
            
        } catch (Exception $e) {
            // En cas d'erreur, annuler les changements
            $db->rollBack();
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    } catch (Exception $e) {
        $error = "Erreur de connexion à la base de données : " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h1><?php echo $pageTitle; ?></h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <h4>Mise à jour effectuée avec succès !</h4>
            <ul>
                <?php foreach ($messages as $message): ?>
                    <li><?php echo $message; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Mise à jour des chemins de stockage</h5>
            <p class="card-text">
                Cette opération va :
                <ul>
                    <li>Mettre à jour les chemins des images d'événements vers <code>/uploads/events/</code></li>
                    <li>Mettre à jour les chemins des logos d'organisateurs vers <code>/uploads/organizers/</code></li>
                </ul>
            </p>
            
            <form method="post" class="mt-3">
                <button type="submit" name="update_paths" class="btn btn-primary">
                    Lancer la mise à jour
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
