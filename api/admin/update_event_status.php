<?php
/**
 * localisation: api/admin/update_event_status.php
 * Role: Mettre à jour le statut d un evenement (approuver, refuser, expirer, etc.)
 * Usage: Requete /api/admin/update_event_status.php?id=X
 * Dépendances: includes/config.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

// Initialiser le tableau des logs
$logs = [];
function logMessage(string $message) {
    global $logs;
    $logs[] = $message;
}

session_start();
logMessage("Début du traitement");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    logMessage("Utilisateur non connecté");
    echo json_encode(['success' => false, 'message' => 'Non autorisé', 'logs' => $logs]);
    exit();
}

try {
    // Connexion à la base de données
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    logMessage("Connexion BD réussie");

    // Vérifier si l'utilisateur est admin
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    logMessage("Rôle de l'utilisateur: " . ($user['role'] ?? 'non trouvé'));

    if (!$user || $user['role'] !== 'admin') {
        logMessage("Accès refusé - utilisateur non admin");
        echo json_encode(['success' => false, 'message' => 'Accès refusé', 'logs' => $logs]);
        exit();
    }

    // Récupérer l'ID et le statut
    $eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $data = json_decode(file_get_contents('php://input'), true);
    $status = $data['status'] ?? '';
    logMessage("Demande de mise à jour - Event ID: $eventId, Nouveau statut: $status");

    if (!$eventId || !in_array($status, ['pending', 'approved', 'rejected'])) {
        logMessage("Paramètres invalides - ID: $eventId, Status: $status");
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides', 'logs' => $logs]);
        exit();
    }

    // Mettre à jour le statut
    $stmt = $pdo->prepare('UPDATE events SET status = ?, updated_at = NOW() WHERE id = ?');
    $success = $stmt->execute([$status, $eventId]);
    logMessage("Mise à jour du statut: " . ($success ? 'réussie' : 'échouée'));

    if ($success) {
        // D'abord, vérifions si l'événement existe et récupérons son créateur
        $stmt = $pdo->prepare('
            SELECT e.*, u.email, u.name 
            FROM events e 
            LEFT JOIN users u ON e.user_id = u.id 
            WHERE e.id = ?
        ');
        $stmt->execute([$eventId]);
        $eventInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        logMessage("Événement et infos utilisateur: " . json_encode($eventInfo));
        
        if ($eventInfo && !empty($eventInfo['email']) && in_array($status, ['approved', 'rejected'])) {
            // Email harmonisé via le Mailer (templates event_status_approved / _rejected)
            try {
                require_once __DIR__ . '/../../includes/mailer.php';
                $mailer = new Mailer();
                logMessage("Tentative d'envoi d'email à: " . $eventInfo['email']);
                $mailSent = $status === 'approved'
                    ? $mailer->sendEventApprovedEmail($eventInfo['email'], $eventInfo)
                    : $mailer->sendEventRejectedEmail($eventInfo['email'], $eventInfo);
                logMessage("Résultat envoi email: " . ($mailSent ? 'succès' : 'échec'));
            } catch (\Throwable $e) {
                logMessage("Erreur envoi email: " . $e->getMessage());
            }
        } elseif ($status === 'pending') {
            logMessage("Pas d'email envoyé - statut 'pending' (retour en attente)");
        } else {
            logMessage("Pas d'email envoyé - créateur non trouvé ou sans email");
        }

        // Notifier les abonnés lorsque l'événement devient public (nouvel événement
        // ou modification re-approuvée). Ne doit jamais bloquer la réponse.
        if ($status === 'approved') {
            try {
                // Re-publication après modification (draft avec original_event_id)
                // → notification 'update' ; sinon 'auto' (détection par l'historique)
                $repub = $pdo->prepare('SELECT COUNT(*) FROM draft_events WHERE original_event_id = ?');
                $repub->execute([$eventId]);
                $notifContext = ((int) $repub->fetchColumn() > 0) ? 'update' : 'auto';

                require_once __DIR__ . '/../../src/Services/Subscribers.php';
                $subscribers = new Subscribers();
                $notifResult = $subscribers->notifyEvent($eventId, $notifContext);
                logMessage("Notifications abonnés: type=" . ($notifResult['type'] ?? '?') .
                           ", sent=" . ($notifResult['sent'] ?? 0) .
                           ", skipped=" . ($notifResult['skipped'] ?? 0));
            } catch (\Throwable $e) {
                logMessage("Erreur notifications abonnés: " . $e->getMessage());
                if (function_exists('logError')) {
                    logError('api/admin/update_event_status.php', 'Erreur notifications abonnés', [
                        'event_id' => $eventId,
                        'exception' => $e->getMessage()
                    ]);
                }
            }
        }

        echo json_encode(['success' => true, 'logs' => $logs]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour', 'logs' => $logs]);
    }

} catch (Exception $e) {
    logMessage("ERREUR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur', 'logs' => $logs]);
}

logMessage("Fin du traitement");
?>
