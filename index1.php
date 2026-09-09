<?php
// Activation de l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Fonction de log détaillée
function debug_log($message, $data = null) {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    $location = basename($trace['file']) . ':' . $trace['line'];
    $log = "[$location] $message";
    if ($data !== null) {
        $log .= "\nData: " . print_r($data, true);
    }
    error_log($log);
}

// Logs initiaux
debug_log('🚀 Démarrage de index.php');
debug_log('📍 Variables serveur', [
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'non défini',
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'non défini',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'non défini',
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'non défini',
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'non défini',
    'PWD' => $_SERVER['PWD'] ?? 'non défini',
    'CURRENT_DIR' => __DIR__
]);

try {
    // Charger la configuration de la base de données
    debug_log('Chargement de database.php');
    require_once __DIR__ . '/config/database.php';
    debug_log('✓ database.php chargé');

    // Charger les fonctions utilitaires
    debug_log('Chargement de functions.php');
    require_once __DIR__ . '/includes/functions.php';
    debug_log('✓ functions.php chargé');

    // Charger la classe Event
    debug_log('Chargement de Event.php');
    require_once __DIR__ . '/src/Models/Event.php';
    debug_log('✓ Event.php chargé');

    // Démarrer la session
    debug_log('Démarrage de la session');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    debug_log('✓ Session démarrée', ['SESSION_ID' => session_id()]);

    // Inclure le header
    debug_log('Chargement du header');
    require_once __DIR__ . '/templates/layouts/header.php';
    debug_log('✓ Header chargé');

    // Vérification de l'authentification
    debug_log('Vérification de l\'authentification');
    require_once __DIR__ . '/includes/auth_check.php';
    debug_log('✓ Authentification vérifiée', ['USER_ID' => $_SESSION['user_id'] ?? 'non connecté']);

    // Paramètres de pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    debug_log('Paramètres de pagination', ['page' => $page, 'limit' => $limit]);

    // Configuration des filtres
    $filters = [
        'search' => $_GET['search'] ?? '',
        'sport' => $_GET['sport'] ?? '',
        'date' => $_GET['date'] ?? '',
        'location' => $_GET['location'] ?? '',
        'difficulty' => $_GET['difficulty'] ?? ''
    ];
    debug_log('Filtres configurés', $filters);

    // Instanciation de la classe Event
    debug_log('Création de l\'instance Event');
    $eventManager = new Event($pdo);
    debug_log('✓ Instance Event créée');

    // Récupération des événements
    debug_log('Récupération des événements');
    $result = $eventManager->getAll($filters, $page, $limit);
    $events = $result['events'];
    $total_pages = $result['total_pages'];
    debug_log('✓ Événements récupérés', [
        'count' => count($events),
        'total_pages' => $total_pages
    ]);

    // Inclusion du template
    debug_log('🎯 Chargement du template home.php');
    require_once __DIR__ . '/templates/home.php';
    debug_log('✅ Template chargé avec succès');

} catch (Exception $e) {
    debug_log("❌ ERREUR CRITIQUE", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Affichage de l'erreur directement
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>Une erreur est survenue</h1>";
    echo "<pre>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Fichier: " . htmlspecialchars($e->getFile()) . "\n";
    echo "Ligne: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}
