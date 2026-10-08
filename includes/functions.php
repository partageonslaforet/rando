<?php
/**
 * Fonctions utilitaires globales de l'application.
 */
if (defined('PLF_FUNCTIONS_LOADED')) {
    return;
}
define('PLF_FUNCTIONS_LOADED', true);

require_once __DIR__ . '/../src/Services/Storage.php';
require_once __DIR__ . '/../logs/error.log.php';

// Fonction utilitaire : sponsors actifs pour l'affichage (card événement, footer accueil)
// Retourne [] si la table est absente (migration non jouée) ou en cas d'erreur.
function getActiveSponsors(?PDO $pdo): array {
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $rows = $pdo->query(
            'SELECT id, name, image_path, link_url, alt_text FROM sponsors
             WHERE active = 1
               AND (start_date IS NULL OR start_date <= CURDATE())
               AND (end_date IS NULL OR end_date >= CURDATE())
             ORDER BY position ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Même logique que les images d'événements : on vérifie que le visuel
        // existe réellement (ou URL externe) avant de l'afficher.
        foreach ($rows as $i => $row) {
            $resolved = resolveImagePublicUrl($row['image_path'] ?? '', null);
            if ($resolved === null) {
                unset($rows[$i]);
                continue;
            }
            $rows[$i]['image_path'] = $resolved;
        }
        return array_values($rows);
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('includes/functions.php', 'getActiveSponsors failed', ['error' => $e->getMessage()]);
        }
        return [];
    }
}

// Fonction utilitaire pour compter les vues exactes d'un événement
function getEventTotalViews(PDO $pdo, int $eventId): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM site_visits WHERE url = :url1 OR url = :url2"
    );
    $stmt->execute([
        'url1' => '/event?id=' . $eventId,
        'url2' => '/event/' . $eventId
    ]);
    return (int)$stmt->fetchColumn();
}

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
 * Enregistre une visite de page pour les statistiques de fréquentation.
 * Ignore silencieusement les erreurs (ne doit jamais casser l'affichage d'une page).
 * @param string $url Chemin de la page visitée (ex: '/', '/events', '/event?id=12')
 */
function trackVisit(string $url): void {
    try {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Ignore les ressources statiques (favicon, images, polices, etc.) qui faussent les stats
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $staticExts = ['png','jpg','jpeg','gif','svg','ico','css','js','txt','xml','json','woff','woff2','ttf','eot','map','pdf','webp','bmp','mp4','mp3','webm','ogg','avif','zip','gz','tar'];
        if (in_array($ext, $staticExts, true)) {
            return;
        }

        // Ne jamais stocker de paramètres sensibles dans les stats
        $parts = parse_url($url);
        if ($parts !== false && !empty($parts['query'])) {
            parse_str($parts['query'], $params);
            foreach (['password', 'password_confirm', 'passwd', 'pwd', 'csrf_token', 'token', 'email'] as $k) {
                unset($params[$k]);
            }
            $url = ($parts['path'] ?? '/') . ($params ? '?' . http_build_query($params) : '');
        }

        // Filtre basique anti-bot / crawler
        if ($userAgent !== '' && preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $userAgent)) {
            return;
        }

        // Détection outils / requêtes non humaines
        $isHuman = 1;
        if ($userAgent === '') {
            $isHuman = 0;
        } elseif (preg_match('/Postman|curl|wget|python-requests|python-urllib|axios|node-fetch|got\(|httpie|insomnia|java\/|Apache-HttpClient|Go-http-client|okhttp|libwww-perl|Guzzle|GuzzleHttp/i', $userAgent)) {
            $isHuman = 0;
        }

        $pdo = getConnection();
        $visitValues = [
            substr($url, 0, 255),
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($userAgent, 0, 255),
            isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 255) : null,
            $_SESSION['user_id'] ?? null,
            session_id() ?: null,
            $isHuman,
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO site_visits (url, ip_address, user_agent, referrer, user_id, session_id, is_human, visited_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        try {
            $stmt->execute($visitValues);
        } catch (Throwable $insertErr) {
            // Fallback si la colonne is_human n'est pas encore déployée
            if (strpos($insertErr->getMessage(), "Unknown column 'is_human'") !== false) {
                $stmt = $pdo->prepare(
                    'INSERT INTO site_visits (url, ip_address, user_agent, referrer, user_id, session_id, visited_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())'
                );
                array_pop($visitValues); // retire is_human
                $stmt->execute($visitValues);
            } else {
                throw $insertErr;
            }
        }
    } catch (Throwable $e) {
        logError('includes/functions.php', 'trackVisit failed: ' . $e->getMessage(), ['url' => $url]);
    }
}

/**
 * Résout pays/ville d'une IP, avec cache en base (table ip_geo_cache).
 * N'appelle l'API externe que si l'IP n'est pas déjà en cache.
 * @param string $ip
 * @return array{country: ?string, city: ?string}
 */
