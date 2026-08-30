<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fonction de log
function preview_log($message) {
    // Échapper les caractères spéciaux et les retours à la ligne
    $escaped = str_replace(
        ["\n", "\r", "'", '"'],
        ['\n', '\r', "\\'", '\\"'],
        $message
    );
    echo "<script>console.log('[PREVIEW] " . $escaped . "')</script>\n";
    echo "<!-- DEBUG PREVIEW: " . htmlspecialchars($message) . " -->\n";
}

preview_log('🚀 Démarrage de preview.php');

// Inclure les dépendances
$rootDir = dirname(__DIR__);
preview_log('📂 Root directory: ' . $rootDir);

// Vérifier si les fichiers existent
$filesNeeded = [
    '/config/database.php',
    '/templates/events/event-detail.php'
];

foreach ($filesNeeded as $file) {
    $fullPath = $rootDir . $file;
    if (!file_exists($fullPath)) {
        preview_log('❌ Fichier manquant: ' . $fullPath);
        die("<div class='alert alert-danger'>Fichier manquant: $file</div>");
    }
    preview_log('✅ Fichier trouvé: ' . $fullPath);
}

require_once $rootDir . '/config/database.php';

// Vérifier si un ID est fourni
if (!isset($_GET['id'])) {
    preview_log('❌ Aucun ID fourni');
    die("<div class='alert alert-danger'>Aucun ID de brouillon fourni</div>");
}

preview_log('📌 ID reçu: ' . $_GET['id']);

try {
    // Obtenir la connexion à la base de données
    $pdo = getConnection();
    preview_log('✅ Connexion à la base de données établie');
    
    // Récupérer le brouillon
    $query = "SELECT * FROM event_drafts WHERE id = :id AND status = 'draft' LIMIT 1";
    preview_log('🔍 Requête SQL: ' . $query);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $_GET['id']]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$draft) {
        preview_log('❌ Brouillon non trouvé pour ID: ' . $_GET['id']);
        die("<div class='alert alert-danger'>Brouillon non trouvé</div>");
    }
    
    // Convertir le tableau en chaîne JSON pour le log
    preview_log('✅ Brouillon trouvé: ' . json_encode($draft, JSON_PRETTY_PRINT));
    
    // Convertir le brouillon en événement pour l'affichage
    $event = [
        'id' => $draft['id'],
        'title' => $draft['title'],
        'description' => $draft['description'],
        'date' => $draft['date'],
        'start_time' => $draft['start_time'],
        'end_time' => $draft['end_time'],
        'location' => $draft['location'],
        'venue' => $draft['venue'],
        'coordinates' => $draft['coordinates'],
        'category' => $draft['category'],
        'difficulty' => $draft['difficulty'],
        'max_participants' => $draft['max_participants'],
        'organisation' => $draft['organisation'],
        'has_gpx' => $draft['has_gpx'],
        'gpx_path' => $draft['gpx_path'],
        'gpx_downloadable' => $draft['gpx_downloadable'],
        'status' => 'preview'
    ];
    
    // Convertir le tableau en chaîne JSON pour le log
    preview_log('✅ Événement converti: ' . json_encode($event, JSON_PRETTY_PRINT));
    
    // Ajouter le paramètre preview=true à l'URL
    $_GET['preview'] = 'true';
    
    // Inclure le template de détail d'événement
    include $rootDir . '/templates/events/event-detail.php';
    
    preview_log('✅ Template inclus avec succès');
    
} catch (Exception $e) {
    $error = $e->getMessage();
    preview_log('❌ Erreur: ' . $error);
    die("<div class='alert alert-danger'>Une erreur est survenue: $error</div>");
}
