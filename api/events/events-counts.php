<?php
/**
 * API publique de listing/comptage des événements (filtres, calendrier, etc.).
 *
 * Utilisé par : public/assets/js/filters.js, public/assets/js/events-api.js, templates/js/calendar*.js
 */

// Désactiver la mise en tampon de sortie si elle est active
if (ob_get_level() > 0) {
    ob_clean();
}
error_log("=== DÉBUT DU SCRIPT events.php ===");
// Activation des erreurs pour le débogage
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Connexion à la base de données via le fichier de configuration
    require_once __DIR__ . '/../../config/database.php';
    // Utilitaires de résolution d'URL publiques pour les images
    require_once __DIR__ . '/../../includes/functions.php';
    // Logger applicatif
    require_once __DIR__ . '/../../logs/error.log.php';
    $pdo = getConnection();

    // Gérer les CORS
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Vérifier si c'est une requête pour le compteur d'événements
    if (isset($_GET['count'])) {
        // ... code existant pour le compteur ...
    } else {
        error_log("=== DÉBUT DU TRAITEMENT DE LA REQUÊTE ===");
        
        // Construction de la requête de base
        $baseQuery = "FROM events e LEFT JOIN event_categories c ON e.category_id = c.id WHERE 1=1";
        $params = [];

        // Ne montrer que les événements approuvés (aligné avec EventManager)
        $baseQuery .= " AND e.status = 'approved'";

        // Récupérer les paramètres de filtrage
        $type = $_GET['type'] ?? 'all';
        $period = $_GET['period'] ?? 'all';
        $category = $_GET['category'] ?? 'all'; // Nouveau paramètre
        $calendar = isset($_GET['calendar']) && $_GET['calendar'] === 'true';
        $search = $_GET['search'] ?? '';
        $month = isset($_GET['month']) ? intval($_GET['month']) : null;
        $year = isset($_GET['year']) ? intval($_GET['year']) : null;

        error_log("=== PARAMÈTRES REÇUS ===");
        error_log("Type: " . $type);
        error_log("Period: " . $period);
        error_log("Category: " . $category); // Log de la catégorie
        error_log("Calendar: " . ($calendar ? 'true' : 'false'));
        error_log("Search: " . $search);
        error_log("Month: " . ($month ?? 'null'));
        error_log("Year: " . ($year ?? 'null'));

        // Filtre par type
        if ($type && $type !== 'all') {
            $baseQuery .= " AND c.code = :type";
            $params[':type'] = $type;
        }

        // Filtre par catégorie (au moins un tag correspondant)
        if ($category && $category !== 'all') {
            $baseQuery .= " AND e.id IN (SELECT event_id FROM event_category_links WHERE category_id = :category)";
            $params[':category'] = $category;
        }

        // Filtre par période
        if ($period !== 'all') {
            switch ($period) {
                case 'today':
                    $baseQuery .= " AND DATE(e.date) = CURDATE()";
                    break;
                case 'past':
                    $baseQuery .= " AND e.date < CURDATE()";
                    break;
                case 'upcoming':
                    $baseQuery .= " AND e.date >= CURDATE()";
                    break;
            }
        }

        // Filtre par recherche élargi (titre, description, organisation, lieu, salle, RV)
        if (!empty($search)) {
            $baseQuery .= " AND (
                e.title LIKE :s1
                OR e.description LIKE :s2
                OR e.organisation LIKE :s3
                OR e.location LIKE :s4
                OR e.venue LIKE :s5
                OR e.meeting_name LIKE :s6
                OR e.meeting_address LIKE :s7
                OR e.meeting_city LIKE :s8
            )";
            $like = '%' . $search . '%';
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
            $params[':s4'] = $like;
            $params[':s5'] = $like;
            $params[':s6'] = $like;
            $params[':s7'] = $like;
            $params[':s8'] = $like;
        }

        // Filtre par mois et année si fournis (pour le calendrier)
   /*      if ($calendar && $month !== null && $year !== null) {
            $baseQuery .= " AND MONTH(e.date) = :month AND YEAR(e.date) = :year";
            $params[':month'] = $month;
            $params[':year'] = $year;
        } */

        if ($calendar && $month !== null && $year !== null) {
            $baseQuery .= " AND DATE(CONVERT_TZ(e.date, 'UTC', '+01:00')) >= :start_date 
                            AND DATE(CONVERT_TZ(e.date, 'UTC', '+01:00')) <= :end_date";
            
            // Premier et dernier jour du mois
            $start_date = sprintf('%04d-%02d-01', $year, $month);
            $end_date = date('Y-m-t', strtotime($start_date));
            
            $params[':start_date'] = $start_date;
            $params[':end_date'] = $end_date;
        }

        error_log("=== DEBUG SQL ===");
        error_log("Base Query: " . $baseQuery);
        error_log("Params: " . print_r($params, true));

        // Pagination (Option B) uniquement si demandé
        $paginate = isset($_GET['page']) || isset($_GET['limit']);
        $page = null; $limit = null; $offset = null; $total = null; $total_pages = null;
        if ($paginate) {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 12;
            $offset = ($page - 1) * $limit;

            // Total pour pagination
            $countQuery = "SELECT COUNT(*) " . $baseQuery;
            $countStmt = $pdo->prepare($countQuery);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            $total_pages = (int)ceil(($total ?: 0) / $limit);

            // Requête avec LIMIT/OFFSET
            $query = "SELECT e.*, c.code as category, c.name as category_name, c.icon as category_icon, c.color as category_color " . $baseQuery . " ORDER BY e.date ASC LIMIT :limit OFFSET :offset";
        } else {
            // Requête complète sans pagination
            $query = "SELECT e.*, c.code as category, c.name as category_name, c.icon as category_icon, c.color as category_color " . $baseQuery . " ORDER BY e.date ASC";
        }

        try {
            $stmt = $pdo->prepare($query);
            // Lier les paramètres dynamiques du WHERE
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            // Lier LIMIT/OFFSET uniquement si pagination activée
            if ($paginate) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
            $stmt->execute();
            $events = $stmt->fetchAll();

            // Tags liés à chaque événement (event_category_links)
            $eventTags = [];
            if (!empty($events)) {
                // Ajouter views_total (toutes sources, sans période) depuis site_visits
                try {
                    foreach ($events as &$ev) {
                        $eid = (int)($ev['id'] ?? 0);
                        $ev['views_total'] = $eid > 0 ? getEventTotalViews($pdo, $eid) : 0;
                    }
                    unset($ev);

                } catch (Throwable $e) {
                    if (function_exists('logError')) {
                        logError('api/events-counts', 'views_total compute failed', ['error' => $e->getMessage()]);
                    }
                    foreach ($events as &$ev) { $ev['views_total'] = null; } unset($ev);
                }

                $eventIds = array_column($events, 'id');
                $tagsStmt = $pdo->prepare("SELECT ecl.event_id, c.id, c.name, c.code, c.icon, c.color
                    FROM event_category_links ecl
                    JOIN event_categories c ON ecl.category_id = c.id
                    WHERE ecl.event_id IN (" . implode(',', array_fill(0, count($eventIds), '?')) . ")
                    ORDER BY c.name ASC");
                $tagsStmt->execute($eventIds);
                foreach ($tagsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $eid = (int)$row['event_id'];
                    unset($row['event_id']);
                    $eventTags[$eid][] = $row;
                }
            }

            if (!empty($events)) {
                foreach ($events as &$event) {
                    $eid = (int)$event['id'];
                    $tags = $eventTags[$eid] ?? [];
                    $event['category_ids'] = array_map(fn($c) => (int)$c['id'], $tags);
                    $event['categories'] = $tags;
                    if (empty($tags)) {
                        $mainCategoryId = (int) ($event['category_id'] ?? 0);
                        $event['category_ids'] = $mainCategoryId ? [$mainCategoryId] : [];
                        $event['categories'] = $mainCategoryId ? [[
                            'id' => $mainCategoryId,
                            'name' => $event['category_name'] ?? null,
                            'icon' => $event['category_icon'] ?? null,
                            'color' => $event['category_color'] ?? null,
                            'code' => $event['category'] ?? null
                        ]] : [];
                    }
                }
                unset($event);
            }


            // Récupérer les images principales (priorité is_main, fallback à la 1ère image)
            if (!empty($events)) {
                $eventIds = array_column($events, 'id');

                // Sélectionner toutes les images des événements demandés, triées par event_id, is_main DESC puis id ASC
                $imagesQuery = "SELECT event_id AS id, image_path, storage_path, is_main
                                 FROM event_images
                                 WHERE event_id IN (" . implode(',', array_fill(0, count($eventIds), '?')) . ")
                                 ORDER BY event_id, is_main DESC, id ASC";

                $imageStmt = $pdo->prepare($imagesQuery);
                $imageStmt->execute($eventIds);
                $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

                // Conserver la 1ère image par event_id (grâce au tri, c'est l'image principale si elle existe)
                $imageMap = [];
                foreach ($images as $row) {
                    $eid = (int)$row['id'];
                    if (!isset($imageMap[$eid])) {
                        $imageMap[$eid] = $row; // première occurrence = is_main ou 1ère image
                    }
                }

                // Joindre l'URL publique résolue à chaque événement
                foreach ($events as &$event) {
                    $eid = (int)$event['id'];
                    $imgRow = $imageMap[$eid] ?? null;

                    // 1) URL depuis table event_images (prioritaire)
                    $publicUrl = null;
                    if ($imgRow) {
                        $publicUrl = resolveImagePublicUrl($imgRow['image_path'] ?? null, $imgRow['storage_path'] ?? null);
                    }

                    // 2) Fallback: colonnes legacy sur events (vérifié existant)
                    if (!$publicUrl && !empty($event['main_image_path'])) {
                        $publicUrl = resolveImagePublicUrl($event['main_image_path'] ?? null, null);
                    }

                    // 3) Normalisation: s'assurer que le chemin est exploitable côté client
                    if (!empty($publicUrl) && !preg_match('#^(https?://|/)#i', $publicUrl)) {
                        $publicUrl = '/' . ltrim($publicUrl, '/');
                    }

                    // 4) Déterminer l'image par défaut de la catégorie
                    $categoryCode = strtolower($event['category'] ?? '');
                    $categoryName = strtolower($event['category_name'] ?? '');
                    $isCourseAPied = (
                        $categoryCode === 'running'
                        || $categoryCode === 'course-a-pied'
                        || $categoryCode === 'courseapied'
                        || strpos($categoryName, 'course à pied') !== false
                    );
                    $categoryFallback = $isCourseAPied
                        ? getCourseAPiedFallbackImage()
                        : '/assets/images/events/default-event.jpg';

                    // 5) Si l'image récupérée est une image générique/absente et que la catégorie est course à pied, forcer le fallback
                    $isGeneric = !$publicUrl
                        || !isRealEventImage($publicUrl)
                        || stripos($publicUrl, 'default-event.jpg') !== false
                        || stripos($publicUrl, 'main-hero.jpg') !== false
                        || stripos($publicUrl, 'map-hero.jpg') !== false
                        || stripos($publicUrl, 'coursea') !== false;
                    if ($isCourseAPied && $isGeneric) {
                        $publicUrl = $categoryFallback;
                    }

                    $event['main_image_path'] = $publicUrl ?: $categoryFallback;

                    // Log si aucune image réelle trouvée (debug uniquement)
                    if (!$publicUrl && defined('DEBUG') && DEBUG) {
                        logError('api/events-counts', 'Image principale non résolue', [
                            'event_id' => $eid,
                            'title' => $event['title'] ?? null,
                            'category' => $event['category'] ?? null,
                            'category_name' => $event['category_name'] ?? null,
                            'fallback_used' => $categoryFallback
                        ]);
                    }
                }
                unset($event);
            }

            error_log("Nombre d'événements trouvés: " . count($events));

            // Log clair si la recherche ne renvoie aucun résultat
            if (!empty($search)) {
                if ($paginate && isset($total) && $total === 0) {
                    logError('api/events-counts', 'search_no_results', [
                        'search' => $search,
                        'period' => $period,
                        'category' => $category,
                        'page' => $page,
                        'limit' => $limit
                    ]);
                } elseif (!$paginate && empty($events)) {
                    logError('api/events-counts', 'search_no_results', [
                        'search' => $search,
                        'period' => $period,
                        'category' => $category
                    ]);
                }
            }

            // Requête pour les compteurs par catégorie (basée sur les tags)
            $countQuery = "SELECT ecl.category_id, COUNT(DISTINCT ecl.event_id) as count 
                           FROM event_category_links ecl 
                           JOIN events e ON ecl.event_id = e.id 
                           WHERE e.status = 'approved' 
                           GROUP BY ecl.category_id";
            $countStmt = $pdo->query($countQuery);
            $categoryCounts = $countStmt->fetchAll();

            // Formatage des compteurs
            $counts = ['all' => 0];
            foreach ($categoryCounts as $count) {
                $counts[$count['category_id']] = (int)$count['count'];
            }

            $totalStmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'");
            $counts['all'] = (int)$totalStmt->fetchColumn();

            // Log de diagnostic des événements annulés
            $cancelledEvents = array_filter($events, fn($e) => (int)($e['is_cancelled'] ?? 0) === 1);
            if (!empty($cancelledEvents) && defined('DEBUG') && DEBUG) {
                logError('api/events-counts', 'Evénements annulés retournés', [
                    'ids' => array_column($cancelledEvents, 'id'),
                    'titles' => array_column($cancelledEvents, 'title'),
                    'count' => count($events),
                    'params' => $params
                ]);
            }

            // Préparation de la réponse
            $response = [
                'status' => 'success',
                'data' => array_values($events),
                'counts' => $counts,
            ];
            if ($paginate) {
                $response['pagination'] = [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $total_pages,
                ];
            }

            // Envoi de la réponse
            echo json_encode($response, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
            exit;

        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw $e;
        }
    }

} catch (Throwable $e) {
    $msg = $e->getMessage();
    $file = $e->getFile();
    $line = $e->getLine();
    $trace = $e->getTraceAsString();

    if (function_exists('logError')) {
        logError('api/events-counts', 'Unhandled error', [
            'message' => $msg,
            'file' => $file,
            'line' => $line,
            'trace' => $trace
        ]);
    }
    error_log("Erreur dans events.php: " . $msg . " | " . $file . ":" . $line);
    error_log("Trace: " . $trace);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Une erreur est survenue lors de la récupération des événements',
        'debug' => [
            'error' => $msg,
            'file' => $file,
            'line' => $line
        ]
    ], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    exit;
}
?>