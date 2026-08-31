<?php
/**
 * Point d'entrée web unique.
 * Document-root Apache/MAMP : dossier public/.
 * Ce fichier délègue au contrôleur existant à la racine du projet
 * et sert temporairement de proxy pour les endpoints API (/api/*).
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($uri, '/api/') === 0) {
    $target = __DIR__ . '/..' . $uri;
    if (is_file($target)) {
        require_once $target;
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint non trouvé']);
    }
} else {
    require_once __DIR__ . '/../index.php';
}
