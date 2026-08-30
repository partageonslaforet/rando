<?php
header('Content-Type: application/json');

// Activer le logging des erreurs
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/delete.log');

error_log("=== Début de la suppression d'événement ===");

require_once __DIR__ . '/../../../includes/functions.php';
requireLogin();

// Vérifier que l'utilisateur est admin
if (!isAdmin()) {
    error_log("Tentative d'accès non autorisé");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Récupérer et valider les données
$data = json_decode(file_get_contents('php://input'), true);
error_log("Données reçues: " . print_r($data, true));

if (!isset($data['event_id'])) {
    error_log("ID de l'événement manquant");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de l\'événement manquant']);
    exit();
}

$eventId = filter_var($data['event_id'], FILTER_VALIDATE_INT);
error_log("ID de l'événement après filtrage: " . var_export($eventId, true));

if (!$eventId) {
    error_log("ID de l'événement invalide");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de l\'événement invalide']);
    exit();
}

try {
    require_once __DIR__ . '/../../../config/database.php';
    $db = getConnection();
    error_log("Connexion à la base de données établie");

    // Vérifier si l'événement existe
    $checkStmt = $db->prepare('SELECT id FROM events WHERE id = ?');
    $checkStmt->execute([$eventId]);
    $event = $checkStmt->fetch();

    if (!$event) {
        error_log("Événement non trouvé: " . $eventId);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Événement non trouvé']);
        exit();
    }

    error_log("Début de la transaction");
    // Commencer une transaction
    $db->beginTransaction();

    try {
        // 1. Supprimer les contacts
        error_log("Suppression des contacts");
        $stmt = $db->prepare('DELETE FROM event_contacts WHERE id = ?');
        $stmt->execute([$eventId]);
        error_log("Contacts supprimés: " . $stmt->rowCount());

        // 2. Supprimer les images
        error_log("Récupération des chemins d'images");
        $stmt = $db->prepare('SELECT storage_path FROM event_images WHERE id = ?');
        $stmt->execute([$eventId]);
        $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
        error_log("Images trouvées: " . count($images));

        foreach ($images as $imagePath) {
            if ($imagePath && file_exists(__DIR__ . '/../../../..' . $imagePath)) {
                error_log("Suppression du fichier: " . $imagePath);
                unlink(__DIR__ . '/../../../..' . $imagePath);
            }
        }

        error_log("Suppression des entrées d'images en base");
        $stmt = $db->prepare('DELETE FROM event_images WHERE id = ?');
        $stmt->execute([$eventId]);
        error_log("Images supprimées: " . $stmt->rowCount());

        // 3. Supprimer les parcours
        error_log("Récupération des fichiers GPX");
        $stmt = $db->prepare('SELECT gpx_file FROM event_parcours WHERE id = ?');
        $stmt->execute([$eventId]);
        $gpxFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        error_log("Fichiers GPX trouvés: " . count($gpxFiles));

        foreach ($gpxFiles as $gpxPath) {
            if ($gpxPath && file_exists(__DIR__ . '/../../../..' . $gpxPath)) {
                error_log("Suppression du fichier GPX: " . $gpxPath);
                unlink(__DIR__ . '/../../../..' . $gpxPath);
            }
        }

        error_log("Suppression des entrées de parcours en base");
        $stmt = $db->prepare('DELETE FROM event_parcours WHERE id = ?');
        $stmt->execute([$eventId]);
        error_log("Parcours supprimés: " . $stmt->rowCount());

        // 4. Supprimer l'événement lui-même
        error_log("Suppression de l'événement");
        $stmt = $db->prepare('DELETE FROM events WHERE id = ?');
        $success = $stmt->execute([$eventId]);
        error_log("Événement supprimé: " . ($success ? "oui" : "non"));

        if ($success) {
            error_log("Validation de la transaction");
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Événement supprimé avec succès']);
        } else {
            throw new Exception('Erreur lors de la suppression de l\'événement');
        }

    } catch (Exception $e) {
        error_log("Erreur pendant la transaction: " . $e->getMessage());
        error_log("Annulation de la transaction");
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erreur delete.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Une erreur est survenue lors de la suppression',
        'debug' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

error_log("=== Fin de la suppression d'événement ===");
