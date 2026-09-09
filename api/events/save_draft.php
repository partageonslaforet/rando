<?php
/**
 * Sauvegarde d'un brouillon (création ou mise à jour).
 * Gère le contenu, les images, les parcours GPX et le storage associé.
 *
 * Utilisé par : public/assets/js/event-images.js, public/assets/js/event-validation.js
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/Services/Storage.php';

// Configuration des erreurs et logs
ini_set('display_errors', 0);
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

// Gestionnaire d'erreurs fatales en fin d'exécution
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(500);
        $json = json_encode(['success' => false, 'message' => 'Fatal: ' . $err['message']], JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json !== false ? $json : '{"success":false,"message":"Fatal"}';
    }
});

// Gestionnaire d'erreurs
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    customLog("PHP Error [$errno]: $errstr in $errfile:$errline", true);
    return true;
});

// Gestionnaire d'exceptions
set_exception_handler(function($e) {
    customLog("Exception non capturée: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString(), true);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    $json = json_encode(['success' => false, 'message' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    echo $json !== false ? $json : '{"success":false,"message":"Erreur d\'encodage"}';
    exit;
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const MAX_SECONDARY_IMAGES = 3;

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
        'routes' => [],
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

    // Suppression d'images si demandée
    if (!empty($_POST['deleteMainImage']) && $draftId) {
        $stmt = $pdo->prepare("DELETE FROM draft_images WHERE event_id = ? AND is_main = 1");
        $stmt->execute([$draftId]);
        $pdo->commit();
        header('Content-Type: application/json');
        $json = json_encode(['success' => true, 'draftId' => $draftId, 'message' => 'Image principale supprimée'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        echo $json !== false ? $json : '{"success":false,"message":"Erreur"}';
        exit;
    }

    if (!empty($_POST['deleteImage']) && $draftId) {
        $imageId = (int)$_POST['deleteImage'];
        $stmt = $pdo->prepare("DELETE FROM draft_images WHERE id = ? AND event_id = ?");
        $stmt->execute([$imageId, $draftId]);
        $pdo->commit();
        header('Content-Type: application/json');
        $json = json_encode(['success' => true, 'draftId' => $draftId, 'message' => 'Image supprimée'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        echo $json !== false ? $json : '{"success":false,"message":"Erreur"}';
        exit;
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

    // Helper pour extraire la ville depuis une adresse textuelle
    function extractCityFromAddress($address) {
        if (empty($address)) return null;
        if (preg_match('/\b\d{4,5}\s+(.+)$/', trim($address), $m)) {
            return trim($m[1]);
        }
        $parts = preg_split('/[,\s]+/', trim($address));
        return $parts ? trim(end($parts)) : null;
    }

    // Traiter les informations principales de l'événement
    // Fallbacks: si les champs génériques sont vides, reprendre ceux de la section "Adresse du jour"
    $locationName = $_POST['location_name'] ?? null;
    $addressGeneric = $_POST['address'] ?? null;
    $coordsGeneric = $_POST['coordinates'] ?? null;

    $meetingName = $_POST['meeting_name'] ?? null;
    $meetingAddress = $_POST['meeting_address'] ?? null;
    $meetingCity = $_POST['meeting_city'] ?? null;

    // Fallback : extraire la ville du texte de l'adresse si le champ n'est pas renseigné
    if (empty($meetingCity) && !empty($meetingAddress)) {
        $meetingCity = extractCityFromAddress($meetingAddress);
    }

    $meetingCoords = $_POST['meeting_coordinates'] ?? null;

    $eventFields = [
        'title' => $_POST['title'] ?? null,
        'description' => $_POST['description'] ?? null,
        'date' => parseFormDate($_POST['date'] ?? null),
        'registration_opens' => parseFormTime($_POST['registrationOpens'] ?? null),
        'registration_closes' => parseFormTime($_POST['registrationCloses'] ?? null),
        // location/venue/coordinates alimentés avec fallback depuis meeting_*
        'location' => $locationName !== null && $locationName !== '' ? $locationName : ($meetingName ?: null),
        'venue' => $addressGeneric !== null && $addressGeneric !== '' ? $addressGeneric : ($meetingAddress ?: null),
        'coordinates' => $coordsGeneric !== null && $coordsGeneric !== '' ? $coordsGeneric : ($meetingCoords ?: null),
        // on sauvegarde également les champs meeting_* explicitement
        'meeting_name' => $meetingName,
        'meeting_address' => $meetingAddress,
        'meeting_city' => $meetingCity,
        'meeting_coordinates' => $meetingCoords,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Gestion de l'organisateur (sélection existante ou nouveau profil)
    $saveReason = $_POST['saveReason'] ?? 'auto';
    if (isset($_POST['organizerId']) && $_POST['organizerId'] !== '' && $_POST['organizerId'] !== 'new') {
        $eventFields['organizer_id'] = (int)$_POST['organizerId'];
        $eventFields['organisation'] = null;
    } elseif (!empty($_POST['organizerName'])) {
        if ($saveReason === 'input') {
            // Auto-save : on stocke le nom sans créer le profil
            $eventFields['organizer_id'] = null;
            $eventFields['organisation'] = $_POST['organizerName'];
        } else {
            require_once __DIR__ . '/../../src/Models/organizer_profile.php';
            $organizerProfile = new OrganizerProfile($pdo, $_SESSION['user_id'] ?? null);
            $organizerName = $_POST['organizerName'];

            // Rechercher un profil existant avec le même nom pour éviter les doublons
            $existingStmt = $pdo->prepare("SELECT id FROM organizer_profiles WHERE user_id = ? AND name = ? LIMIT 1");
            $existingStmt->execute([$_SESSION['user_id'] ?? null, $organizerName]);
            $existingId = $existingStmt->fetchColumn();

            if ($existingId) {
                $eventFields['organizer_id'] = (int)$existingId;
            } else {
                $eventFields['organizer_id'] = $organizerProfile->createOrUpdate([
                    'name' => $organizerName,
                    'email' => $_POST['organizerEmail'] ?? null,
                    'address' => $_POST['organizerAddress'] ?? null,
                    'description' => $_POST['organizerDescription'] ?? null,
                    'website' => $_POST['organizerWebsite'] ?? null,
                    'phone' => $_POST['organizerPhone'] ?? null,
                ]);
            }
            $eventFields['organisation'] = null;
        }
    } elseif (isset($_POST['organizerId'])) {
        // "Moi-même / compte principal" ou vide : on efface l'organisateur lié
        $eventFields['organizer_id'] = null;
        $eventFields['organisation'] = null;
    }

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
        if ($value !== null || $field === 'organizer_id') {
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
            // Ne compter que les fichiers effectivement soumis (pas les champs vides)
            $uploadCount = count(array_filter($_FILES['secondaryImages']['tmp_name'], function($tmp) { return !empty($tmp); }));
            $uploadCountOk = 0;
            foreach ($_FILES['secondaryImages']['error'] as $error) {
                if ($error === UPLOAD_ERR_OK) {
                    $uploadCountOk++;
                }
            }

            // Vérifier le nombre total d'images secondaires (existantes + nouvelles)
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM draft_images WHERE event_id = ? AND is_main = 0");
            $countStmt->execute([$draftId]);
            $existingCount = (int)$countStmt->fetchColumn();

            if ($uploadCountOk > MAX_SECONDARY_IMAGES) {
                throw new Exception("Vous ne pouvez pas uploader plus de " . MAX_SECONDARY_IMAGES . " images secondaires à la fois");
            }
            if ($existingCount + $uploadCountOk > MAX_SECONDARY_IMAGES) {
                throw new Exception("Vous ne pouvez pas avoir plus de " . MAX_SECONDARY_IMAGES . " images secondaires au total");
            }

            $uploadedImages = [];

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
                        $uploadedImages[] = [
                            'image_path' => getFullUrl($uploadResult['public_url']),
                            'storage_path' => $uploadResult['storage_path']
                        ];
                    }
                }
            }

            if (!empty($uploadedImages)) {
                foreach ($uploadedImages as $img) {
                    $stmt = $pdo->prepare("
                        INSERT INTO draft_images (
                            event_id, image_path, storage_path, is_main, storage_type
                        ) VALUES (
                            :event_id, :image_path, :storage_path, 0, 'local'
                        )
                    ");

                    if (!$stmt->execute([
                        'event_id' => $draftId,
                        'image_path' => $img['image_path'],
                        'storage_path' => $img['storage_path']
                    ])) {
                        throw new Exception("Erreur lors de l'insertion d'une image secondaire");
                    }

                    $response['secondaryImages'][] = [
                        'id' => $pdo->lastInsertId(),
                        'path' => $img['image_path'],
                        'storage_path' => $img['storage_path']
                    ];
                }

                $response['message'][] = count($response['secondaryImages']) . " images secondaires enregistrées";
            }
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
                // Récupérer les fichiers GPX existants avant suppression afin de les conserver
                // lors des auto-saves qui ne renvoient pas le fichier
                $oldGpxStmt = $pdo->prepare("SELECT gpx_file FROM draft_parcours WHERE event_id = ? ORDER BY id ASC");
                $oldGpxStmt->execute([$draftId]);
                $oldGpxFiles = $oldGpxStmt->fetchAll(PDO::FETCH_COLUMN);

                $pdo->prepare("DELETE FROM draft_parcours WHERE event_id = ?")->execute([$draftId]);

                $stmt = $pdo->prepare("
                    INSERT INTO draft_parcours (
                        event_id, name, category_id, distance, elevation_gain, description,
                        gpx_file, gpx_downloadable, price
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($routes as $index => $route) {
                    // Un parcours est enregistrable dès qu'au moins le nom ou la distance est renseigné
                    $routeName = trim($route['name'] ?? '');
                    $routeDistance = is_numeric($route['distance'] ?? null) ? $route['distance'] : null;
                    $categoryId = !empty($route['category_id']) ? (int)$route['category_id'] : null;

                    if ($routeName !== '' || !is_null($routeDistance) || !empty($categoryId)) {
                        // Traiter le fichier GPX s'il existe
                        $gpxPath = null;
                        $downloadable = !empty($route['gpx_downloadable']) ? 1 : 0;
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
                                customLog("Fichier GPX uploadé pour le parcours " . ($routeName ?: '#') . ": " . $gpxPath);
                            }
                        } elseif (!empty($route['gpx_file'])) {
                            // Conserver le GPX déjà enregistré lors d'une mise à jour sans nouveau fichier
                            $gpxPath = $route['gpx_file'];
                            customLog("GPX existant conservé pour le parcours " . ($routeName ?: '#') . ": " . $gpxPath);
                        } elseif (!empty($oldGpxFiles[$index])) {
                            // Fallback par position lorsque le frontend n'a pas renvoyé le champ gpx_file (auto-save)
                            $gpxPath = $oldGpxFiles[$index];
                            customLog("GPX existant conservé par index pour le parcours " . ($routeName ?: '#') . ": " . $gpxPath);
                        }

                        $elevation = is_numeric($route['elevation'] ?? null) ? (int) $route['elevation'] : null;
                        $price = is_numeric($route['price'] ?? null) ? $route['price'] : 0.00;

                        if ($gpxPath && $downloadable === 0 && !isset($route['gpx_downloadable'])) {
                            $downloadable = 1;
                        }

                        $stmt->execute([
                            $draftId,
                            $routeName,
                            $categoryId,
                            $routeDistance,
                            $elevation,
                            $route['description'] ?? '',
                            $gpxPath,
                            $downloadable,
                            $price
                        ]);

                        $response['routes'][] = [
                            'index' => $index,
                            'name' => $routeName,
                            'gpx_file' => $gpxPath,
                            'gpx_downloadable' => $downloadable
                        ];
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
    $json = json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $json !== false ? $json : json_encode(['success' => false, 'message' => 'Erreur d\'encodage JSON'], JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
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
    $json = json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    echo $json !== false ? $json : '{"success":false,"message":"Erreur d\'encodage"}';
}
