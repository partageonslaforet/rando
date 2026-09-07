<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../logs/error.log.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $eventId = (int)($input['event_id'] ?? 0);
    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'event_id manquant']);
        exit;
    }

    $db = getConnection();

    $stmt = $db->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Événement introuvable"]);
        exit;
    }

    $isOwner = ((int)$event['user_id'] === (int)$_SESSION['user_id']);
    $isAdmin = (isset($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin')
            || (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
    if (!$isOwner && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès interdit']);
        exit;
    }

    $db->beginTransaction();

    // Créer le brouillon à partir des champs principaux connus
    $ins = $db->prepare("INSERT INTO draft_events (
        user_id, title, description, date, registration_opens, registration_closes,
        location, venue, coordinates, meeting_name, meeting_address, meeting_coordinates,
        organisation, organizer_id, created_at, updated_at
    ) VALUES (
        :user_id, :title, :description, :date, :registration_opens, :registration_closes,
        :location, :venue, :coordinates, :meeting_name, :meeting_address, :meeting_coordinates,
        :organisation, :organizer_id, NOW(), NOW()
    )");

    $ins->execute([
        // Le brouillon doit appartenir à l'utilisateur courant (admin inclus)
        ':user_id' => (int)$_SESSION['user_id'],
        ':title' => $event['title'] ?? null,
        ':description' => $event['description'] ?? null,
        ':date' => $event['date'] ?? null,
        ':registration_opens' => $event['registration_opens'] ?? null,
        ':registration_closes' => $event['registration_closes'] ?? null,
        ':location' => $event['location'] ?? null,
        ':venue' => $event['venue'] ?? null,
        ':coordinates' => $event['coordinates'] ?? null,
        ':meeting_name' => $event['meeting_name'] ?? null,
        ':meeting_address' => $event['meeting_address'] ?? null,
        ':meeting_coordinates' => $event['meeting_coordinates'] ?? null,
        ':organisation' => $event['organisation'] ?? null,
        ':organizer_id' => isset($event['organizer_id']) ? (int)$event['organizer_id'] : null,
    ]);

    $draftId = (int)$db->lastInsertId();
    if (function_exists('logError')) {
        logError('create_draft_from_event.php', 'Draft created from event', [
            'event_id' => $eventId,
            'draft_id' => $draftId,
            'event_organizer_id' => $event['organizer_id'] ?? null,
            'event_organisation' => $event['organisation'] ?? null,
            'draft_owner' => $_SESSION['user_id'] ?? null,
        ]);
    }

    // Optionnel: lier le brouillon à l'événement d'origine si la colonne existe
    try {
        $colCheck = $db->prepare("SELECT original_event_id FROM draft_events WHERE id = ?");
        $colCheck->execute([$draftId]);
        // Si pas d'exception, on peut mettre à jour
        $upd = $db->prepare('UPDATE draft_events SET original_event_id = ? WHERE id = ?');
        $upd->execute([$eventId, $draftId]);
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('create_draft_from_event.php', 'original_event_id non défini (colonne absente?)', ['error' => $e->getMessage()]);
        }
    }

    // Copier les liens de catégories si table event_category_links existe
    try {
        $catSel = $db->prepare('SELECT category_id FROM event_category_links WHERE event_id = ?');
        $catSel->execute([$eventId]);
        $catIds = $catSel->fetchAll(PDO::FETCH_COLUMN, 0);
        if ($catIds) {
            $catIns = $db->prepare('INSERT INTO draft_event_category_links (draft_event_id, category_id) VALUES (?, ?)');
            foreach ($catIds as $cid) {
                $catIns->execute([$draftId, (int)$cid]);
            }
        } elseif (!empty($event['category_id'])) {
            $catIns = $db->prepare('INSERT INTO draft_event_category_links (draft_event_id, category_id) VALUES (?, ?)');
            $catIns->execute([$draftId, (int)$event['category_id']]);
        }
    } catch (Throwable $e) {
        // Ignorer si la table n'existe pas, mais consigner l'erreur
        if (function_exists('logError')) {
            logError('create_draft_from_event.php', 'Catégories non copiées', ['error' => $e->getMessage()]);
        }
    }

    // Copier les images de l'événement publié vers le brouillon
    try {
        $imgIns = $db->prepare("INSERT INTO draft_images (event_id, image_path, storage_path, is_main, storage_type)
                                 SELECT ?, image_path, storage_path, is_main, storage_type
                                 FROM event_images WHERE event_id = ?");
        $imgIns->execute([$draftId, $eventId]);
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('create_draft_from_event.php', 'Images non copiées', ['error' => $e->getMessage()]);
        }
    }

    // Copier les contacts (si table présente)
    try {
        $ctIns = $db->prepare("INSERT INTO draft_contacts (event_id, name, email, phone)
                               SELECT ?, name, email, phone
                               FROM event_contacts WHERE event_id = ?");
        $ctIns->execute([$draftId, $eventId]);
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('create_draft_from_event.php', 'Contacts non copiés', ['error' => $e->getMessage()]);
        }
    }

    // Copier les parcours si disponibles
    try {
        $parSel = $db->prepare('SELECT name, category_id, distance, elevation_gain, description, gpx_file, gpx_downloadable, price FROM event_parcours WHERE event_id = ? ORDER BY id');
        $parSel->execute([$eventId]);
        $routes = $parSel->fetchAll(PDO::FETCH_ASSOC);
        if ($routes) {
            $parIns = $db->prepare('INSERT INTO draft_parcours (event_id, name, category_id, distance, elevation_gain, description, gpx_file, gpx_downloadable, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($routes as $r) {
                $parIns->execute([
                    $draftId,
                    $r['name'] ?? null,
                    isset($r['category_id']) ? (int)$r['category_id'] : null,
                    $r['distance'] ?? null,
                    $r['elevation_gain'] ?? null,
                    $r['description'] ?? null,
                    $r['gpx_file'] ?? null,
                    isset($r['gpx_downloadable']) ? (int)$r['gpx_downloadable'] : 0,
                    $r['price'] ?? null,
                ]);
            }
        }
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('create_draft_from_event.php', 'Parcours non copiés', ['error' => $e->getMessage()]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true, 'draft_id' => $draftId]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if (function_exists('logError')) {
        logError('create_draft_from_event.php', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
