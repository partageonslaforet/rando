<?php
/**
 * Utilitaires de traitement et redimensionnement d'images.
 */

require_once __DIR__ . '/../Services/Storage.php';

/**
 * Gère l'upload d'une image
 * @param array $file Fichier uploadé ($_FILES['name'])
 * @param string $type Type d'image ('event' ou 'organizer')
 * @return array|false Tableau contenant les chemins ou false si erreur
 */
function handleImageUpload($file, $type = 'event') {
    if (isset($_GET['debug'])) {
        echo "Début de handleImageUpload pour type: " . $type;
        echo "Fichier reçu : " . print_r($file, true);
    }

    try {
        // Déterminer le type de stockage
        $storageType = ($type === 'organizer') ? 'organizers' : 'events';
        
        // Utiliser la classe Storage pour sauvegarder le fichier
        $result = Storage::saveUploadedFile($file, $storageType);
        
        // Retourner un tableau avec tous les chemins nécessaires
        return [
            'public_url' => $result['public_url'],
            'storage_path' => $result['storage_path'],
            'filename' => $result['filename']
        ];
    } catch (Exception $e) {
        if (isset($_GET['debug'])) {
            echo "Erreur lors de l'upload : " . $e->getMessage();
        }
        return false;
    }
}

/**
 * Enregistre les images d'un événement dans la base de données
 * @param PDO $db Connexion à la base de données
 * @param int $eventId ID de l'événement
 * @param array|string $mainImage Chemin ou tableau de l'image principale
 * @param array $secondaryImages Chemins des images secondaires
 * @return bool
 */
function saveEventImages($db, $eventId, $mainImage, $secondaryImages = []) {
    try {
        // Insérer l'image principale
        if ($mainImage) {
            $stmt = $db->prepare("
                INSERT INTO event_images (event_id, image_path, storage_path, is_main)
                VALUES (:event_id, :image_path, :storage_path, 1)
            ");
            
            // Si $mainImage est un tableau (nouvel upload)
            if (is_array($mainImage)) {
                $imagePath = $mainImage['public_url'];
                $storagePath = $mainImage['storage_path'];
            } else {
                // Si c'est juste un chemin (ancien format)
                $imagePath = $mainImage;
                $storagePath = '/uploads/events/' . basename($mainImage);
            }
            
            $stmt->execute([
                'event_id' => $eventId,
                'image_path' => $imagePath,
                'storage_path' => $storagePath
            ]);
        }

        // Insérer les images secondaires
        if (!empty($secondaryImages)) {
            $stmt = $db->prepare("
                INSERT INTO event_images (event_id, image_path, storage_path, is_main)
                VALUES (:event_id, :image_path, :storage_path, 0)
            ");

            foreach ($secondaryImages as $image) {
                // Si l'image est un tableau (nouvel upload)
                if (is_array($image)) {
                    $imagePath = $image['public_url'];
                    $storagePath = $image['storage_path'];
                } else {
                    // Si c'est juste un chemin (ancien format)
                    $imagePath = $image;
                    $storagePath = '/uploads/events/' . basename($image);
                }
                
                $stmt->execute([
                    'event_id' => $eventId,
                    'image_path' => $imagePath,
                    'storage_path' => $storagePath
                ]);
            }
        }

        return true;
    } catch (PDOException $e) {
        if (isset($_GET['debug'])) {
            echo "Erreur lors de l'enregistrement des images : " . $e->getMessage();
        }
        return false;
    }
}

/**
 * Récupère l'image principale d'un événement
 * @param PDO $db Connexion à la base de données
 * @param int $eventId ID de l'événement
 * @return string|null Chemin de l'image principale ou null
 */
function getEventMainImage($db, $eventId) {
    try {
        $stmt = $db->prepare("
            SELECT image_path, storage_path
            FROM event_images
            WHERE event_id = :event_id AND is_main = 1
            LIMIT 1
        ");
        $stmt->execute(['event_id' => $eventId]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['image_path'];
        }
        
        return null;
    } catch (PDOException $e) {
        if (isset($_GET['debug'])) {
            echo "Erreur lors de la récupération de l'image principale : " . $e->getMessage();
        }
        return null;
    }
}

/**
 * Récupère les images secondaires d'un événement
 * @param PDO $db Connexion à la base de données
 * @param int $eventId ID de l'événement
 * @return array
 */
function getEventSecondaryImages($db, $eventId) {
    try {
        $stmt = $db->prepare("
            SELECT image_path, storage_path
            FROM event_images
            WHERE event_id = :event_id AND is_main = 0
            ORDER BY id ASC
        ");
        $stmt->execute(['event_id' => $eventId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        if (isset($_GET['debug'])) {
            echo "Erreur lors de la récupération des images secondaires : " . $e->getMessage();
        }
        return [];
    }
}

/**
 * Gère l'upload d'un logo d'organisateur
 * @param array $file Fichier uploadé ($_FILES['name'])
 * @param int $organizerId ID de l'organisateur
 * @param PDO $db Connexion à la base de données
 * @return bool
 */
function handleOrganizerLogoUpload($file, $organizerId, $db) {
    try {
        // Upload le logo
        $result = handleImageUpload($file, 'organizer');
        if (!$result) {
            return false;
        }

        // Mettre à jour la base de données
        $stmt = $db->prepare("
            UPDATE organizer_profiles 
            SET logo_path = :logo_path,
                storage_path = :storage_path
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $organizerId,
            'logo_path' => $result['public_url'],
            'storage_path' => $result['storage_path']
        ]);
    } catch (Exception $e) {
        if (isset($_GET['debug'])) {
            echo "Erreur lors de l'upload du logo : " . $e->getMessage();
        }
        return false;
    }
}
