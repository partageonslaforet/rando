<?php

require_once __DIR__ . '/../config/database.php';

try {
    // Connexion à la base de données
    $db = getConnection();
    
    // Démarrer une transaction
    $db->beginTransaction();
    
    try {
        // Tableau associatif des événements et leurs images
        $eventImages = [
            // ID de l'événement => nom du fichier image
            1 => 'DSC_0001.jpg',  // Exemple : à remplacer par vos vrais noms de fichiers
            2 => 'DSC_0002.jpg',
            // Ajoutez d'autres événements et leurs images ici
        ];

        // Préparer la requête d'insertion
        $stmt = $db->prepare("
            INSERT INTO event_images (
                event_id, 
                image_path, 
                storage_path, 
                is_main
            ) VALUES (
                :event_id,
                :image_path,
                :storage_path,
                1
            )
        ");

        // Insérer chaque image
        foreach ($eventImages as $eventId => $filename) {
            $storagePath = '/uploads/events/' . $filename;
            $imagePath = 'https://rando.partageonslaforet.be/uploads/events/' . $filename;

            echo "Liaison de l'image pour l'événement #$eventId:\n";
            echo "Fichier: $filename\n";
            echo "Chemin de stockage: $storagePath\n";
            echo "URL publique: $imagePath\n\n";

            $stmt->execute([
                'event_id' => $eventId,
                'image_path' => $imagePath,
                'storage_path' => $storagePath
            ]);
        }

        // Valider les changements
        $db->commit();
        echo "\nLiaison des images terminée avec succès !\n";

    } catch (Exception $e) {
        // En cas d'erreur, annuler les changements
        $db->rollBack();
        echo "Erreur lors de la liaison des images : " . $e->getMessage() . "\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "Erreur de connexion à la base de données : " . $e->getMessage() . "\n";
    exit(1);
}
