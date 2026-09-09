<?php
/**
 * Renvoie le profil organisateur de l'utilisateur connecté au format JSON.
 * Utilisé pour l'auto-complétion du formulaire événement et la page profil.
 */

// En-têtes pour éviter la mise en cache
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json; charset=utf-8');

error_log(" Début get_profile.php");

try {
    // 1. Démarrer la session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    error_log(" Session ID: " . session_id());
    
    // 2. Charger les dépendances
    $rootDir = dirname(dirname(__DIR__));
    if (!file_exists($rootDir . '/includes/init.php')) {
        throw new Exception('Erreur de configuration');
    }
    require_once $rootDir . '/includes/init.php';
    error_log(" init.php chargé");

    if (!file_exists($rootDir . '/src/Models/organizer_profile.php')) {
        throw new Exception('Erreur de configuration');
    }
    require_once $rootDir . '/src/Models/organizer_profile.php';
    error_log(" organizer_profile.php chargé");

    // 3. Vérifier la connexion à la base de données
    if (!isset($db)) {
        error_log(" Variable db non définie");
        throw new Exception('Erreur de base de données');
    }
    error_log(" Connexion DB OK");

    // 4. Vérifier l'authentification
    if (!isset($_SESSION['user_id'])) {
        error_log(" Non authentifié (user_id non défini)");
        throw new Exception('Non authentifié');
    }
    error_log(" Authentification OK - User ID: " . $_SESSION['user_id']);

    // 5. Initialiser le gestionnaire de profil
    $organizerManager = new OrganizerProfile($db);

    // 6. Récupérer le profil
    $profile = [
        'name' => '',
        'address' => '',
        'description' => '',
        'website' => '',
        'phone' => '',
        'email' => '',
        'logo_path' => '',
        'storage_path' => ''
    ];

    // Si un ID spécifique est fourni, récupérer ce profil
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $existingProfile = $organizerManager->getById($_GET['id']);
        error_log("Recherche du profil ID: " . $_GET['id']);
    } else {
        // Sinon, récupérer les informations de l'utilisateur
        $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $existingProfile = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Utilisation des informations utilisateur");
    }
    
    if ($existingProfile) {
        error_log(" Profil existant trouvé");
        $profile = array_merge($profile, [
            'name' => $existingProfile['name'] ?? '',
            'email' => $existingProfile['email'] ?? '',
            'address' => $existingProfile['address'] ?? '',
            'description' => $existingProfile['description'] ?? '',
            'website' => $existingProfile['website'] ?? '',
            'phone' => $existingProfile['phone'] ?? '',
            'logo_path' => $existingProfile['logo_path'] ?? '',
            'storage_path' => $existingProfile['storage_path'] ?? ''
        ]);
    } else {
        error_log(" Aucun profil existant trouvé");
    }

    error_log(" Profil final: " . print_r($profile, true));

    // 7. Renvoyer la réponse
    echo json_encode([
        'success' => true,
        'message' => 'Profil récupéré avec succès',
        'profile' => $profile
    ]);
    error_log(" Réponse JSON envoyée avec succès");

} catch (Exception $e) {
    $msg = $e->getMessage();
    $code = in_array($msg, ['Non autorisé', 'Non authentifié'], true) ? 401 : 500;
    error_log(" Erreur dans get_profile.php: " . $msg);
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $msg
    ]);
}
