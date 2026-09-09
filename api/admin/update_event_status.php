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
function logMessage($message) {
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
        
        if ($eventInfo && !empty($eventInfo['email'])) {
            // Préparer l'email
            $subject = $status === 'approved' ? 
                'Votre événement a été approuvé' : 
                'Votre événement a été rejeté';

            $message = "Bonjour " . htmlspecialchars($eventInfo['name']) . ",\n\n";
            $message .= "Votre événement \"" . htmlspecialchars($eventInfo['title']) . "\" prévu le " . 
                       date('d/m/Y', strtotime($eventInfo['date'])) . " a été " .
                       ($status === 'approved' ? "approuvé" : "rejeté") . ".\n\n";

            if ($status === 'approved') {
                $message .= "Il est maintenant visible sur le site.\n";
            } else {
                $message .= "Pour plus d'informations, veuillez nous contacter.\n";
            }

            $message .= "\nCordialement,\nL'équipe Partageons la Forêt";

            // En-têtes additionnels
            $headers = 'From: Partageons la Forêt <noreply@partageonslaforet.be>' . "\r\n" .
                      'Reply-To: contact@partageonslaforet.be' . "\r\n" .
                      'X-Mailer: PHP/' . phpversion();

            logMessage("Tentative d'envoi d'email à: " . $eventInfo['email']);
            logMessage("Sujet: " . $subject);
            logMessage("Message: " . str_replace("\n", "\\n", $message));

            // Envoyer l'email
            $mailSent = mail($eventInfo['email'], $subject, $message, $headers);
            logMessage("Résultat envoi email: " . ($mailSent ? 'succès' : 'échec'));
        } else {
            logMessage("Pas d'email envoyé - créateur non trouvé ou sans email");
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
