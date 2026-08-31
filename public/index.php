<?php
/**
 * Point d'entrée web unique.
 * Document-root Apache/MAMP : dossier public/.
 * Ce fichier sert temporairement de routeur pour php -S et délègue
 * aux scripts PHP existants hors du document-root.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Routes autorisées hors du public/ : API, pages, templates d'événements/auth
$allowedPrefixes = ['/api/', '/pages/', '/templates/events/', '/templates/auth/'];
$isRoutable = false;
foreach ($allowedPrefixes as $prefix) {
    if (strpos($uri, $prefix) === 0) {
        $isRoutable = true;
        break;
    }
}

if ($isRoutable) {
    $target = __DIR__ . '/..' . $uri;
    if (is_file($target)) {
        require_once $target;
    } else {
        http_response_code(404);
        if (strpos($uri, '/api/') === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Endpoint non trouvé']);
        } else {
            echo 'Page non trouvée';
        }
    }
} else {
    require_once __DIR__ . '/../index.php';
}
