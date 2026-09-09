<?php
/**
 * src/Models/Event.php
 * Role: Modèle principal pour la gestion complète des événements (CRUD, médias, GPX).
 * Usage: new Event($db)
 * Dépendances: src/Services/Storage.php, config/assets.php
 */

require_once __DIR__ . '/../Services/Storage.php';

class Event {
    private $db;
    private $config;
    private const LOG_FILE = '/tmp/rando_debug.log';
    private $defaultImage = '/assets/images/default-event.jpg';
    private $validCategories = ['running', 'hiking', 'cycling'];

    private function log($message, $data = null) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $location = basename($trace['file']) . ':' . $trace['line'];
        $log = "[$location] $message";
        if ($data !== null) {
            $log .= "\nData: " . print_r($data, true);
        }
        error_log($log);
    }

    private function debug_log($message, $data = null) {
        $this->log("[EVENT.PHP] " . $message, $data);
    }

    public function __construct($db) {
        $this->debug_log("Construction de l'objet Event");
        
        // Détection de l'environnement
        $httpHost = $_SERVER['HTTP_HOST'] ?? 'non défini';
        $this->debug_log("Détection de l'environnement", [
            'HTTP_HOST' => $httpHost,
            'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'non défini',
            'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'non défini',
            'DIR' => __DIR__
        ]);

        if (!$db) {
            $this->debug_log("❌ Erreur: La connexion à la base de données est nulle");
            throw new Exception("La connexion à la base de données est requise");
        }
        $this->db = $db;
        $this->debug_log("✓ Connexion à la base de données établie");

        // Chargement de la configuration
        try {
            $configPath = __DIR__ . '/../../config/assets.php';
            $this->debug_log("Tentative de chargement de la configuration", [
                'configPath' => $configPath,
                'exists' => file_exists($configPath) ? 'oui' : 'non',
                'isReadable' => is_readable($configPath) ? 'oui' : 'non',
                'permissions' => file_exists($configPath) ? decoct(fileperms($configPath) & 0777) : 'N/A'
            ]);
            
            if (!file_exists($configPath)) {
                throw new Exception("Le fichier de configuration n'existe pas: " . $configPath);
            }
            
            if (!is_readable($configPath)) {
                throw new Exception("Le fichier de configuration n'est pas lisible: " . $configPath);
            }
            
            $this->config = require $configPath;
            $this->debug_log("✓ Configuration chargée");
        } catch (Exception $e) {
            $this->debug_log("⚠️ Erreur lors du chargement de la configuration", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Récupère l'image par défaut pour un événement
     */
    public function getDefaultImage() {
        $defaultImage = $this->config['images']['event_placeholder'];
        $this->log("Utilisation de l'image par défaut: " . $defaultImage);
        
        // Vérifier si le fichier existe
        $publicRoot = __DIR__ . '/../../public';
        $fullPath = $publicRoot . $defaultImage;
        if (!file_exists($fullPath)) {
            $this->log("❌ ATTENTION: L'image par défaut n'existe pas: " . $fullPath);
            // Fallback sur l'image hero si l'image par défaut n'existe pas
            $defaultImage = $this->config['images']['default_hero'];
            $this->log("↪ Utilisation de l'image de fallback: " . $defaultImage);
        }
        
        return $defaultImage;
    }

    /**
     * Récupère tous les événements avec filtres et pagination
     * 
     * @param array $filters Filtres (category, period, search)
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @return array
     */
    public function getAll($filters = [], $page = 1, $limit = 12) {
        try {
            $this->debug_log("=== DÉBUT GET ALL ===");
            $this->debug_log("Page: " . $page);
            $this->debug_log("Limite: " . $limit);
            $this->debug_log("Filtres: " . print_r($filters, true));
            
            // Construction de la requête de base
            $query = "SELECT 
                     e.id,
                     e.title,
                     e.description,
                     e.date,
                     e.start_time,
                     e.end_time,
                     e.location,
                     e.venue,
                     e.coordinates,
                     e.category,
                     e.status,
                     e.organisation,
                     e.category_id,
                     e.difficulty,
                     e.max_participants,
                     e.user_id,
                     e.main_image_path,
                     u.name as creator_name,
                     c.name as category_name,
                     c.icon as category_icon,
                     c.color as category_color,
                     (SELECT image_path FROM event_images WHERE event_id = e.id ORDER BY is_main DESC, id ASC LIMIT 1) as fallback_image
                     FROM events e 
                     LEFT JOIN users u ON e.user_id = u.id 
                     LEFT JOIN event_categories c ON e.category_id = c.id
                     WHERE e.status = 'published'";
            $params = [];

            // Filtre par catégorie
            if (!empty($filters['category'])) {
                $query .= " AND e.category_id = :category";
                $params[':category'] = $filters['category'];
                $this->debug_log("Filtre catégorie ajouté: " . $filters['category']);
            }

            // Filtre par difficulté
            if (!empty($filters['difficulty'])) {
                $query .= " AND e.difficulty = :difficulty";
                $params[':difficulty'] = $filters['difficulty'];
                $this->debug_log("Filtre difficulté ajouté: " . $filters['difficulty']);
            }

            // Filtre par recherche
            if (!empty($filters['search'])) {
                $query .= " AND (e.title LIKE :search OR e.description LIKE :search2 
                           OR e.location LIKE :search3 OR e.organisation LIKE :search4)";
                $search = '%' . $filters['search'] . '%';
                $params[':search'] = $search;
                $params[':search2'] = $search;
                $params[':search3'] = $search;
                $params[':search4'] = $search;
                $this->debug_log("Filtre recherche ajouté: " . $filters['search']);
            }

            // Filtre par période
            if (!empty($filters['period'])) {
                switch ($filters['period']) {
                    case 'today':
                        $query .= " AND DATE(e.date) = CURDATE()";
                        break;
                    case 'past':
                        $query .= " AND (e.date < CURDATE() OR (e.date = CURDATE() AND e.end_time < CURTIME()))";
                        break;
                    case 'upcoming':
                        $query .= " AND (e.date > CURDATE() OR (e.date = CURDATE() AND e.start_time >= CURTIME()))";
                        break;
                    default:
                        $query .= " AND (e.date > CURDATE() OR (e.date = CURDATE() AND e.start_time >= CURTIME()))";
                        break;
                }
                $this->debug_log("Filtre période ajouté: " . $filters['period']);
            } else {
                $query .= " AND (e.date > CURDATE() OR (e.date = CURDATE() AND e.start_time >= CURTIME()))";
            }

            // Compter le total avant la pagination
            $countQuery = "SELECT COUNT(*) FROM events e 
                          LEFT JOIN users u ON e.user_id = u.id 
                          LEFT JOIN event_categories c ON e.category_id = c.id
                          WHERE e.status = 'published'";
            
            // Ajouter les mêmes conditions que la requête principale
            if (!empty($filters['category'])) {
                $countQuery .= " AND e.category_id = :category";
            }
            if (!empty($filters['difficulty'])) {
                $countQuery .= " AND e.difficulty = :difficulty";
            }
            if (!empty($filters['search'])) {
                $countQuery .= " AND (e.title LIKE :search OR e.description LIKE :search2 
                               OR e.location LIKE :search3 OR e.organisation LIKE :search4)";
            }
            if (!empty($filters['period'])) {
                switch ($filters['period']) {
                    case 'today':
                        $countQuery .= " AND DATE(e.date) = CURDATE()";
                        break;
                    case 'past':
                        $countQuery .= " AND (e.date < CURDATE() OR (e.date = CURDATE() AND e.end_time < CURTIME()))";
                        break;
                    case 'upcoming':
                        $countQuery .= " AND (e.date > CURDATE() OR (e.date = CURDATE() AND e.start_time >= CURTIME()))";
                        break;
                    default:
                        $countQuery .= " AND (e.date > CURDATE() OR (e.date = CURDATE() AND e.start_time >= CURTIME()))";
                        break;
                }
            } else {
                $countQuery .= " AND (e.date > CURDATE() OR (e.date = CURDATE() AND e.start_time >= CURTIME()))";
            }
            
            $stmt = $this->db->prepare($countQuery);
            foreach ($params as $key => $value) {
                if ($key !== ':limit' && $key !== ':offset') {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->execute();
            $total = $stmt->fetchColumn();
            
            $this->debug_log("Nombre total d'événements trouvés: " . $total);

            // Ajouter le tri et la pagination
            $query .= " ORDER BY e.date ASC, e.start_time ASC";
            $query .= " LIMIT :limit OFFSET :offset";
            $offset = ($page - 1) * $limit;
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $this->debug_log("Offset calculé: " . $offset);
            $this->debug_log("Requête finale: " . $query);
            $this->debug_log("Paramètres: " . print_r($params, true));

            // Exécuter la requête principale
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':offset') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // (logs de debug supprimés)

            $this->debug_log("Nombre d'événements récupérés: " . count($events));
            $this->debug_log("=== FIN GET ALL ===");

            return [
                'events' => $events,
                'total_pages' => ceil($total / $limit),
                'current_page' => $page,
                'total_events' => $total
            ];

        } catch (Exception $e) {
            $this->debug_log("❌ Erreur dans getAll: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère un événement par son ID
     */
    public function getById($id) {
        try {
            $this->debug_log("Début getById pour l'événement #" . $id);
            $this->log("Récupération de l'événement #" . $id);

            $stmt = $this->db->prepare("
                SELECT e.*, u.name as organizer_name, u.email as organizer_email
                FROM events e 
                LEFT JOIN users u ON e.user_id = u.id 
                WHERE e.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                $this->log("❌ Événement #" . $id . " non trouvé");
                $this->debug_log("❌ Événement #" . $id . " non trouvé");
                return null;
            }

            // Ajouter l'image par défaut si nécessaire
            if (empty($event['main_image'])) {
                $event['main_image'] = $this->getDefaultImage();
                $this->log("Image par défaut ajoutée pour l'événement #" . $id);
                $this->debug_log("Image par défaut ajoutée pour l'événement #" . $id);
            }

            $this->log("✓ Événement #" . $id . " récupéré avec succès");
            $this->debug_log("✓ Événement #" . $id . " récupéré avec succès");
            return $event;
        } catch (Exception $e) {
            $this->log("❌ Erreur dans getById: " . $e->getMessage());
            $this->debug_log("❌ Erreur dans getById: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère un événement par son ID
     */
    public function get($id) {
        try {
            // Récupérer l'événement
            $sql = "SELECT * FROM events WHERE id = :id AND status = 'published'";
            $this->log("SQL query for get method: " . $sql);
            $this->debug_log("SQL query for get method: " . $sql);
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $event = $stmt->fetch();

            if (!$event) {
                return null;
            }

            // Récupérer les images
            $sql = "SELECT * FROM event_images WHERE event_id = :event_id ORDER BY is_main DESC";
            $this->log("SQL query for images: " . $sql);
            $this->debug_log("SQL query for images: " . $sql);
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['event_id' => $id]);
            $event['images'] = $stmt->fetchAll();

            // Récupérer les parcours
            $sql = "SELECT * FROM event_routes WHERE event_id = :event_id ORDER BY id ASC";
            $this->log("SQL query for routes: " . $sql);
            $this->debug_log("SQL query for routes: " . $sql);
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['event_id' => $id]);
            $event['routes'] = $stmt->fetchAll();

            $this->log("Event retrieved: " . print_r($event, true));
            $this->debug_log("Event retrieved: " . print_r($event, true));

            return $event;
        } catch (Exception $e) {
            $this->log("Database error in get method: " . $e->getMessage());
            $this->debug_log("Database error in get method: " . $e->getMessage());
            throw $e;
        }
    }

    private function geocodeLocation($location) {
        try {
            // Ajouter un User-Agent comme requis par Nominatim
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: PartageonsLaForet/1.0'
                    ]
                ]
            ];
            $context = stream_context_create($opts);

            // Encoder l'adresse pour l'URL
            $encodedLocation = urlencode($location);
            
            // Faire la requête à Nominatim
            $url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedLocation}";
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                $this->log("Erreur lors du géocodage de l'adresse: " . $location);
                $this->debug_log("Erreur lors du géocodage de l'adresse: " . $location);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!empty($data)) {
                // Format: "lat,lng"
                return $data[0]['lat'] . ',' . $data[0]['lon'];
            }
            
            return null;
        } catch (Exception $e) {
            $this->log("Exception lors du géocodage: " . $e->getMessage());
            $this->debug_log("Exception lors du géocodage: " . $e->getMessage());
            return null;
        }
    }

    public function create($data) {
        try {
            $this->db->beginTransaction();

            // Validation de la catégorie
            $category = strtolower(trim($data['category'] ?? ''));
            if (empty($category)) {
                // Détection automatique de la catégorie VTT
                if (stripos($data['title'], 'vtt') !== false) {
                    $category = 'cycling';
                } else {
                    $category = 'hiking'; // Catégorie par défaut
                }
            } elseif (!in_array($category, $this->validCategories)) {
                $category = 'hiking'; // Si la catégorie n'est pas valide, on met hiking par défaut
            }

            // Géocoder l'adresse si elle est fournie
            $coordinates = null;
            if (!empty($data['location'])) {
                $coordinates = $this->geocodeLocation($data['location']);
                // Attendre 1 seconde pour respecter les limites de Nominatim
                sleep(1);
            }

            // Préparation des données de l'événement
            $eventData = [
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $category, // Utilisation de la catégorie validée
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'] ?? null,
                'location' => $data['location'],
                'venue' => $data['venue'] ?? null,
                'coordinates' => $coordinates,
                'difficulty' => $data['difficulty'] ?? null,
                'max_participants' => $data['max_participants'] ?? null,
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->log("Event data: " . print_r($eventData, true));
            $this->debug_log("Event data: " . print_r($eventData, true));

            // Insertion de l'événement
            $sql = "INSERT INTO events (title, description, category, date, start_time, end_time, 
                    location, venue, coordinates, difficulty, max_participants, created_by, created_at) 
                    VALUES (:title, :description, :category, :date, :start_time, :end_time, 
                    :location, :venue, :coordinates, :difficulty, :max_participants, :created_by, :created_at)";
            
            $this->log("SQL query for create method: " . $sql);
            $this->debug_log("SQL query for create method: " . $sql);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($eventData);
            $eventId = $this->db->lastInsertId();

            $this->log("Event created with ID: " . $eventId);
            $this->debug_log("Event created with ID: " . $eventId);

            // Traitement des parcours
            if (!empty($data['routes'])) {
                foreach ($data['routes'] as $route) {
                    $gpxPath = null;
                    if (!empty($route['gpx_file']['tmp_name'])) {
                        $gpxPath = $this->uploadGpxFile($route['gpx_file'], $eventId);
                    }

                    $routeData = [
                        'event_id' => $eventId,
                        'name' => $route['name'],
                        'distance' => $route['distance'] ?? null,
                        'elevation' => $route['elevation'] ?? null,
                        'gpx_file' => $gpxPath,
                        'price' => $route['price'] ?? 0
                    ];

                    $this->log("Route data: " . print_r($routeData, true));
                    $this->debug_log("Route data: " . print_r($routeData, true));

                    $sql = "INSERT INTO event_routes (event_id, name, distance, elevation, gpx_file, price) 
                            VALUES (:event_id, :name, :distance, :elevation, :gpx_file, :price)";
                    $this->log("SQL query for route: " . $sql);
                    $this->debug_log("SQL query for route: " . $sql);
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($routeData);
                }
            }

            // Traitement des images
            if (!empty($data['images'])) {
                $uploadedImages = $this->uploadImages($data['images'], $eventId);
                if (!empty($uploadedImages)) {
                    foreach ($uploadedImages as $index => $imagePath) {
                        $isMain = ($index === 0); // La première image est l'image principale par défaut
                        $sql = "INSERT INTO event_images (event_id, image_path, is_main) 
                                VALUES (:event_id, :image_path, :is_main)";
                        $this->log("SQL query for image: " . $sql);
                        $this->debug_log("SQL query for image: " . $sql);
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            'event_id' => $eventId,
                            'image_path' => $imagePath,
                            'is_main' => $isMain
                        ]);
                    }
                }
            }

            $this->db->commit();
            $this->log("Event created successfully");
            $this->debug_log("Event created successfully");
            return $eventId;

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->log("Database error in create method: " . $e->getMessage());
            $this->debug_log("Database error in create method: " . $e->getMessage());
            throw $e;
        }
    }

    public function update($eventId, $data) {
        try {
            $this->db->beginTransaction();

            // Géocoder la nouvelle adresse si elle a changé
            $coordinates = null;
            if (!empty($data['location'])) {
                $currentEvent = $this->get($eventId);
                if ($currentEvent && $currentEvent['location'] !== $data['location']) {
                    $coordinates = $this->geocodeLocation($data['location']);
                    // Attendre 1 seconde pour respecter les limites de Nominatim
                    sleep(1);
                } else if ($currentEvent) {
                    // Garder les coordonnées existantes
                    $coordinates = $currentEvent['coordinates'];
                }
            }

            // Préparation des données de l'événement
            $eventData = [
                'id' => $eventId,
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'] ?? null,
                'location' => $data['location'],
                'venue' => $data['venue'] ?? null,
                'coordinates' => $coordinates,
                'difficulty' => $data['difficulty'] ?? null,
                'max_participants' => $data['max_participants'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->log("Event data: " . print_r($eventData, true));
            $this->debug_log("Event data: " . print_r($eventData, true));

            $sql = "UPDATE events SET 
                    title = :title, 
                    description = :description,
                    category = :category,
                    date = :date,
                    start_time = :start_time,
                    end_time = :end_time,
                    location = :location,
                    venue = :venue,
                    coordinates = :coordinates,
                    difficulty = :difficulty,
                    max_participants = :max_participants,
                    updated_at = :updated_at
                    WHERE id = :id";
            
            $this->log("SQL query for update method: " . $sql);
            $this->debug_log("SQL query for update method: " . $sql);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($eventData);

            // Mise à jour des parcours
            // Suppression des anciens parcours
            $sql = "DELETE FROM event_routes WHERE event_id = :event_id";
            $this->log("SQL query for delete routes: " . $sql);
            $this->debug_log("SQL query for delete routes: " . $sql);
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['event_id' => $eventId]);

            // Ajout des nouveaux parcours
            if (!empty($data['routes'])) {
                foreach ($data['routes'] as $route) {
                    $gpxPath = null;
                    if (!empty($route['gpx_file']['tmp_name'])) {
                        $gpxPath = $this->uploadGpxFile($route['gpx_file'], $eventId);
                    } elseif (!empty($route['existing_gpx'])) {
                        $gpxPath = $route['existing_gpx'];
                    }

                    $routeData = [
                        'event_id' => $eventId,
                        'name' => $route['name'],
                        'distance' => $route['distance'] ?? null,
                        'elevation' => $route['elevation'] ?? null,
                        'gpx_file' => $gpxPath,
                        'price' => $route['price'] ?? 0
                    ];

                    $this->log("Route data: " . print_r($routeData, true));
                    $this->debug_log("Route data: " . print_r($routeData, true));

                    $sql = "INSERT INTO event_routes (event_id, name, distance, elevation, gpx_file, price) 
                            VALUES (:event_id, :name, :distance, :elevation, :gpx_file, :price)";
                    $this->log("SQL query for route: " . $sql);
                    $this->debug_log("SQL query for route: " . $sql);
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($routeData);
                }
            }

            // Traitement des images
            if (!empty($data['delete_images'])) {
                foreach ($data['delete_images'] as $imageId) {
                    $this->deleteImage($imageId);
                }
            }

            if (!empty($data['images'])) {
                $uploadedImages = $this->uploadImages($data['images'], $eventId);
                if (!empty($uploadedImages)) {
                    foreach ($uploadedImages as $imagePath) {
                        $sql = "INSERT INTO event_images (event_id, image_path) 
                                VALUES (:event_id, :image_path)";
                        $this->log("SQL query for image: " . $sql);
                        $this->debug_log("SQL query for image: " . $sql);
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            'event_id' => $eventId,
                            'image_path' => $imagePath
                        ]);
                    }
                }
            }

            if (!empty($data['main_image'])) {
                $sql = "UPDATE event_images SET is_main = CASE 
                        WHEN id = :main_id THEN 1 
                        ELSE 0 END 
                        WHERE event_id = :event_id";
                $this->log("SQL query for main image: " . $sql);
                $this->debug_log("SQL query for main image: " . $sql);
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'main_id' => $data['main_image'],
                    'event_id' => $eventId
                ]);
            }

            $this->db->commit();
            $this->log("Event updated successfully");
            $this->debug_log("Event updated successfully");
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->log("Database error in update method: " . $e->getMessage());
            $this->debug_log("Database error in update method: " . $e->getMessage());
            throw $e;
        }
    }

    private function uploadGpxFile($file, $eventId) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erreur lors de l'upload du fichier GPX");
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'gpx') {
            throw new Exception("Le fichier doit être au format GPX");
        }

        $filename = uniqid('gpx_' . $eventId . '_') . '.gpx';
        $targetPath = Storage::getStoragePath('gpx', $filename);
        $uploadDir = dirname($targetPath);
        Storage::ensureDirectoryExists($uploadDir);

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Erreur lors du déplacement du fichier GPX");
        }

        $this->log("GPX file uploaded successfully: " . $filename);
        $this->debug_log("GPX file uploaded successfully: " . $filename);

        return Storage::getPublicUrl('gpx', $filename);
    }

    private function uploadImages($files, $eventId) {
        $uploadedFiles = [];
        $uploadDir = dirname(Storage::getStoragePath('events', 'placeholder.jpg'));
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Vérifier le nombre total d'images existantes
        $sql = "SELECT COUNT(*) as count FROM event_images WHERE event_id = :event_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);
        $existingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if (count($files) + $existingCount > 5) {
            throw new Exception("Maximum 5 images autorisées par événement");
        }

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Vérification du type de fichier
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception("Type de fichier non autorisé : " . $file['type']);
            }

            // Vérification de la taille (5Mo max)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("L'image ne doit pas dépasser 5Mo");
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid('event_' . $eventId . '_') . '.' . $extension;
            $targetPath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $uploadedFiles[] = Storage::getPublicUrl('events', $filename);
            }
        }

        $this->log("Images uploaded successfully: " . print_r($uploadedFiles, true));
        $this->debug_log("Images uploaded successfully: " . print_r($uploadedFiles, true));

        return $uploadedFiles;
    }

    private function deleteImage($imageId) {
        try {
            $sql = "SELECT image_path FROM event_images WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $imageId]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image) {
                $filePath = Storage::getStoragePath('events', basename($image['image_path']));
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $sql = "DELETE FROM event_images WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['id' => $imageId]);
            }

            $this->log("Image deleted successfully: " . $imageId);
            $this->debug_log("Image deleted successfully: " . $imageId);
        } catch (Exception $e) {
            $this->log("Database error in deleteImage method: " . $e->getMessage());
            $this->debug_log("Database error in deleteImage method: " . $e->getMessage());
            throw $e;
        }
    }

    public function addParticipant($eventId, $userId) {
        try {
            $sql = "INSERT INTO event_participants (event_id, user_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId, $userId]);
            $this->log("Participant added successfully: " . $userId);
            $this->debug_log("Participant added successfully: " . $userId);
            return true;
        } catch (PDOException $e) {
            $this->log("Database error in addParticipant method: " . $e->getMessage());
            $this->debug_log("Database error in addParticipant method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de l'ajout du participant.");
        }
    }

    public function removeParticipant($eventId, $userId) {
        try {
            $sql = "DELETE FROM event_participants WHERE event_id = ? AND user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId, $userId]);
            $this->log("Participant removed successfully: " . $userId);
            $this->debug_log("Participant removed successfully: " . $userId);
            return true;
        } catch (PDOException $e) {
            $this->log("Database error in removeParticipant method: " . $e->getMessage());
            $this->debug_log("Database error in removeParticipant method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la suppression du participant.");
        }
    }

    public function getParticipants($eventId) {
        try {
            $sql = "SELECT u.* FROM users u
                    JOIN event_participants ep ON u.id = ep.user_id
                    WHERE ep.event_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);
            $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->log("Participants retrieved successfully for event #" . $eventId);
            $this->debug_log("Participants retrieved successfully for event #" . $eventId);
            return $participants;
        } catch (PDOException $e) {
            $this->log("Database error in getParticipants method: " . $e->getMessage());
            $this->debug_log("Database error in getParticipants method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la récupération des participants.");
        }
    }

    public function getOrganizer($organizerId) {
        try {
            $sql = "SELECT * FROM organizer_profiles WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$organizerId]);
            $organizer = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->log("Organizer retrieved successfully: #" . $organizerId);
            $this->debug_log("Organizer retrieved successfully: #" . $organizerId);
            return $organizer ?: null;
        } catch (PDOException $e) {
            $this->log("Database error in getOrganizer method: " . $e->getMessage());
            $this->debug_log("Database error in getOrganizer method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la récupération de l'organisateur.");
        }
    }

    public function isUserParticipating($eventId, $userId) {
        try {
            $sql = "SELECT COUNT(*) FROM event_participants WHERE event_id = ? AND user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId, $userId]);
            $isParticipating = (bool) $stmt->fetchColumn();
            $this->log("isUserParticipating for event #" . $eventId . ", user #" . $userId . ": " . ($isParticipating ? 'yes' : 'no'));
            $this->debug_log("isUserParticipating for event #" . $eventId . ", user #" . $userId . ": " . ($isParticipating ? 'yes' : 'no'));
            return $isParticipating;
        } catch (PDOException $e) {
            $this->log("Database error in isUserParticipating method: " . $e->getMessage());
            $this->debug_log("Database error in isUserParticipating method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la vérification de la participation.");
        }
    }

    public function count($filters = []) {
        $sql = "SELECT COUNT(*) FROM events WHERE status = 'published'";
        $params = [];
        
        $where = [];
        if (!empty($filters['date']) && $filters['date'] === 'upcoming') {
            $where[] = "date >= CURDATE()";
        }
        
        if (!empty($where)) {
            $sql .= " AND " . implode(' AND ', $where);
        }
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->fetchColumn();
            $this->log("Events count: " . $count);
            $this->debug_log("Events count: " . $count);
            return $count;
        } catch (PDOException $e) {
            $this->log("Database error in count method: " . $e->getMessage());
            $this->debug_log("Database error in count method: " . $e->getMessage());
            return 0;
        }
    }

    public function countParticipants() {
        $sql = "SELECT COUNT(*) FROM event_participants";
        try {
            $count = $this->db->query($sql)->fetchColumn();
            $this->log("Participants count: " . $count);
            $this->debug_log("Participants count: " . $count);
            return $count;
        } catch (PDOException $e) {
            $this->log("Database error in countParticipants method: " . $e->getMessage());
            $this->debug_log("Database error in countParticipants method: " . $e->getMessage());
            return 0;
        }
    }

    public function countComments() {
        $sql = "SELECT COUNT(*) FROM event_comments";
        try {
            $count = $this->db->query($sql)->fetchColumn();
            $this->log("Comments count: " . $count);
            $this->debug_log("Comments count: " . $count);
            return $count;
        } catch (PDOException $e) {
            $this->log("Database error in countComments method: " . $e->getMessage());
            $this->debug_log("Database error in countComments method: " . $e->getMessage());
            return 0;
        }
    }

    public function getComments($eventId) {
        try {
            $sql = "SELECT c.*, u.name as user_name
                    FROM event_comments c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.event_id = ?
                    ORDER BY c.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->log("Comments retrieved successfully for event #" . $eventId);
            $this->debug_log("Comments retrieved successfully for event #" . $eventId);
            return $comments;
        } catch (PDOException $e) {
            $this->log("Database error in getComments method: " . $e->getMessage());
            $this->debug_log("Database error in getComments method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la récupération des commentaires.");
        }
    }

    public function addComment(array $data) {
        try {
            $sql = "INSERT INTO event_comments (event_id, user_id, content) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['event_id'], $data['user_id'], $data['content']]);
            $commentId = (int) $this->db->lastInsertId();
            $this->log("Comment added successfully: " . $commentId);
            $this->debug_log("Comment added successfully: " . $commentId);
            return $commentId;
        } catch (PDOException $e) {
            $this->log("Database error in addComment method: " . $e->getMessage());
            $this->debug_log("Database error in addComment method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de l'ajout du commentaire.");
        }
    }

    public function getCommentById($id) {
        try {
            $sql = "SELECT c.*, u.name as user_name
                    FROM event_comments c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->log("Comment retrieved successfully: #" . $id);
            $this->debug_log("Comment retrieved successfully: #" . $id);
            return $comment;
        } catch (PDOException $e) {
            $this->log("Database error in getCommentById method: " . $e->getMessage());
            $this->debug_log("Database error in getCommentById method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la récupération du commentaire.");
        }
    }

    public function updateComment($id, array $data) {
        try {
            $sql = "UPDATE event_comments SET content = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['content'], $id]);
            $this->log("Comment updated successfully: #" . $id);
            $this->debug_log("Comment updated successfully: #" . $id);
            return true;
        } catch (PDOException $e) {
            $this->log("Database error in updateComment method: " . $e->getMessage());
            $this->debug_log("Database error in updateComment method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la mise à jour du commentaire.");
        }
    }

    public function deleteComment($id) {
        try {
            $sql = "DELETE FROM event_comments WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $this->log("Comment deleted successfully: #" . $id);
            $this->debug_log("Comment deleted successfully: #" . $id);
            return true;
        } catch (PDOException $e) {
            $this->log("Database error in deleteComment method: " . $e->getMessage());
            $this->debug_log("Database error in deleteComment method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la suppression du commentaire.");
        }
    }

    /**
     * Récupère tous les événements avec leur période et compte les totaux
     * 
     * @return array
     */
    public function getEventCounts()
    {
        try {
            // Récupérer tous les événements avec leur période
            $query = "
                SELECT 
                    e.*,
                    COALESCE(e.main_image_path, 
                        (SELECT image_path FROM event_images WHERE event_id = e.id LIMIT 1)
                    ) as main_image_path,
                    CASE
                        WHEN DATE(e.date) = CURDATE() THEN 'today'
                        WHEN DATE(e.date) < CURDATE() THEN 'past'
                        ELSE 'upcoming'
                    END as period
                FROM events e
                WHERE e.status = 'published'
                ORDER BY e.date ASC
            ";
            
            $this->debug_log("Requête SQL:", $query);
            
            $stmt = $this->db->query($query);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug des données
            $this->debug_log("Événements bruts:", $events);
            
            // Initialiser les compteurs
            $counts = [
                'upcoming' => 0,  // Compteur pour le filtre temporel
                'today' => 0,     // Compteur pour le filtre temporel
                'past' => 0,      // Compteur pour le filtre temporel
                'running' => 0,   // Compteur pour le filtre de catégorie
                'hiking' => 0,    // Compteur pour le filtre de catégorie
                'cycling' => 0,   // Compteur pour le filtre de catégorie
                'all' => 0        // Total pour la période sélectionnée
            ];
            
            // Compter d'abord tous les événements par période
            foreach ($events as $event) {
                $period = $event['period'] ?? 'upcoming'; // Valeur par défaut si period est vide
                if (isset($counts[$period])) { // Vérifier que le compteur existe
                    $counts[$period]++;
                }
            }
            
            // Ensuite, compter les événements par catégorie pour la période sélectionnée
            $selectedPeriod = $_GET['period'] ?? 'upcoming';
            if (!empty($selectedPeriod)) {
                foreach ($events as $event) {
                    if (($event['period'] ?? 'upcoming') === $selectedPeriod) {
                        $category = $event['category'] ?? 'other'; // Valeur par défaut si category est vide
                        if (isset($counts[$category])) { // Vérifier que le compteur existe
                            $counts[$category]++;
                        }
                        $counts['all']++;
                    }
                }
            }
            
            // Ajouter l'image par défaut si nécessaire
            foreach ($events as &$event) {
                if (empty($event['main_image_path'])) {
                    $event['main_image_path'] = $this->getDefaultImage();
                }
            }
            
            // Debug des compteurs
            error_log("Counts: " . print_r($counts, true));
            
            return [
                'counts' => $counts,
                'events' => $events
            ];
            
        } catch (PDOException $e) {
            $this->log("Erreur lors de la récupération des événements: " . $e->getMessage());
            $this->debug_log("Erreur lors de la récupération des événements: " . $e->getMessage());
            return [
                'counts' => [
                    'upcoming' => 0,
                    'today' => 0,
                    'past' => 0,
                    'running' => 0,
                    'hiking' => 0,
                    'cycling' => 0,
                    'all' => 0
                ],
                'events' => []
            ];
        }
    }

    /**
     * Supprime un événement
     * 
     * @param int $eventId ID de l'événement à supprimer
     * @return bool
     * @throws Exception
     */
    public function delete($eventId) {
        try {
            // Supprimer d'abord les enregistrements liés
            $this->db->beginTransaction();

            // Supprimer les participants
            $sql = "DELETE FROM event_participants WHERE event_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);

            // Supprimer les commentaires
            $sql = "DELETE FROM event_comments WHERE event_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);

            // Supprimer les images
            $sql = "SELECT image_path FROM event_images WHERE event_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);
            $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Supprimer les fichiers physiques
            foreach ($images as $imagePath) {
                $fullPath = Storage::getStoragePath('events', basename($imagePath));
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }

            // Supprimer les entrées d'images de la base de données
            $sql = "DELETE FROM event_images WHERE event_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);

            // Enfin, supprimer l'événement lui-même
            $sql = "DELETE FROM events WHERE id = ? AND status = 'approved'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);

            $this->db->commit();
            $this->log("Event deleted successfully: " . $eventId);
            $this->debug_log("Event deleted successfully: " . $eventId);
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->log("Database error in delete method: " . $e->getMessage());
            $this->debug_log("Database error in delete method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la suppression de l'événement.");
        }
    }
}
