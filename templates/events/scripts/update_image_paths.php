<?php
/**
 * localisation: templates/events/scripts/update_image_paths.php
 * Role: Mettre a jour les chemins images et logos en base (one-time / migration)
 * Usage: Lancer en CLI : php templates/events/scripts/update_image_paths.php
 * Dépendances: config/database.php, constante APP_URL
 */

require_once __DIR__ . '/../../../config/database.php';

try {
    // Connexion à la base de données
    $db = getConnection();
    
    // Démarrer une transaction
    $db->beginTransaction();
    
    try {
        // 1. Mise à jour des images d'événements
        echo "Mise à jour des chemins d'images d'événements...\n";
        
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
                $newImagePath = APP_URL . '/uploads/events/' . $filename;
                
                echo "Ancien chemin: " . $image['image_path'] . "\n";
                echo "Nouveau chemin public: " . $newImagePath . "\n";
                echo "Nouveau chemin stockage: " . $newStoragePath . "\n\n";
                
                $updateStmt->execute([
                    'id' => $image['id'],
                    'storage_path' => $newStoragePath,
                    'image_path' => $newImagePath
                ]);
            }
        } else {
            echo "Aucune image d'événement à mettre à jour.\n";
        }
        
        // 2. Mise à jour des logos d'organisateurs
        echo "\nMise à jour des logos d'organisateurs...\n";
        
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
                $newStoragePath = '/uploads/organizer_logos/' . $filename;
                $newLogoPath = APP_URL . '/uploads/organizer_logos/' . $filename;
                
                echo "Ancien chemin: " . $logo['logo_path'] . "\n";
                echo "Nouveau chemin public: " . $newLogoPath . "\n";
                echo "Nouveau chemin stockage: " . $newStoragePath . "\n\n";
                
                $updateStmt->execute([
                    'id' => $logo['id'],
                    'storage_path' => $newStoragePath,
                    'logo_path' => $newLogoPath
                ]);
            }
        } else {
            echo "Aucun logo d'organisateur à mettre à jour.\n";
        }
        
        // Valider les changements
        $db->commit();
        echo "\nMise à jour terminée avec succès !\n";
        
    } catch (Exception $e) {
        // En cas d'erreur, annuler les changements
        $db->rollBack();
        echo "Erreur lors de la mise à jour : " . $e->getMessage() . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Erreur de connexion à la base de données : " . $e->getMessage() . "\n";
    exit(1);
}
