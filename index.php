<?php
/**
 * index.php
 * Role: Point d'entrée principal. Gère le routage, l'authentification et l'affichage des pages.
 * Usage: Requête racine /.
 * Dépendances: config/database.php, includes/functions.php, src/Models/Event.php, templates/
 */

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

try {
    // 1. Headers HTTP et session (AVANT toute sortie)
    header('Content-Type: text/html; charset=utf-8');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 2. Logs initiaux
    debug_log('🚀 Démarrage de index.php');
    debug_log('📍 Variables serveur', [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'non défini',
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'non défini',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'non défini'
    ]);

    // 3. Chargement des dépendances essentielles
    debug_log('Chargement des dépendances');
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/src/Models/Event.php';
    require_once __DIR__ . '/templates/components/header/header.php';
    require_once __DIR__ . '/templates/components/footer/footer.php'; 
    debug_log('✓ Dépendances chargées');

    // 4. Vérification de l'authentification
    require_once __DIR__ . '/includes/auth_check.php';
    debug_log('✓ Session et auth vérifiées');

    // 5. Configuration des paramètres
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $filters = [
        'search' => $_GET['search'] ?? '',
        'sport' => $_GET['sport'] ?? '',
        'date' => $_GET['date'] ?? '',
        'location' => $_GET['location'] ?? '',
        'difficulty' => $_GET['difficulty'] ?? ''
    ];
    debug_log('Paramètres configurés', ['page' => $page, 'limit' => $limit, 'filters' => $filters]);

    // 6. Récupération des données
    debug_log('Récupération des événements');
    $eventManager = new Event($pdo);
    $result = $eventManager->getAll($filters, $page, $limit);
    $events = $result['events'];
    $total_pages = $result['total_pages'];
    debug_log('✓ Événements récupérés', ['count' => count($events)]);

    // 7. Routage AVANT d'afficher le layout : la page détail gère son propre header/footer
    debug_log('🎯 Chargement du template');
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    debug_log('URI analysée: ' . $uri);

    if ($uri === '/event') {
        debug_log('🎯 Chargement de la page détail événement');
        require_once __DIR__ . '/templates/events/event-detail.php';
        return;
    }

    // 8. Début de l'affichage (pages classiques)
    render_header();
    debug_log('✓ Header affiché');

    if ($uri === '/events') {
        debug_log('🎯 Chargement de la page liste des événements');
        require_once __DIR__ . '/templates/events/events.php';
    } else {
        debug_log('🎯 Chargement de la page d\'accueil');
        require_once __DIR__ . '/templates/home.php';

        // Inclure la modale de création d'événement si l'utilisateur est connecté
        if (isLoggedIn()) {
            $createModalOnly = true;
            require_once __DIR__ . '/templates/modals/create-event.php';
        }
    }
    debug_log('✅ Template chargé avec succès');

    // 9. Affichage du footer
    render_footer();
    require_once __DIR__ . '/templates/layouts/footer.php';
    debug_log('✓ Footer affiché');
    
} catch (Exception $e) {
    debug_log("❌ ERREUR CRITIQUE", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    echo "<h1>Une erreur est survenue</h1>";
    echo "<pre>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Fichier: " . htmlspecialchars($e->getFile()) . "\n";
    echo "Ligne: " . $e->getLine() . "\n";
    echo "</pre>";
}