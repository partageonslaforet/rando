<?php
/**
 * Mise à jour d'un événement publié par son propriétaire.
 * Modifie le contenu, les images et les parcours en base.
 *
 * Utilisé par : public/assets/js/event-edit.js, public/assets/js/event-validation.js, templates/events/event-edit.js
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../logs/error.log.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';
require_once __DIR__ . '/../../src/Services/Storage.php';

// Vérifier la session
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    $db = getConnection();
    $db->beginTransaction();

    // Récupérer l'ID de l'événement
    $eventId = $_POST['eventId'] ?? null;
    if (!$eventId) {
        throw new Exception('ID de l\'événement manquant');
    }

    // Vérifier que l'utilisateur est bien le propriétaire de l'événement
    $stmt = $db->prepare("SELECT user_id FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $isAdmin = function_exists('isAdmin') ? isAdmin() : (($_SESSION['user_role'] ?? '') === 'admin');
    if (!$event || ($event['user_id'] != $_SESSION['user_id'] && !$isAdmin)) {
        throw new Exception('Non autorisé à modifier cet événement');
    }

    // Synchroniser les champs meeting_* pour la page d'affichage
    $meetingAddress = trim($_POST['address'] ?? '');
    $meetingCity = '';
    if (!empty($meetingAddress)) {
        if (preg_match('/\b\d{4,5}\s+(.+)$/', $meetingAddress, $m)) {
            $meetingCity = trim($m[1]);
        } else {
            $parts = preg_split('/[,\s]+/', $meetingAddress);
            $meetingCity = trim(end($parts));
        }
    }

    // 1. Mettre à jour la table events
    $fieldsToUpdate = [
        'title' => $_POST['title'] ?? null,
        'description' => $_POST['description'] ?? null,
        'date' => $_POST['date'] ?? null,
        'start_time' => $_POST['start_time'] ?? null,
        'end_time' => $_POST['end_time'] ?? null,
        'location' => $_POST['location'] ?? null,
        'coordinates' => $_POST['coordinates'] ?? null,
        'meeting_name' => $_POST['location'] ?? null,
        'meeting_address' => $_POST['address'] ?? null,
        'meeting_city' => $meetingCity,
        'meeting_coordinates' => $_POST['coordinates'] ?? null,
        'organisation' => $_POST['organizerId'] ?? $_POST['organisation'] ?? null,
        'venue' => $_POST['address'] ?? null,
        'max_participants' => $_POST['max_participants'] ?? null,
        'category_id' => $_POST['category_id'] ?? null
    ];

    // Récupérer les anciennes valeurs
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $oldEvent = $stmt->fetch(PDO::FETCH_ASSOC);

    // Construire la requête UPDATE
    $updateFields = [];
    $updateValues = [];
    $changedLabels = []; // libellés FR des éléments modifiés (pour notification abonnés)
    $fieldLabels = [
        'title' => 'Titre',
        'description' => 'Description',
        'date' => 'Date',
        'start_time' => 'Heure de début',
        'end_time' => 'Heure de fin',
        'location' => 'Lieu',
        'meeting_name' => 'Lieu',
        'coordinates' => 'Coordonnées GPS',
        'meeting_coordinates' => 'Coordonnées GPS',
        'meeting_address' => 'Adresse de rendez-vous',
        'venue' => 'Adresse de rendez-vous',
        'meeting_city' => 'Ville',
        'organisation' => 'Organisation',
        'max_participants' => 'Nombre de places',
        'category_id' => 'Catégorie',
        'contacts' => 'Contacts',
        'parcours' => 'Parcours',
        'images' => 'Images',
    ];
    foreach ($fieldsToUpdate as $field => $value) {
        if ($value !== null && $value !== $oldEvent[$field]) {
            $updateFields[] = "$field = ?";
            $updateValues[] = $value;

            if (isset($fieldLabels[$field])) {
                $changedLabels[] = $fieldLabels[$field];
            }

            // Enregistrer la modification
            logEventModification($db, $eventId, $field, $oldEvent[$field], $value, $_SESSION['user_id']);
        }
    }

    if (!empty($updateFields)) {
        $updateValues[] = $eventId;
        $sql = "UPDATE events SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($updateValues);
    }

    // 2. Mettre à jour les contacts
    if (isset($_POST['contacts'])) {
        $contacts = is_string($_POST['contacts']) ? json_decode($_POST['contacts'], true) : $_POST['contacts'];
        
        // Récupérer les anciens contacts
        $stmt = $db->prepare("SELECT * FROM event_contacts WHERE event_id = ?");
        $stmt->execute([$eventId]);
        $oldContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Supprimer les anciens contacts
        $stmt = $db->prepare("DELETE FROM event_contacts WHERE event_id = ?");
        $stmt->execute([$eventId]);
        
        // Insérer les nouveaux contacts
        if (!empty($contacts)) {
            $stmt = $db->prepare("INSERT INTO event_contacts (event_id, name, phone) VALUES (?, ?, ?)");
            foreach ($contacts as $contact) {
                $stmt->execute([$eventId, $contact['name'], $contact['number']]);
            }
        }
        
        // Détecter un changement réel de contacts (nom + téléphone)
        $normOld = array_map(static fn($c) => ['name' => $c['name'] ?? '', 'phone' => $c['phone'] ?? ''], $oldContacts);
        $normNew = array_map(static fn($c) => ['name' => $c['name'] ?? '', 'phone' => $c['number'] ?? ''], $contacts ?: []);
        if ($normOld !== $normNew) {
            $changedLabels[] = 'Contacts';
        }

        // Logger les modifications des contacts
        logEventModification(
            $db, 
            $eventId, 
            'contacts', 
            json_encode($oldContacts), 
            json_encode($contacts), 
            $_SESSION['user_id']
        );
    }

    // 3. Mettre à jour les parcours
    if (isset($_POST['routes'])) {
        $parcours = is_string($_POST['routes']) ? json_decode($_POST['routes'], true) : $_POST['routes'];
        
        // Récupérer les anciens parcours
        $stmt = $db->prepare("SELECT * FROM event_parcours WHERE event_id = ?");
        $stmt->execute([$eventId]);
        $oldParcours = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Supprimer les anciens parcours
        $stmt = $db->prepare("DELETE FROM event_parcours WHERE event_id = ?");
        $stmt->execute([$eventId]);
        
        // Insérer les nouveaux parcours
        if (!empty($parcours)) {
            $stmt = $db->prepare("
                INSERT INTO event_parcours (
                    event_id,
                    name,
                    category_id,
                    distance,
                    elevation_gain,
                    price,
                    gpx_file,
                    gpx_downloadable
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($parcours as $p) {
                $stmt->execute([
                    $eventId,
                    $p['name'],
                    !empty($p['category_id']) ? (int)$p['category_id'] : null,
                    $p['distance'],
                    $p['elevation'],
                    $p['price'],
                    $p['gpx'],
                    !empty($p['gpx_downloadable']) ? 1 : 0
                ]);
            }
        }
        
        // Détecter un changement réel de parcours (nom + distance + dénivelé + prix)
        $normOldP = array_map(static fn($p) => [
            (string)($p['name'] ?? ''),
            (string)($p['distance'] ?? ''),
            (string)($p['elevation_gain'] ?? ''),
            (string)($p['price'] ?? ''),
        ], $oldParcours);
        $normNewP = array_map(static fn($p) => [
            (string)($p['name'] ?? ''),
            (string)($p['distance'] ?? ''),
            (string)($p['elevation'] ?? ''),
            (string)($p['price'] ?? ''),
        ], $parcours ?: []);
        if ($normOldP !== $normNewP) {
            $changedLabels[] = 'Parcours';
        }

        // Logger les modifications des parcours
        logEventModification(
            $db, 
            $eventId, 
            'parcours', 
            json_encode($oldParcours), 
            json_encode($parcours), 
            $_SESSION['user_id']
        );
    }

    // 4. Gérer les images
    if (isset($_FILES['images'])) {
        // Récupérer les anciennes images
        $stmt = $db->prepare("SELECT * FROM event_images WHERE event_id = ?");
        $stmt->execute([$eventId]);
        $oldImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Traiter les nouvelles images
        $newImages = [];
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $filename = uniqid() . '_' . $_FILES['images']['name'][$key];
                $uploadFile = Storage::getStoragePath('events', $filename);
                Storage::ensureDirectoryExists(dirname($uploadFile));
                
                if (move_uploaded_file($tmp_name, $uploadFile)) {
                    $newImages[] = [
                        'path' => Storage::getPublicUrl('events', $filename),
                        'is_main' => isset($_POST['main_image']) && $_POST['main_image'] == $key ? 1 : 0
                    ];
                }
            }
        }
        
        if (!empty($newImages)) {
            // Supprimer les anciennes images qui ne sont plus utilisées
            foreach ($oldImages as $oldImage) {
                $oldFile = Storage::getStoragePath('events', basename($oldImage['image_path']));
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            
            // Supprimer les anciennes entrées dans la base
            $stmt = $db->prepare("DELETE FROM event_images WHERE event_id = ?");
            $stmt->execute([$eventId]);
            
            // Insérer les nouvelles images
            $stmt = $db->prepare("
                INSERT INTO event_images (
                    event_id, 
                    image_path, 
                    is_main
                ) VALUES (?, ?, ?)
            ");
            foreach ($newImages as $image) {
                $stmt->execute([$eventId, $image['path'], $image['is_main']]);
            }
            
            // Logger les modifications des images
            logEventModification(
                $db, 
                $eventId, 
                'images', 
                json_encode($oldImages), 
                json_encode($newImages), 
                $_SESSION['user_id']
            );

            $changedLabels[] = 'Images';
        }
    }

    $db->commit();

    // Notifier les abonnés si l'événement reste publié après modification.
    // Contexte 'update' explicite + liste des éléments modifiés.
    // Sans changement réel détecté, aucune notification n'est envoyée.
    $changedLabels = array_values(array_unique($changedLabels));
    if (!empty($changedLabels)) {
        try {
            require_once __DIR__ . '/../../src/Services/Subscribers.php';
            $subscribers = new Subscribers();
            $notifResult = $subscribers->notifyEvent($eventId, 'update', $changedLabels);
            if (function_exists('logError')) {
                logError('api/events/update.php', 'Notifications abonnés (modif)', [
                    'eventId' => $eventId,
                    'type' => $notifResult['type'] ?? null,
                    'changed' => $changedLabels,
                    'sent' => $notifResult['sent'] ?? 0,
                    'skipped' => $notifResult['skipped'] ?? 0,
                ]);
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('api/events/update.php', 'Erreur notifications abonnés (modif)', [
                    'eventId' => $eventId,
                    'exception' => $e->getMessage()
                ]);
            }
        }
    }

    // Après mise à jour, notifier l'admin pour validation (cas publication depuis l'édition)
    try {
        require_once __DIR__ . '/../../includes/mailer.php';
        require_once __DIR__ . '/../../logs/error.log.php';

        // Récupérer les informations nécessaires pour l'email
        $stmt = $db->prepare("SELECT id, title, date, organisation FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $eventData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => (int)$eventId];

        $adminTo = defined('CONTACT_TO_EMAIL') ? CONTACT_TO_EMAIL : 'rando@partageonslaforet.be';

        if (function_exists('logError')) {
            logError('api/events/update.php', 'Tentative envoi email admin (modif)', [
                'to' => $adminTo,
                'eventId' => $eventId,
                'hasEventData' => (bool)$eventData
            ]);
        }

        $mailer = new Mailer();
        // Ajouter timestamp de publication pour l'email admin (modif)
        $eventData['published_at'] = date('Y-m-d H:i:s');
        $sent = $mailer->sendAdminEventPendingEmail($adminTo, $eventData);

        if (function_exists('logError')) {
            if ($sent) {
                logError('api/events/update.php', 'Email admin de validation (modif) envoyé', [
                    'to' => $adminTo,
                    'eventId' => $eventId
                ]);
            } else {
                logError('api/events/update.php', 'Échec envoi email admin (modif)', [
                    'to' => $adminTo,
                    'eventId' => $eventId,
                    'result' => $sent
                ]);
            }
        }
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('api/events/update.php', 'Erreur envoi email admin (modif)', [
                'eventId' => $eventId,
                'exception' => $e->getMessage()
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Événement mis à jour avec succès',
        'eventId' => $eventId
    ]);

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError('api/events/update.php', 'Exception during event update', [
            'eventId' => $eventId ?? null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Fonction pour enregistrer une modification (silencieuse si la table n'existe pas)
function logEventModification(PDO $db, int $event_id, string $field_name, mixed $old_value, mixed $new_value, int $user_id): bool {
    static $tableExists = null;
    if ($tableExists === null) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'event_modifications'");
            $tableExists = ($stmt->rowCount() > 0);
        } catch (Throwable $e) {
            $tableExists = false;
        }
    }
    if (!$tableExists) {
        if (function_exists('logError')) {
            logError('api/events/update.php', 'event_modifications table missing; skipping log', [
                'event_id' => $event_id,
                'field_name' => $field_name
            ]);
        }
        return false;
    }
    try {
        $stmt = $db->prepare("
            INSERT INTO event_modifications (
                event_id,
                field_name,
                old_value,
                new_value,
                modified_by,
                modified_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$event_id, $field_name, $old_value, $new_value, $user_id]);
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('api/events/update.php', 'logEventModification failed', [
                'event_id' => $event_id,
                'field_name' => $field_name,
                'error' => $e->getMessage()
            ]);
        }
        return false;
    }
}