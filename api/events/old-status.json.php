<?php
// Désactiver toute sortie de tampon
@ob_end_clean();
while (ob_get_level()) {
    @ob_end_clean();
}

// Désactiver l'affichage des erreurs
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Fonction pour envoyer une réponse JSON et terminer
function sendJsonResponse($success, $message = null, $httpCode = 200) {
    // Nettoyer toute sortie précédente
    while (ob_get_level()) {
        @ob_end_clean();
    }
    
    // Définir les en-têtes
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    http_response_code($httpCode);
    
    // Préparer la réponse
    $response = ['success' => $success];
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    // Encoder et logger la réponse
    $jsonResponse = json_encode($response);
    error_log("Réponse JSON envoyée: " . $jsonResponse);
    
    // Envoyer la réponse
    echo $jsonResponse;
    exit();
}

// Log pour debug
error_log("API status.php appelée");

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log des données reçues
error_log("POST Data: " . file_get_contents('php://input'));
error_log("GET Data: " . json_encode($_GET));
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'non défini'));

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id'])) {
    error_log("Erreur: Utilisateur non connecté");
    sendJsonResponse(false, 'Non autorisé', 401);
}

try {
    // Inclure la configuration
    require_once '/home/cool5792/rando.partageonslaforet.be/includes/config.php';

    // Connexion à la base de données
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    error_log("Connexion BD réussie");

    // Vérifier si l'utilisateur est admin
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    error_log("Role utilisateur: " . ($user['role'] ?? 'non trouvé'));

    if (!$user || $user['role'] !== 'admin') {
        error_log("Erreur: Utilisateur non admin");
        sendJsonResponse(false, 'Accès refusé', 403);
    }

    // Récupérer l'ID de l'événement
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        error_log("Erreur: ID événement invalide ou manquant: " . ($_GET['id'] ?? 'non défini'));
        sendJsonResponse(false, 'ID d\'événement manquant ou invalide', 400);
    }

    $eventId = (int)$_GET['id'];
    error_log("ID événement: " . $eventId);

    // Vérifier que l'événement existe
    $stmt = $pdo->prepare('SELECT id FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    error_log("Événement trouvé: " . ($event ? 'oui' : 'non'));

    if (!$event) {
        error_log("Erreur: Événement non trouvé");
        sendJsonResponse(false, 'Événement non trouvé', 404);
    }

    // Récupérer les données JSON
    $jsonData = file_get_contents('php://input');
    error_log("Données JSON reçues: " . $jsonData);
    
    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erreur décodage JSON: " . json_last_error_msg());
        sendJsonResponse(false, 'Données JSON invalides', 400);
    }

    $status = $data['status'] ?? '';
    error_log("Nouveau statut demandé: " . $status);

    // Valider le statut
    if (!in_array($status, ['pending', 'approved', 'rejected'])) {
        error_log("Erreur: Statut invalide");
        sendJsonResponse(false, 'Statut invalide', 400);
    }

    // Mettre à jour le statut
    $stmt = $pdo->prepare('UPDATE events SET status = ?, updated_at = NOW() WHERE id = ?');
    $success = $stmt->execute([$status, $eventId]);
    error_log("Mise à jour statut: " . ($success ? 'réussie' : 'échouée'));

    if (!$success) {
        error_log("Erreur lors de la mise à jour du statut");
        sendJsonResponse(false, 'Erreur lors de la mise à jour', 500);
    }

    // Envoyer un email de notification si nécessaire
    if ($status === 'approved' || $status === 'rejected') {
        $stmt = $pdo->prepare('
            SELECT e.title, e.date, u.email, u.name
            FROM events e
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
        ');
        $stmt->execute([$eventId]);
        $eventInfo = $stmt->fetch();
        error_log("Info événement pour email: " . json_encode($eventInfo));

        if ($eventInfo && !empty($eventInfo['email'])) {
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

            $mailSent = mail($eventInfo['email'], $subject, $message);
            error_log("Email envoyé: " . ($mailSent ? 'oui' : 'non'));
        }
    }

    // Envoyer la réponse de succès
    sendJsonResponse(true);

} catch (PDOException $e) {
    error_log("Erreur BD: " . $e->getMessage());
    sendJsonResponse(false, 'Erreur serveur', 500);
} catch (Exception $e) {
    error_log("Erreur générale: " . $e->getMessage());
    sendJsonResponse(false, 'Erreur serveur', 500);
}
?>
