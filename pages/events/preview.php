<?php
// Démarrer la session avant tout output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fonction de log
function preview_log($message) {
    error_log("[PREVIEW] " . print_r($message, true));
}

preview_log('🚀 Démarrage de preview.php');

// Inclure les dépendances
$rootDir = dirname(__DIR__);
preview_log('📂 Root directory: ' . $rootDir);

require_once $rootDir . '/config/database.php';
preview_log('✅ Fichier trouvé: ' . $rootDir . '/config/database.php');

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
    
    // Récupérer le brouillon avec toutes les informations
    $query = "
        SELECT d.*, 
               o.email as organizer_email,
               o.phone as organizer_phone,
               o.website as organizer_website,
               o.name as organizer_name,
               o.description as organizer_description,
               o.logo_path as organizer_logo
        FROM event_drafts d
        LEFT JOIN organizer_profiles o ON d.user_id = o.user_id
        WHERE d.id = :id AND d.status = 'draft'
        LIMIT 1
    ";
    preview_log('🔍 Requête SQL: ' . $query);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $_GET['id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        preview_log('❌ Aucun brouillon trouvé pour l\'ID ' . $_GET['id']);
        die("<div class='alert alert-danger'>Brouillon non trouvé</div>");
    }
    
    preview_log('✅ Brouillon récupéré');
    
    // Définir les variables nécessaires pour event-detail.php
    $additionalStyles = '<link rel="stylesheet" href="/assets/css/event-detail.css">';
    $additionalStyles .= '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
    
    // Inclure d'abord le header
    require_once $rootDir . '/includes/header-solid.php';
    
    // Puis inclure event-detail.php
    require_once $rootDir . '/templates/events/event-detail.php';
    
} catch (Exception $e) {
    preview_log('❌ Erreur: ' . $e->getMessage());
    die("<div class='alert alert-danger'>Une erreur est survenue: " . htmlspecialchars($e->getMessage()) . "</div>");
}