function resolveIpLocation(string $ip): array {
    $default = ['country' => null, 'city' => null];
    if ($ip === '' || in_array($ip, ['127.0.0.1', '::1'], true)) {
        return ['country' => 'Local', 'city' => null];
    }

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT country, city FROM ip_geo_cache WHERE ip_address = ?');
        $stmt->execute([$ip]);
        $cached = $stmt->fetch();
        if ($cached) {
            return ['country' => $cached['country'], 'city' => $cached['city']];
        }

        $context = stream_context_create(['http' => ['timeout' => 2]]);
        $response = @file_get_contents(
            'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,city',
            false,
            $context
        );
        $data = $response ? json_decode($response, true) : null;

        $country = ($data && ($data['status'] ?? '') === 'success') ? ($data['country'] ?? null) : null;
        $city = ($data && ($data['status'] ?? '') === 'success') ? ($data['city'] ?? null) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO ip_geo_cache (ip_address, country, city, resolved_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE country = VALUES(country), city = VALUES(city), resolved_at = NOW()'
        );
        $stmt->execute([$ip, $country, $city]);

        return ['country' => $country, 'city' => $city];
    } catch (Throwable $e) {
        error_log('resolveIpLocation: ' . $e->getMessage());
        return $default;
    }
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
    // Tenter de restaurer la session via le cookie remember_me si besoin
    if (!isLoggedIn()) {
        require_once __DIR__ . '/auth_check.php';
    }

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

    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = uniqid('gpx_', true) . '.' . $extension;
    $targetPath = Storage::getStoragePath('gpx', $newFileName);

    // Créer le dossier de destination s'il n'existe pas
    Storage::ensureDirectoryExists(dirname($targetPath));

    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        error_log("Échec du déplacement du fichier GPX vers " . $targetPath);
        return false;
    }

    // Retourner le chemin relatif
    return Storage::getPublicUrl('gpx', $newFileName);
}

if (!function_exists('getFullUrl')) {
    function getFullUrl(string $path) {
        if (defined('APP_URL') && !empty(APP_URL)) {
            return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $domain . '/' . ltrim($path, '/');
    }
}

if (!function_exists('resolveImagePublicUrl')) {
    function resolveImagePublicUrl(?string $imagePath, ?string $storagePath) {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

        // 1) Si storage_path existe physiquement, on l'utilise
        if (!empty($storagePath) && file_exists($storagePath)) {
            if ($docRoot && strpos($storagePath, $docRoot) === 0) {
                return getFullUrl(substr($storagePath, strlen($docRoot)));
            }
            return $storagePath;
        }

        // 2) Si image_path est une URL externe, on l'accepte telle quelle
        if (!empty($imagePath) && preg_match('#^https?://#i', $imagePath)) {
            return $imagePath;
        }

        // 3) Si image_path est un chemin local, vérifier que le fichier existe.
        // Le docroot peut être la racine du projet (alias .htaccess /assets et
        // /uploads → /public/...) ou directement public/ selon l'environnement.
        if (!empty($imagePath)) {
            $relative = ltrim($imagePath, '/');
            $candidates = [];
            if ($docRoot) {
                $candidates[] = $docRoot . '/' . $relative;
                $candidates[] = $docRoot . '/public/' . $relative;
            }
            $candidates[] = dirname(__DIR__) . '/public/' . $relative;
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $imagePath;
                }
            }
        }

        return null;
    }
}

if (!function_exists('getCourseAPiedFallbackImage')) {
    function getCourseAPiedFallbackImage(): string {
        return '/assets/images/course-a-pied.jpg';
    }
}

if (!function_exists('isRealEventImage')) {
    function isRealEventImage(?string $url): bool {
        return !empty($url) && (
            stripos($url, '/uploads/') !== false
            || preg_match('#^https?://#i', $url)
        );
    }
}

if (!function_exists('userHasOrganizerProfile')) {
    /**
     * Indique si l'utilisateur connecté possède au moins un profil organisateur.
     * Résultat mis en cache en session ; invalidé lors de la création/suppression
     * d'un profil via les endpoints api/organization-profil/*.
     * En cas d'erreur DB, retourne true (on préfère ne pas afficher de pastille erronée).
     */
    function userHasOrganizerProfile(): bool {
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        if (isset($_SESSION['has_organizer_profile'])) {
            return (bool) $_SESSION['has_organizer_profile'];
        }
        try {
            require_once __DIR__ . '/../config/database.php';
            $stmt = getConnection()->prepare('SELECT 1 FROM organizer_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            $has = (bool) $stmt->fetchColumn();
            $_SESSION['has_organizer_profile'] = $has;
            return $has;
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('includes/functions.php', 'userHasOrganizerProfile failed', ['error' => $e->getMessage()]);
            }
            return true; // Ne pas afficher la pastille en cas d'erreur
        }
    }
}

// Initialiser la session au chargement du fichier
initSession();
