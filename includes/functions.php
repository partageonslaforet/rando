<?php

/**
 * Vérifie si la requête est une requête AJAX
 * @return bool
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Démarre la session si elle n'est pas déjà démarrée
 */
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        error_log('Initialisation de la session');
        
        // Configurer les paramètres de cookie de session
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; // HTTPS seulement en production
        $httponly = true; // Empêcher l'accès JavaScript
        $samesite = 'Strict'; // Protection CSRF
        
        // Définir le domaine du cookie sans le port (interdit pour les cookies)
        $domain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        
        // Configurer la session
        ini_set('session.cookie_secure', $secure);
        ini_set('session.cookie_httponly', $httponly);
        ini_set('session.use_strict_mode', true);
        ini_set('session.use_only_cookies', true);
        
        // Définir les options du cookie de session
        session_set_cookie_params([
            'lifetime' => 0, // Jusqu'à la fermeture du navigateur
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);
        
        session_start();
        error_log('Session initialisée avec ID: ' . session_id());
        error_log('Session data: ' . print_r($_SESSION, true));
    }
}

/**
 * Vérifie si un utilisateur est connecté
 * @return bool
 */
function isLoggedIn() {
    error_log('Vérification de connexion');
    error_log('SESSION_ID: ' . session_id());
    error_log('SESSION: ' . print_r($_SESSION, true));
    
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur connecté est un administrateur
 * @return bool
 */
function isAdmin() {
    error_log(' Vérification admin: ' . (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' ? 'Admin' : 'Non admin'));
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 * @param string $role
 * @return bool
 */
function hasRole($role) {
    error_log(' Vérification rôle ' . $role . ': ' . (isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role ? 'Oui' : 'Non'));
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Obtient l'ID de l'utilisateur connecté
 * @return int|null
 */
function getCurrentUserId() {
    error_log(' Récupération ID utilisateur: ' . ($_SESSION['user_id'] ?? 'Non connecté'));
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtient le rôle de l'utilisateur connecté
 * @return string|null
 */
function getCurrentUserRole() {
    error_log(' Récupération rôle utilisateur: ' . ($_SESSION['user_role'] ?? 'Non défini'));
    return $_SESSION['user_role'] ?? null;
}

/**
 * Obtient l'email de l'utilisateur connecté
 * @return string|null
 */
function getCurrentUserEmail() {
    error_log(' Récupération email utilisateur: ' . ($_SESSION['user_email'] ?? 'Non défini'));
    return $_SESSION['user_email'] ?? null;
}

/**
 * Vérifie si l'utilisateur a accès à une ressource
 * @param string $resource
 * @return bool
 */
function canAccess($resource) {
    error_log(' Vérification accès à ' . $resource);
    
    if (!isLoggedIn()) {
        error_log(' Accès refusé: non connecté');
        return false;
    }

    // L'administrateur a accès à tout
    if (isAdmin()) {
        error_log(' Accès accordé: admin');
        return true;
    }

    // Ajouter ici d'autres règles d'accès selon les besoins
    $hasAccess = false;
    switch ($resource) {
        case 'profile':
            $hasAccess = true;
            break;
        case 'admin':
            $hasAccess = isAdmin();
            break;
        default:
            $hasAccess = false;
    }
    
    error_log(' Résultat accès à ' . $resource . ': ' . ($hasAccess ? 'Accordé' : 'Refusé'));
    return $hasAccess;
}

/**
 * Redirige vers la page d'accueil si l'utilisateur n'a pas accès
 * @param string $resource
 */
function requireAccess($resource) {
    error_log(' Vérification accès requis pour ' . $resource);
    if (!canAccess($resource)) {
        error_log(' Accès refusé, redirection vers la page d\'accueil');
        header('Location: /');
        exit();
    }
    error_log(' Accès accordé pour ' . $resource);
}

/**
 * Redirige si non connecté
 */
function requireLogin() {
    if (!isLoggedIn()) {
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Session expirée. Veuillez vous reconnecter.'
            ]);
            exit;
        } else {
            header('Location: /login.php');
            exit;
        }
    }
    error_log(' Accès autorisé - Utilisateur: ' . getCurrentUserId());
}

/**
 * Redirige si non admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        error_log(' Accès refusé - Utilisateur non admin');
        header('Location: /');
        exit;
    }
    error_log(' Accès autorisé - Admin: ' . getCurrentUserId());
}

/**
 * Traite un fichier GPX uploadé
 * @param array $file Tableau contenant les informations du fichier ($_FILES)
 * @return string|false Chemin du fichier GPX ou false en cas d'erreur
 */
function processGpxFile($file) {
    // Vérifier si le fichier a été correctement uploadé
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Erreur lors de l'upload du fichier GPX: " . $file['error']);
        return false;
    }

    // Vérifier le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Liste des types MIME autorisés pour les fichiers GPX
    $allowedMimeTypes = [
        'application/xml',
        'application/gpx+xml',
        'text/xml'
    ];

    if (!in_array($mimeType, $allowedMimeTypes)) {
        error_log("Type de fichier GPX non autorisé: " . $mimeType);
        return false;
    }

    // Créer le dossier de destination s'il n'existe pas
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/gpx/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = uniqid('gpx_', true) . '.' . $extension;
    $targetPath = $uploadDir . $newFileName;

    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        error_log("Échec du déplacement du fichier GPX vers " . $targetPath);
        return false;
    }

    // Retourner le chemin relatif
    return '/uploads/gpx/' . $newFileName;
}

// Initialiser la session au chargement du fichier
initSession();
