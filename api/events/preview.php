<?php
session_start();

// Configuration des erreurs et logs
ini_set('display_errors', 1);
error_reporting(E_ALL);

function customLog($message, $isError = false) {
    $logFile = __DIR__ . '/preview.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] " . ($isError ? "ERROR: " : "INFO: ") . $message . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    customLog("PHP Error [$errno]: $errstr in $errfile:$errline", true);
    return true;
});

set_exception_handler(function($e) {
    customLog("Exception non capturée: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString(), true);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
});

try {
    customLog("🚀 Début de la prévisualisation");

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Session expirée. Veuillez vous reconnecter.');
    }

    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';
    
    $pdo = getConnection();
    
    // Lire les données JSON brutes
    $jsonData = file_get_contents('php://input');
    customLog("Données JSON brutes reçues: " . $jsonData);
    
    $data = json_decode($jsonData, true);
    customLog("Données décodées: " . print_r($data, true));
    
    $draftId = $data['draftId'] ?? null;
    customLog("DraftId extrait: " . ($draftId ? $draftId : "null"));
    
    if (!$draftId) {
        throw new Exception('ID du brouillon manquant');
    }

    // Debug: Afficher l'ID reçu
    $debugScript = "<script>console.log('🔍 Draft ID reçu:', " . json_encode($draftId) . ");</script>";

    // Récupération des données de l'événement avec les parcours
    $stmt = $pdo->prepare("
        SELECT ed.*, o.name as organizer_name, o.email as organizer_email,
               GROUP_CONCAT(dp.id) as route_ids,
               GROUP_CONCAT(dp.name) as route_names,
               GROUP_CONCAT(dp.distance) as route_distances,
               GROUP_CONCAT(dp.elevation_gain) as route_elevations,
               GROUP_CONCAT(dp.description) as route_descriptions,
               GROUP_CONCAT(dp.gpx_file) as route_gpx_paths,
               GROUP_CONCAT(dp.gpx_downloadable) as route_gpx_downloadable,
               GROUP_CONCAT(dc.name) as contact_names,
               GROUP_CONCAT(dc.email) as contact_emails,
               GROUP_CONCAT(dc.phone) as contact_phones,
               GROUP_CONCAT(di.image_path) as secondary_images
        FROM draft_events ed
        LEFT JOIN organizer_profiles o ON ed.organisation = o.id
        LEFT JOIN draft_parcours dp ON dp.event_id = ed.id
        LEFT JOIN draft_contacts dc ON dc.event_id = ed.id
        LEFT JOIN draft_images di ON di.event_id = ed.id AND di.is_main = 0
        WHERE ed.id = ? AND ed.user_id = ?
        GROUP BY ed.id
    ");

    $stmt->execute([$draftId, $_SESSION['user_id']]);
    $eventData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug: Afficher la requête SQL et ses résultats
    $debugScript .= "<script>
        console.group('🔍 Requête SQL');
        console.log('Draft ID:', " . json_encode($draftId) . ");
        console.log('User ID:', " . json_encode($_SESSION['user_id']) . ");
        console.log('Données brutes:', " . json_encode($eventData) . ");
        console.groupEnd();
    </script>";

    if (!$eventData) {
        throw new Exception('Brouillon non trouvé ou accès non autorisé');
    }

    // Formatage des données pour le template
    $eventData['routes'] = [];
    if ($eventData['route_ids']) {
        $routeIds = explode(',', $eventData['route_ids']);
        $routeNames = explode(',', $eventData['route_names']);
        $routeDistances = explode(',', $eventData['route_distances']);
        $routeElevations = explode(',', $eventData['route_elevations']);
        $routeDescriptions = explode(',', $eventData['route_descriptions']);
        $routeGpxPaths = explode(',', $eventData['route_gpx_paths']);
        $routeGpxDownloadable = explode(',', $eventData['route_gpx_downloadable']);

        foreach ($routeIds as $i => $routeId) {
            $eventData['routes'][] = [
                'id' => $routeId,
                'name' => $routeNames[$i] ?? '',
                'distance' => $routeDistances[$i] ?? null,
                'elevation_gain' => $routeElevations[$i] ?? null,
                'description' => $routeDescriptions[$i] ?? '',
                'gpx_file' => $routeGpxPaths[$i] ?? '',
                'gpx_downloadable' => $routeGpxDownloadable[$i] ?? 0
            ];
        }
    }

    // Ajout des contacts
    $eventData['contacts'] = [];
    if (!empty($eventData['contact_names'])) {
        $contactNames = explode(',', $eventData['contact_names']);
        $contactEmails = explode(',', $eventData['contact_emails']);
        $contactPhones = explode(',', $eventData['contact_phones']);

        foreach ($contactNames as $i => $name) {
            $eventData['contacts'][] = [
                'name' => $name,
                'email' => $contactEmails[$i] ?? '',
                'phone' => $contactPhones[$i] ?? ''
            ];
        }
    }

    // Ajout des images secondaires
    $eventData['secondary_images'] = !empty($eventData['secondary_images']) 
        ? explode(',', $eventData['secondary_images']) 
        : [];

    customLog("Données formatées: " . print_r($eventData, true));

    // Charger le template
    ob_start();
    include $_SERVER['DOCUMENT_ROOT'] . '/templates/events/preview-template.php';
    $html = ob_get_clean();

    // Debug: Afficher le HTML généré
    $debugScript .= "<script>
        console.group('🔍 Données de prévisualisation');
        console.log('Événement:', " . json_encode($eventData) . ");
        console.log('Routes:', " . json_encode($eventData['routes']) . ");
        console.log('Contacts:', " . json_encode($eventData['contacts']) . ");
        console.log('HTML généré:', " . json_encode($html) . ");
        console.groupEnd();
    </script>";

    $html .= $debugScript;

    // Ajouter des logs JavaScript pour le debugging
    $debugScript = "<script>
        console.group('🔍 Données de prévisualisation');
        console.log('Événement:', " . json_encode($eventData) . ");
        console.log('Routes:', " . json_encode($eventData['routes']) . ");
        console.log('Contacts:', " . json_encode($eventData['contacts']) . ");
        console.groupEnd();
    </script>";

    $html .= $debugScript;

    // Retourner la réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'html' => $html
        ]
    ]);

} catch (Exception $e) {
    customLog("❌ Erreur: " . $e->getMessage(), true);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
