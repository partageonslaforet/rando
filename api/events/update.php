<?php
/**
 * Mise à jour d'un événement publié par son propriétaire.
 * Modifie le contenu, les images et les parcours en base.
 *
 * Utilisé par : public/assets/js/event-edit.js, public/assets/js/event-validation.js, templates/events/event-edit.js
 */

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';

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
    
    if (!$event || $event['user_id'] != $_SESSION['user_id']) {
        throw new Exception('Non autorisé à modifier cet événement');
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
        'organisation' => $_POST['organisation'] ?? null,
        'venue' => $_POST['venue'] ?? null,
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
    foreach ($fieldsToUpdate as $field => $value) {
        if ($value !== null && $value !== $oldEvent[$field]) {
            $updateFields[] = "$field = ?";
            $updateValues[] = $value;
            
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
        $contacts = json_decode($_POST['contacts'], true);
        
        // Récupérer les anciens contacts
        $stmt = $db->prepare("SELECT * FROM event_contacts WHERE event_id = ?");
        $stmt->execute([$eventId]);
        $oldContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Supprimer les anciens contacts
        $stmt = $db->prepare("DELETE FROM event_contacts WHERE event_id = ?");
        $stmt->execute([$eventId]);
        
        // Insérer les nouveaux contacts
        if (!empty($contacts)) {
            $stmt = $db->prepare("INSERT INTO event_contacts (event_id, name, number) VALUES (?, ?, ?)");
            foreach ($contacts as $contact) {
                $stmt->execute([$eventId, $contact['name'], $contact['number']]);
            }
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
        $parcours = json_decode($_POST['routes'], true);
        
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
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/events/';
        $newImages = [];
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $filename = uniqid() . '_' . $_FILES['images']['name'][$key];
                $uploadFile = $uploadDir . $filename;
                
                if (move_uploaded_file($tmp_name, $uploadFile)) {
                    $newImages[] = [
                        'path' => '/uploads/events/' . $filename,
                        'is_main' => isset($_POST['main_image']) && $_POST['main_image'] == $key ? 1 : 0
                    ];
                }
            }
        }
        
        if (!empty($newImages)) {
            // Supprimer les anciennes images qui ne sont plus utilisées
            foreach ($oldImages as $oldImage) {
                if (file_exists($_SERVER['DOCUMENT_ROOT'] . $oldImage['image_path'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $oldImage['image_path']);
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
        }
    }

    $db->commit();

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

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Fonction pour enregistrer une modification
function logEventModification($db, $event_id, $field_name, $old_value, $new_value, $user_id) {
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
}