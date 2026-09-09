<?php
// Log de test initial
error_log("=== Début de publish.php ===");
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME']);
error_log("DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT']);

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Capturer la sortie
ob_start();

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';
    echo "✅ init.php inclus\n";
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
    echo "✅ auth_check.php inclus\n";
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Utils/image_utils.php';
    echo "✅ image_utils.php inclus\n";
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Utils/gpx_utils.php';
    echo "✅ gpx_utils.php inclus\n";
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/mailer.php';
    echo "✅ mailer.php inclus\n";
} catch (Exception $e) {
    $output = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'debug' => [
            'output' => $output,
            'trace' => $e->getTraceAsString()
        ]
    ]);
    exit;
}

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Log de début
error_log("=== Début de la publication ===");
error_log("POST: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

function formatError($e) {
    return [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
}

try {
    // Vérifier la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log("❌ Méthode HTTP non autorisée");
        echo json_encode(['error' => 'Méthode HTTP non autorisée', 'debug' => [
            'session' => $_SESSION,
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]]);
        exit;
    }

    // Vérifier l'authentification
    if (!isset($_SESSION['user_id'])) {
        error_log("❌ Utilisateur non authentifié");
        echo json_encode(['error' => 'Utilisateur non authentifié', 'debug' => [
            'session' => $_SESSION,
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]]);
        exit;
    }

    $draftId = $_POST['draftId'] ?? null;
    if (!$draftId) {
        error_log("❌ ID du brouillon manquant");
        echo json_encode(['error' => 'ID du brouillon manquant', 'debug' => [
            'post' => $_POST
        ]]);
        exit;
    }

    error_log("Recherche du brouillon ID: $draftId pour l'utilisateur: {$_SESSION['user_id']}");

    // Récupérer le brouillon
    $stmt = $pdo->prepare("
        SELECT * FROM draft_events 
        WHERE id = :id AND user_id = :user_id AND status = 'draft'
    ");
    
    $params = [
        'id' => $draftId,
        'user_id' => $_SESSION['user_id']
    ];
    error_log(" Paramètres: " . print_r($params, true));

    $stmt->execute($params);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log(" Brouillon trouvé: " . ($draft ? 'oui' : 'non'));
    if ($draft) {
        error_log(" Détails du brouillon: " . print_r($draft, true));
    }

    if (!$draft) {
        error_log("❌ Brouillon non trouvé");
        echo json_encode(['error' => 'Brouillon non trouvé', 'debug' => [
            'draft_id' => $draftId,
            'user_id' => $_SESSION['user_id']
        ]]);
        exit;
    }

    error_log(" Recherche du brouillon - ID: $draftId, User: {$_SESSION['user_id']}");

    // Début de la transaction
    error_log(" Début de la transaction");
    $pdo->beginTransaction();

    try {
        // Copier les données du brouillon vers la table events
        error_log(" Copie des données vers events");
        $stmt = $pdo->prepare("
            INSERT INTO events (
                user_id, title, description, date, start_time, end_time,
                location, venue, coordinates, category_id, status,
                organisation, has_gpx, gpx_path, gpx_downloadable
            ) SELECT 
                user_id, title, description, date, start_time, end_time,
                location, venue, coordinates, category, 'pending',
                organisation, has_gpx, gpx_path, gpx_downloadable
            FROM draft_events
            WHERE id = ?
        ");
        
        error_log(" SQL Insert events: " . $stmt->queryString);
        $stmt->execute([$draftId]);
        $eventId = $pdo->lastInsertId();
        error_log(" Événement créé avec ID: " . $eventId);

        // Copier les images du brouillon vers l'événement
        error_log(" Copie des images");
        $stmt = $pdo->prepare("SELECT * FROM draft_images WHERE event_id = ?");
        $stmt->execute([$draftId]);
        $draftImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log(" Nombre d'images trouvées: " . count($draftImages));

        // Séparer les images principales et secondaires
        $mainImages = array_filter($draftImages, function($img) { return $img['is_main'] == 1; });
        $secondaryImages = array_filter($draftImages, function($img) { return $img['is_main'] == 0; });

        // Ne prendre que la première image principale si elle existe
        if (!empty($mainImages)) {
            $mainImage = reset($mainImages);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO event_images (
                        event_id,
                        image_path,
                        storage_path,
                        is_main,
                        storage_type
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $eventId,
                    $mainImage['image_path'],
                    $mainImage['storage_path'],
                    1,
                    $mainImage['storage_type'] ?? 'local'
                ]);
                
                error_log(" Image principale copiée avec succès");
            } catch (PDOException $e) {
                error_log("❌ Erreur lors de la copie de l'image principale: " . $e->getMessage());
                throw new Exception("Erreur lors de la copie de l'image principale: " . $e->getMessage());
            }
        }

        // Copier toutes les images secondaires
        foreach ($secondaryImages as $image) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO event_images (
                        event_id,
                        image_path,
                        storage_path,
                        is_main,
                        storage_type
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $eventId,
                    $image['image_path'],
                    $image['storage_path'],
                    0,
                    $image['storage_type'] ?? 'local'
                ]);
                
                error_log(" Image secondaire copiée avec succès");
            } catch (PDOException $e) {
                error_log("❌ Erreur lors de la copie d'une image secondaire: " . $e->getMessage());
                throw new Exception("Erreur lors de la copie des images secondaires: " . $e->getMessage());
            }
        }

        // Copier les contacts du brouillon vers l'événement
        $stmt = $pdo->prepare("
            SELECT * FROM  draft_contacts 
            WHERE event_id = ?
        ");
        $stmt->execute([$draftId]);
        $draftContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($draftContacts as $contact) {
            $stmt = $pdo->prepare("
                INSERT INTO event_contacts (
                    event_id,
                    name,
                    email,
                    phone
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $eventId,
                $contact['name'],
                $contact['email'],
                $contact['phone']
            ]);
        }

        // Copier les parcours du brouillon vers l'événement
        $stmt = $pdo->prepare("
            SELECT * FROM draft_parcours 
            WHERE event_id = ?
        ");
        $stmt->execute([$draftId]);
        $draftParcours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($draftParcours as $parcours) {
            $stmt = $pdo->prepare("
                INSERT INTO event_parcours (
                    event_id,
                    name,
                    distance,
                    elevation_gain,
                    description,
                    gpx_path,
                    gpx_downloadable,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $eventId,
                $parcours['name'],
                $parcours['distance'],
                $parcours['elevation_gain'],
                $parcours['description'],
                $parcours['gpx_path'],
                $parcours['gpx_downloadable']
            ]);
        }

        // Mettre à jour le statut du brouillon
        $stmt = $pdo->prepare("
            UPDATE draft_events 
            SET status = 'published'
            WHERE id = ?
        ");
        $stmt->execute([$draftId]);

        // Valider la transaction
        $pdo->commit();

        // Envoyer l'email de confirmation
        try {
            $mailer = new Mailer();
            $mailer->sendEventPublishedEmail(
                $_SESSION['email'],
                [
                    'eventId' => $eventId,
                    'title' => $draft['title'],
                    'date' => $draft['date']
                ]
            );
        } catch (Exception $e) {
            error_log("❌ Erreur lors de l'envoi de l'email: " . $e->getMessage());
            // Ne pas bloquer la publication si l'email échoue
        }

        // Répondre avec succès
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Événement publié avec succès',
            'eventId' => $eventId
        ]);

    } catch (Exception $e) {
        error_log("❌ Erreur lors de la publication: " . $e->getMessage());
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la publication: ' . $e->getMessage(),
            'error' => formatError($e)
        ]);
    }

} catch (Exception $e) {
    error_log("❌ Erreur globale: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'error' => formatError($e)
    ]);
}
