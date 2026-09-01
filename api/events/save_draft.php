<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/classes/Storage.php';

// Configuration des erreurs et logs
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Définir le chemin du fichier de log
$logFile = __DIR__ . '/save_draft.log';

// Fonction de log unifiée
function customLog($message, $isError = false) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp]" . ($isError ? " ERROR: " : " INFO: ") . $message . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Nettoyer le fichier de log au début
file_put_contents($logFile, '');

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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    customLog(" Début de l'enregistrement du brouillon");
    customLog("POST data: " . print_r($_POST, true));
    customLog("FILES data: " . print_r($_FILES, true));

    // Démarrer la transaction
    $pdo = getConnection();
    $pdo->beginTransaction();
    
    // Initialiser la réponse
    $response = [
        'success' => true,
        'draftId' => null,
        'mainImage' => null,
        'secondaryImages' => [],
        'message' => []
    ];

    // Récupérer ou créer un brouillon
    $draftId = null;
    if (isset($_POST['draftId']) && !empty($_POST['draftId'])) {
        $draftId = $_POST['draftId'];
        $response['draftId'] = $draftId;
        customLog(" Utilisation du brouillon existant: " . $draftId);
    } else {
        // Créer un nouveau brouillon
        $stmt = $pdo->prepare("
            INSERT INTO draft_events (user_id, created_at, updated_at)
            VALUES (:user_id, NOW(), NOW())
        ");
        
        if (!$stmt->execute(['user_id' => $_SESSION['user_id']])) {
            throw new Exception("Erreur lors de la création du brouillon");
        }
        
        $draftId = $pdo->lastInsertId();
        $response['draftId'] = $draftId;
        customLog(" Nouveau brouillon créé avec ID: " . $draftId);
    }

    // Traiter les catégories du formulaire
    $categoryIds = [];
    if (!empty($_POST['categories']) && is_array($_POST['categories'])) {
        $categoryIds = array_map('intval', $_POST['categories']);
    }

    // Helper pour parser les dates du formulaire jj/mm/aaaa
    function parseFormDate($value) {
        if (empty($value)) return null;
        $dt = DateTime::createFromFormat('d/m/Y', $value);
        return $dt ? $dt->format('Y-m-d') : null;
    }

    // Helper pour parser les heures du formulaire HH:MM
    function parseFormTime($value) {
        if (empty($value)) return null;
        $dt = DateTime::createFromFormat('H:i', $value);
        if (!$dt) {
            $dt = DateTime::createFromFormat('H:i:s', $value);
        }
        return $dt ? $dt->format('H:i:s') : null;
    }

    // Traiter les informations principales de l'événement
    $eventFields = [
        'title' => $_POST['title'] ?? null,
        'description' => $_POST['description'] ?? null,
        'date' => parseFormDate($_POST['date'] ?? null),
        'registration_opens' => parseFormTime($_POST['registrationOpens'] ?? null),
        'registration_closes' => parseFormTime($_POST['registrationCloses'] ?? null),
        'location' => $_POST['location_name'] ?? null,
        'venue' => $_POST['address'] ?? null,
        'coordinates' => $_POST['coordinates'] ?? null,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Log des champs de l'événement
    customLog("📝 Champs de l'événement:");
    foreach ($eventFields as $field => $value) {
        customLog("$field: $value");
    }

    // L'organisateur est désormais géré dans Mon compte > Organisateur
    customLog("ℹ️ Aucun organisateur reçu depuis le formulaire événement");

    $updateFields = [];
    $updateParams = [];
    foreach ($eventFields as $field => $value) {
        if ($value !== null) {
            $updateFields[] = "$field = ?";
            $updateParams[] = $value;
        }
    }

    if (!empty($updateFields)) {
        // Ajouter event_id à la fin des paramètres
        $updateParams[] = $draftId;

        $sql = "UPDATE draft_events SET " . implode(', ', $updateFields) . " WHERE id = ?";
        customLog("Requête SQL: " . $sql);
        customLog("Paramètres: " . print_r($updateParams, true));

        $stmt = $pdo->prepare($sql);
        
        if (!$stmt->execute($updateParams)) {
            $error = $stmt->errorInfo();
            customLog("Erreur SQL: " . print_r($error, true), true);
            throw new Exception("Erreur lors de la mise à jour des informations de l'événement: " . $error[2]);
        }
        customLog("Informations principales de l'événement mises à jour avec succès");
    } else {
        customLog("Aucun champ à mettre à jour", true);
    }

    // Sauvegarder les tags d'activité
    if (!empty($categoryIds)) {
        try {
            $pdo->prepare("DELETE FROM draft_event_category_links WHERE draft_event_id = ?")->execute([$draftId]);
            $insertStmt = $pdo->prepare("INSERT INTO draft_event_category_links (draft_event_id, category_id) VALUES (?, ?)");
            foreach ($categoryIds as $categoryId) {
                $insertStmt->execute([$draftId, $categoryId]);
            }
            customLog("✅ Tags d'activité enregistrés : " . implode(', ', $categoryIds));
        } catch (Exception $e) {
            customLog("❌ Erreur lors de l'enregistrement des tags : " . $e->getMessage(), true);
            throw new Exception("Erreur lors de l'enregistrement des tags d'activité");
        }
    } else {
        $pdo->prepare("DELETE FROM draft_event_category_links WHERE draft_event_id = ?")->execute([$draftId]);
    }

    // Fonction pour obtenir l'URL complète
    function getFullUrl($path) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        return $protocol . $domain . '/' . ltrim($path, '/');
    }

    // Gérer l'image principale
    if (isset($_FILES['mainImage']) && $_FILES['mainImage']['error'] === UPLOAD_ERR_OK) {
        try {
            // Supprimer l'ancienne image principale
            $stmt = $pdo->prepare("DELETE FROM draft_images WHERE event_id = :event_id AND is_main = 1");
            $stmt->execute(['event_id' => $draftId]);

            $uploadResult = Storage::saveUploadedFile($_FILES['mainImage'], 'events');
            if ($uploadResult && is_array($uploadResult)) {
                $publicUrl = getFullUrl($uploadResult['public_url']);
                $stmt = $pdo->prepare("
                    INSERT INTO draft_images (
                        event_id, image_path, storage_path, is_main, storage_type
                    ) VALUES (
                        :event_id, :image_path, :storage_path, 1, 'local'
                    )
                ");
                
                $imageParams = [
                    'event_id' => $draftId,
                    'image_path' => $publicUrl,
                    'storage_path' => $uploadResult['storage_path']
                ];
                
                customLog("Insertion image principale - URL: " . $publicUrl);
                
                if (!$stmt->execute($imageParams)) {
                    throw new Exception("Erreur lors de l'insertion de l'image principale");
                }
                
                $response['mainImage'] = [
                    'id' => $pdo->lastInsertId(),
                    'path' => $publicUrl,
                    'storage_path' => $uploadResult['storage_path']
                ];
                $response['message'][] = "Image principale enregistrée";
            }
        } catch (Exception $e) {
            customLog(" Erreur lors de l'enregistrement de l'image principale: " . $e->getMessage(), true);
            throw $e;
        }
    }

    // Gérer les images secondaires
    if (isset($_FILES['secondaryImages']) && is_array($_FILES['secondaryImages']['tmp_name'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM draft_images WHERE event_id = :event_id AND is_main = 0");
            $stmt->execute(['event_id' => $draftId]);

            foreach ($_FILES['secondaryImages']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['secondaryImages']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['secondaryImages']['name'][$key],
                        'type' => $_FILES['secondaryImages']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['secondaryImages']['error'][$key],
                        'size' => $_FILES['secondaryImages']['size'][$key]
                    ];

                    $uploadResult = Storage::saveUploadedFile($file, 'events');
                    if ($uploadResult && is_array($uploadResult)) {
                        $publicUrl = getFullUrl($uploadResult['public_url']);
                        $stmt = $pdo->prepare("
                            INSERT INTO draft_images (
                                event_id, image_path, storage_path, is_main, storage_type
                            ) VALUES (
                                :event_id, :image_path, :storage_path, 0, 'local'
                            )
                        ");
                        
                        if (!$stmt->execute([
                            'event_id' => $draftId,
                            'image_path' => $publicUrl,
                            'storage_path' => $uploadResult['storage_path']
                        ])) {
                            throw new Exception("Erreur lors de l'insertion d'une image secondaire");
                        }

                        $response['secondaryImages'][] = [
                            'id' => $pdo->lastInsertId(),
                            'path' => $publicUrl,
                            'storage_path' => $uploadResult['storage_path']
                        ];
                    }
                }
            }
            $response['message'][] = count($response['secondaryImages']) . " images secondaires enregistrées";
        } catch (Exception $e) {
            customLog(" Erreur lors du traitement des images secondaires: " . $e->getMessage(), true);
            throw $e;
        }
    }

    // Traiter les contacts
    if (isset($_POST['contacts'])) {
        try {
            $contacts = $_POST['contacts'];
            if (is_string($contacts)) {
                $contacts = json_decode($contacts, true);
            }

            if (!empty($contacts)) {
                // Supprimer les anciens contacts
                $stmt = $pdo->prepare("DELETE FROM draft_contacts WHERE event_id = :event_id");
                $stmt->execute(['event_id' => $draftId]);

                foreach ($contacts as $contact) {
                    $stmt = $pdo->prepare("
                        INSERT INTO draft_contacts (
                            event_id, name, email, phone
                        ) VALUES (
                            :event_id, :name, :email, :phone
                        )
                    ");
                    
                    if (!$stmt->execute([
                        'event_id' => $draftId,
                        'name' => $contact['name'],
                        'email' => $contact['email'] ?? null,
                        'phone' => $contact['phone'] ?? null
                    ])) {
                        throw new Exception("Erreur lors de l'insertion d'un contact");
                    }
                }
                $response['message'][] = count($contacts) . " contacts enregistrés";
            }
        } catch (Exception $e) {
            customLog(" Erreur lors du traitement des contacts: " . $e->getMessage(), true);
            throw $e;
        }
    }

    // Traiter les parcours
    if (isset($_POST['routes'])) {
        try {
            $routes = is_string($_POST['routes']) ? json_decode($_POST['routes'], true) : $_POST['routes'];
            
            if (!empty($routes)) {
                $pdo->prepare("DELETE FROM draft_parcours WHERE event_id = ?")->execute([$draftId]);

                $stmt = $pdo->prepare("
                    INSERT INTO draft_parcours (
                        event_id, name, distance, elevation_gain, description,
                        gpx_file, gpx_downloadable, price
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($routes as $index => $route) {
                    if (!empty($route['name'])) {
                        // Traiter le fichier GPX s'il existe
                        $gpxPath = null;
                        if (isset($_FILES['routes']['tmp_name'][$index]['gpx']) && 
                            $_FILES['routes']['error'][$index]['gpx'] === UPLOAD_ERR_OK) {
                            
                            $gpxFile = [
                                'name' => $_FILES['routes']['name'][$index]['gpx'],
                                'type' => $_FILES['routes']['type'][$index]['gpx'],
                                'tmp_name' => $_FILES['routes']['tmp_name'][$index]['gpx'],
                                'error' => $_FILES['routes']['error'][$index]['gpx'],
                                'size' => $_FILES['routes']['size'][$index]['gpx']
                            ];
                            
                            $uploadResult = Storage::saveUploadedFile($gpxFile, 'gpx');
                            if ($uploadResult && is_array($uploadResult)) {
                                $gpxPath = $uploadResult['public_url'];
                                customLog("Fichier GPX uploadé pour le parcours " . $route['name'] . ": " . $gpxPath);
                            }
                        }

                        $stmt->execute([
                            $draftId,
                            $route['name'],
                            $route['distance'] ?? null,
                            $route['elevation'] ?? null,
                            $route['description'] ?? '',
                            $gpxPath,
                            isset($route['gpx_downloadable']) ? 1 : 0,
                            $route['price'] ?? 0.00
                        ]);
                    }
                }
                $response['message'][] = count($routes) . " parcours enregistrés";
            }
        } catch (Exception $e) {
            customLog("Erreur parcours: " . $e->getMessage(), true);
            throw $e;
        }
    }

    // Valider la transaction
    $pdo->commit();
    customLog(" Transaction validée avec succès");

    // Envoyer la réponse
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    customLog(" Exception globale: " . $e->getMessage(), true);
    
    require_once __DIR__ . '/../../logs/error.log.php';
    logError('api/events/save_draft.php', 'Erreur enregistrement brouillon', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
