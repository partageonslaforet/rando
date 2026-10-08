<?php
/**
 * localisation: api/admin/events/notify_organizer.php
 * Role: Envoie manuellement à l'organisateur l'email "votre événement est en ligne"
 *       avec le lien de revendication tokenisé (pages/claim-event.php).
 * Usage: POST /api/admin/events/notify_organizer.php — body JSON { "event_id": N }
 * Dépendances: includes/config.php, includes/mailer.php, logs/error.log.php
 * Sécurité: admin uniquement ; le claim_token est généré si absent (réutilisé sinon).
 */

header('Content-Type: application/json');
ob_start(); // capture tout output parasite (notices PHP, BOM, whitespace) qui casserait le JSON
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../logs/error.log.php';

$logs = [];
function logMessage(string $message) {
    global $logs;
    $logs[] = $message;
}

// Envoie une réponse JSON propre : purge le buffer et log le bruit capturé pour diagnostic
function respond(array $payload, int $code = 200): void {
    $stray = ob_get_clean();
    if ($stray !== '' && $stray !== false) {
        if (function_exists('logError')) {
            logError('api/admin/events/notify_organizer.php', 'Output parasite capturé (JSON corrompu)', [
                'len' => strlen($stray),
                'sample' => substr($stray, 0, 200),
            ]);
        }
    }
    http_response_code($code);
    echo json_encode($payload);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    respond(['success' => false, 'message' => 'Non autorisé', 'logs' => $logs], 401);
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check admin
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['role'] !== 'admin') {
        respond(['success' => false, 'message' => 'Accès refusé', 'logs' => $logs], 403);
    }

    // Payload
    $data = json_decode(file_get_contents('php://input'), true);
    $eventId = (int)($data['event_id'] ?? 0);
    if (!$eventId) {
        respond(['success' => false, 'message' => 'event_id invalide', 'logs' => $logs], 400);
    }

    // Événement + email du premier contact déclaré (event_contacts) —
    // l'admin insère l'événement, le destinataire est le contact de l'organisateur
    $stmt = $pdo->prepare(
        'SELECT e.*,
                (SELECT ec.email FROM event_contacts ec
                  WHERE ec.event_id = e.id AND ec.email IS NOT NULL AND ec.email <> ""
                  ORDER BY ec.id LIMIT 1) AS contact_email,
                (SELECT ec.name FROM event_contacts ec
                  WHERE ec.event_id = e.id AND ec.email IS NOT NULL AND ec.email <> ""
                  ORDER BY ec.id LIMIT 1) AS contact_name
         FROM events e
         WHERE e.id = ? AND e.status = "approved"'
    );
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        respond(['success' => false, 'message' => 'Événement introuvable ou non approuvé', 'logs' => $logs], 404);
    }
    if (empty($event['contact_email'])) {
        respond(['success' => false, 'message' => 'Aucun contact avec email sur cet événement', 'logs' => $logs]);
    }
    if (!filter_var($event['contact_email'], FILTER_VALIDATE_EMAIL)) {
        logError('api/admin/events/notify_organizer.php', 'Email contact invalide', [
            'event_id' => $eventId,
            'email' => $event['contact_email'],
        ]);
        respond([
            'success' => false,
            'message' => 'Adresse email de contact invalide : ' . $event['contact_email']
                       . ' — corrigez-la dans la fiche de l\'événement',
            'logs' => $logs,
        ]);
    }

    // Claim token : réutilisé s'il existe déjà (le lien reste stable entre deux envois)
    $claimToken = $event['claim_token'] ?? null;
    if (empty($claimToken)) {
        $claimToken = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE events SET claim_token = ? WHERE id = ?')->execute([$claimToken, $eventId]);
        logMessage("Claim token généré pour event $eventId");
    }

    $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    $claimUrl = $base . '/pages/claim-event.php?token=' . $claimToken;

    require_once __DIR__ . '/../../../includes/mailer.php';
    $mailer = new Mailer();
    $sent = $mailer->sendOrganizerEventOnlineEmail($event['contact_email'], $event, $claimUrl);

    if ($sent) {
        $pdo->prepare('UPDATE events SET organizer_notified_at = NOW() WHERE id = ?')->execute([$eventId]);
        logMessage("Email envoyé à " . $event['contact_email']);
        respond([
            'success' => true,
            'message' => 'Email envoyé à ' . $event['contact_email'],
            'email' => $event['contact_email'],
            'logs' => $logs,
        ]);
    } else {
        logError('api/admin/events/notify_organizer.php', 'Envoi mail échoué', [
            'event_id' => $eventId,
            'to' => $event['contact_email'],
        ]);
        respond(['success' => false, 'message' => 'Échec de l\'envoi de l\'email', 'logs' => $logs]);
    }
} catch (Throwable $e) {
    logError('api/admin/events/notify_organizer.php', 'Erreur notification organisateur', [
        'event_id' => $eventId ?? null,
        'error' => $e->getMessage(),
    ]);
    respond(['success' => false, 'message' => 'Erreur serveur : ' . $e->getMessage(), 'logs' => $logs], 500);
}
