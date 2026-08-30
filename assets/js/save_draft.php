<?php
session_start();

// Configuration des erreurs et logs
ini_set('display_errors', 1);
error_reporting(E_ALL);

function customLog($message, $isError = false) {
    $logFile = __DIR__ . '/save_draft.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] " . ($isError ? "ERROR: " : "INFO: ") . $message . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Gestionnaire d'erreurs
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    customLog("PHP Error [$errno]: $errstr in $errfile:$errline", true);
    return true;
});

// Gestionnaire d'exceptions
set_exception_handler(function($e) {
    customLog("Exception non capturée: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString(), true);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
});

try {
    customLog("🚀 Début de l'enregistrement du brouillon");
    customLog("POST data: " . print_r($_POST, true));
    customLog("FILES data: " . print_r($_FILES, true));
    
    // Vérification de la session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Session expirée. Veuillez vous reconnecter.');
    }

    // Chargement des dépendances
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/gpx_utils.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/image_utils.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Models/EventCategory.php';

    $userId = $_SESSION['user_id'];
    $pdo = getConnection();
    $pdo->beginTransaction();

    // Traitement des fichiers
    $mainImagePath = null;
    $mainImageStorage = null;
    $secondaryImagePaths = [];

    // Traiter l'image principale si fournie
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = handleImageUpload($_FILES['main_image'], 'events');
        if ($uploadResult && is_array($uploadResult)) {
            $mainImagePath = $uploadResult['public_url'] ?? null;
            $mainImageStorage = $uploadResult['storage_path'] ?? null;
        }
    }

    // Traiter les images secondaires si fournies
    if (isset($_FILES['secondary_images'])) {
        foreach ($_FILES['secondary_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['secondary_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['secondary_images']['name'][$key],
                    'type' => $_FILES['secondary_images']['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $_FILES['secondary_images']['error'][$key],
                    'size' => $_FILES['secondary_images']['size'][$key]
                ];
                
                $uploadResult = handleImageUpload($file, 'events');
                if ($uploadResult && is_array($uploadResult)) {
                    $secondaryImagePaths[] = [
                        'image_path' => $uploadResult['public_url'] ?? '',
                        'storage_path' => $uploadResult['storage_path'] ?? ''
                    ];
                }
            }
        }
    }

    // Validation des champs requis
    $requiredFields = [
        'title' => 'Le titre est requis',
        'description' => 'La description est requise',
        'date' => 'La date est requise',
        'startTime' => "L'heure de début est requise",
        'location_name' => 'Le lieu est requis',
        'coordinates' => 'Les coordonnées sont requises',
        'category' => 'La catégorie est requise'
    ];

    $errors = [];
    foreach ($requiredFields as $field => $message) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $errors[] = $message;
            customLog("Champ manquant : $field, Valeur : " . (isset($_POST[$field]) ? $_POST[$field] : 'non défini'));
        }
    }

    // Validation spécifique pour les coordonnées
    if (isset($_POST['coordinates']) && !empty($_POST['coordinates'])) {
        $coords = explode(',', $_POST['coordinates']);
        if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
            $errors[] = "Format des coordonnées invalide";
            customLog("Coordonnées invalides : " . $_POST['coordinates']);
        }
    }

    if (!empty($errors)) {
        throw new Exception("Validation échouée : " . implode(", ", $errors));
    }

    // Log des données reçues
    customLog("Données POST reçues : " . print_r($_POST, true));
    customLog("Fichiers reçus : " . print_r($_FILES, true));

    // Préparation des paramètres avec valeurs par défaut
    $params = [
        ':user_id' => $userId,
        ':title' => $_POST['title'],
        ':description' => $_POST['description'],
        ':date' => $_POST['date'],
        ':start_time' => $_POST['startTime'],
        ':end_time' => $_POST['endTime'] ?? null,
        ':location' => $_POST['location_name'],
        ':venue' => $_POST['address'] ?? null,
        ':coordinates' => $_POST['coordinates'],
        ':category' => $_POST['category'],
        ':organisation' => $_POST['organizerId'] ?? null,
        ':has_gpx' => isset($_FILES['gpx']) && $_FILES['gpx']['error'] === UPLOAD_ERR_OK ? 1 : 0,
        ':gpx_path' => $gpxPath ?? '',
        ':gpx_downloadable' => isset($_POST['gpx_downloadable']) ? 1 : 0
    ];

    // Log des paramètres préparés
    customLog("Paramètres préparés pour la requête SQL : " . print_r($params, true));

    // Insérer ou mettre à jour le brouillon
    if (isset($_POST['draft_id']) && !empty($_POST['draft_id'])) {
        // Mise à jour d'un brouillon existant
        $stmt = $pdo->prepare("
            UPDATE draft_events 
            SET title = :title, description = :description, 
                date = :date, start_time = :start_time, end_time = :end_time,
                location = :location, venue = :venue, coordinates = :coordinates,
                category = :category, organisation = :organisation,
                has_gpx = :has_gpx, gpx_path = :gpx_path, gpx_downloadable = :gpx_downloadable
            WHERE id = :draft_id AND user_id = :user_id
        ");
        $params[':draft_id'] = $_POST['draft_id'];
        $stmt->execute($params);
        $draftId = $_POST['draft_id'];
    } else {
        // Création d'un nouveau brouillon
        $stmt = $pdo->prepare("
            INSERT INTO draft_events (
                user_id, title, description, date, start_time, end_time,
                location, venue, coordinates, category, organisation,
                has_gpx, gpx_path, gpx_downloadable, status
            ) VALUES (
                :user_id, :title, :description, :date, :start_time, :end_time,
                :location, :venue, :coordinates, :category, :organisation,
                :has_gpx, :gpx_path, :gpx_downloadable, 'draft'
            )
        ");
        $stmt->execute($params);
        $draftId = $pdo->lastInsertId();
    }

    // Gérer l'image principale
    if ($mainImagePath) {
        // Supprimer l'ancienne image principale si elle existe
        $stmt = $pdo->prepare("
            DELETE FROM draft_images 
            WHERE event_id = ? AND is_main = 1
        ");
        $stmt->execute([$draftId]);

        // Insérer la nouvelle image principale
        $stmt = $pdo->prepare("
            INSERT INTO draft_images (
                event_id, 
                image_path, 
                storage_path, 
                is_main,
                storage_type
            ) VALUES (?, ?, ?, 1, 'local')
        ");
        $stmt->execute([
            $draftId,
            $mainImagePath,
            $mainImageStorage
        ]);
    }

    // Gérer les contacts
    if (isset($_POST['contacts'])) {
        try {
            customLog("Type de contacts reçu: " . gettype($_POST['contacts']));
            customLog("Contenu de contacts: " . print_r($_POST['contacts'], true));
            
            $contacts = null;
            if (is_string($_POST['contacts'])) {
                $contacts = json_decode($_POST['contacts'], true);
                customLog("Décodage JSON effectué");
            } elseif (is_array($_POST['contacts'])) {
                $contacts = $_POST['contacts'];
                customLog("Utilisation directe du tableau");
            }
            
            if ($contacts !== null && is_array($contacts)) {
                // Supprimer les anciens contacts
                $stmt = $pdo->prepare("DELETE FROM draft_contact WHERE event_id = :event_id");
                $stmt->execute(['event_id' => $draftId]);
                customLog("Anciens contacts supprimés");

                // Insérer les nouveaux contacts
                $stmt = $pdo->prepare("
                    INSERT INTO draft_contact (event_id, name, email, phone) 
                    VALUES (:event_id, :name, :email, :phone)
                ");

                foreach ($contacts as $contact) {
                    customLog("Traitement du contact: " . print_r($contact, true));
                    if (!empty($contact['name']) || !empty($contact['email']) || !empty($contact['phone'])) {
                        $stmt->execute([
                            'event_id' => $draftId,
                            'name' => $contact['name'] ?? '',
                            'email' => $contact['email'] ?? '',
                            'phone' => $contact['phone'] ?? ''
                        ]);
                        customLog("Contact ajouté avec succès");
                    }
                }
            } else {
                customLog("Format des contacts invalide après traitement", true);
            }
        } catch (Exception $e) {
            customLog("Exception lors du traitement des contacts: " . $e->getMessage() . "\n" . $e->getTraceAsString(), true);
            throw $e; // Relancer l'exception pour la gestion globale des erreurs
        }
    }

    // Gérer les images secondaires
    if (isset($_FILES['secondary_images'])) {
        // Supprimer les anciennes images secondaires
        $stmt = $pdo->prepare("DELETE FROM draft_images WHERE event_id = :event_id AND is_main = 0");
        $stmt->execute(['event_id' => $draftId]);

        foreach ($_FILES['secondary_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['secondary_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['secondary_images']['name'][$key],
                    'type' => $_FILES['secondary_images']['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $_FILES['secondary_images']['error'][$key],
                    'size' => $_FILES['secondary_images']['size'][$key]
                ];
                
                $uploadResult = handleImageUpload($file, 'events');
                if ($uploadResult && is_array($uploadResult)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO draft_images (
                            event_id, 
                            image_path, 
                            storage_path, 
                            is_main,
                            storage_type
                        ) VALUES (
                            :event_id,
                            :image_path,
                            :storage_path,
                            0,
                            'local'
                        )
                    ");
                    
                    $stmt->execute([
                        'event_id' => $draftId,
                        'image_path' => $uploadResult['public_url'],
                        'storage_path' => $uploadResult['storage_path']
                    ]);
                    
                    customLog("Image secondaire ajoutée pour l'événement $draftId: " . $uploadResult['public_url']);
                }
            }
        }
    }

    // Gérer les parcours
    if (isset($_POST['routes'])) {
        try {
            customLog("Type de routes reçu: " . gettype($_POST['routes']));
            customLog("Contenu brut des routes: " . print_r($_POST['routes'], true));
            
            $routes = null;
            if (is_string($_POST['routes'])) {
                $routes = json_decode($_POST['routes'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Erreur de décodage JSON des routes: " . json_last_error_msg());
                }
                customLog("Routes décodées avec succès");
            } elseif (is_array($_POST['routes'])) {
                $routes = $_POST['routes'];
                customLog("Utilisation directe du tableau de routes");
            }
            
            if ($routes !== null && is_array($routes)) {
                // Supprimer les anciens parcours
                $stmt = $pdo->prepare("DELETE FROM draft_parcours WHERE event_id = :event_id");
                $stmt->execute(['event_id' => $draftId]);
                customLog("Anciens parcours supprimés");

                // Insérer les nouveaux parcours
                $stmt = $pdo->prepare("
                    INSERT INTO draft_parcours (
                        event_id,
                        name,
                        distance,
                        elevation,
                        gpx_path,
                        gpx_storage_path,
                        gpx_downloadable
                    ) VALUES (
                        :event_id,
                        :name,
                        :distance,
                        :elevation,
                        :gpx_path,
                        :gpx_storage_path,
                        :gpx_downloadable
                    )
                ");

                foreach ($routes as $index => $route) {
                    customLog("Traitement du parcours " . ($index + 1));
                    
                    $gpxPath = '';
                    $gpxStoragePath = '';
                    
                    // Traiter le fichier GPX s'il existe
                    if (isset($_FILES["route_gpx_$index"]) && $_FILES["route_gpx_$index"]['error'] === UPLOAD_ERR_OK) {
                        customLog("Traitement du fichier GPX pour le parcours " . ($index + 1));
                        $gpxResult = handleGpxUpload($_FILES["route_gpx_$index"], 'events');
                        if ($gpxResult && is_array($gpxResult)) {
                            $gpxPath = $gpxResult['public_url'];
                            $gpxStoragePath = $gpxResult['storage_path'];
                            customLog("Fichier GPX traité avec succès");
                        }
                    }

                    $stmt->execute([
                        'event_id' => $draftId,
                        'name' => $route['name'],
                        'distance' => $route['distance'] ?? null,
                        'elevation' => $route['elevation'] ?? null,
                        'gpx_path' => $gpxPath,
                        'gpx_storage_path' => $gpxStoragePath,
                        'gpx_downloadable' => $route['gpx_downloadable'] ? 1 : 0
                    ]);
                    
                    customLog("Parcours " . ($index + 1) . " ajouté avec succès");
                }
            } else {
                customLog("Format des routes invalide après traitement", true);
            }
        } catch (Exception $e) {
            customLog("Exception lors du traitement des routes: " . $e->getMessage() . "\n" . $e->getTraceAsString(), true);
            throw $e;
        }
    }

    // Valider la transaction
    $pdo->commit();
    customLog("✅ Transaction validée");
    customLog("✅ Brouillon enregistré avec succès (ID: $draftId)");

    // Envoyer la réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Brouillon enregistré avec succès',
        'draft_id' => $draftId
    ]);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    customLog("❌ Erreur: " . $e->getMessage(), true);
    customLog("Trace: " . $e->getTraceAsString(), true);
    customLog("Transaction annulée");
    throw $e;
}
