<?php
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
    require_once __DIR__ . '/../config/database.php';
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
        $baseQuery = "FROM events e WHERE 1=1";
        $params = [];

        // Ne montrer que les événements publiés/approuvés
        $baseQuery .= " AND e.status = 'published'";

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
            $baseQuery .= " AND e.category = :type";
            $params[':type'] = $type;
        }

        // Filtre par catégorie
        if ($category && $category !== 'all') {
            $baseQuery .= " AND e.category_id = :category";
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

        // Filtre par recherche
        if (!empty($search)) {
            $baseQuery .= " AND (e.title LIKE :search OR e.description LIKE :search2)";
            $params[':search'] = "%$search%";
            $params[':search2'] = "%$search%";
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

        // Construction de la requête finale
        $query = "SELECT e.* " . $baseQuery . " ORDER BY e.date ASC";

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $events = $stmt->fetchAll();
            
            // Récupérer les catégories pour tous les événements
            if (!empty($events)) {
                $eventIds = array_column($events, 'id');
                $categoriesQuery = "SELECT e.id as event_id, c.name as category_name, c.icon as category_icon, c.color as category_color 
                                FROM events e 
                                LEFT JOIN event_categories c ON e.category_id = c.id 
                                WHERE e.id IN (" . implode(',', array_fill(0, count($eventIds), '?')) . ")";
                
                $categoryStmt = $pdo->prepare($categoriesQuery);
                $categoryStmt->execute($eventIds);
                $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Associer les catégories aux événements
                $categoryMap = [];
                foreach ($categories as $category) {
                    $categoryMap[$category['event_id']] = [
                        'category_name' => $category['category_name'],
                        'category_icon' => $category['category_icon'],
                        'category_color' => $category['category_color']
                    ];
                }
                
                // Ajouter les informations de catégorie à chaque événement
                foreach ($events as &$event) {
                    if (isset($categoryMap[$event['id']])) {
                        $event = array_merge($event, $categoryMap[$event['id']]);
                    }
                }
                unset($event);
            }


            // Récupérer les images principales
            if (!empty($events)) {
                $eventIds = array_column($events, 'id');
                // Modifions la requête pour joindre event_images
                $imagesQuery = "SELECT e.id, 
                    CASE WHEN ei.event_id IS NOT NULL THEN SUBSTRING_INDEX(ei.image_path, '/', -1) ELSE NULL END as event_image
                FROM events e 
                LEFT JOIN event_images ei ON e.id = ei.event_id AND ei.is_main = 1 
                WHERE e.id IN (" . implode(',', array_fill(0, count($eventIds), '?')) . ")";
                
                $imageStmt = $pdo->prepare($imagesQuery);
                $imageStmt->execute($eventIds);
                $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
            
                // Créer un mapping des images
                $imageMap = [];
                    foreach ($images as $image) {
                        $imageMap[$image['id']] = $image['event_image'];  // Changé de main_image_path à event_image
                    }
            
                // Ajouter les images aux événements
                foreach ($events as &$event) {
                    $event['event_image'] = $imageMap[$event['id']] ?? null;  // Changé de main_image_path à event_image
                }
                unset($event);
            }

            error_log("Nombre d'événements trouvés: " . count($events));

            // Requête pour les compteurs par catégorie
            $countQuery = "SELECT category_id, COUNT(*) as count FROM events WHERE status = 'published' GROUP BY category_id";
            $countStmt = $pdo->query($countQuery);
            $categoryCounts = $countStmt->fetchAll();

            // Formatage des compteurs
            $counts = ['all' => 0];
            foreach ($categoryCounts as $count) {
                $counts[$count['category_id']] = (int)$count['count'];
                $counts['all'] += (int)$count['count'];
            }

            // Préparation de la réponse
            $response = [
                'status' => 'success',
                'data' => array_values($events),
                'counts' => $counts
            ];

            // Envoi de la réponse
            echo json_encode($response, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
            exit;

        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw $e;
        }
    }

} catch (Exception $e) {
    error_log("Erreur dans events.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Une erreur est survenue lors de la récupération des événements'
    ]);
    exit;
}
?>