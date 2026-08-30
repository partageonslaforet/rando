<?php

class Event {
    private $db;
    private $config;
    private const LOG_FILE = '/tmp/rando_debug.log';

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        error_log($logMessage, 3, self::LOG_FILE);
    }

    public function __construct($db) {
        $this->db = $db;
        $this->config = require __DIR__ . '/../../config/assets.php';
        // Vérifier si l'image par défaut existe
        $defaultImagePath = $_SERVER['DOCUMENT_ROOT'] . $this->getDefaultImage();
        $this->log("Chemin complet de l'image par défaut: " . $defaultImagePath);
        if (!file_exists($defaultImagePath)) {
            $this->log("ATTENTION: L'image par défaut n'existe pas: " . $defaultImagePath);
        }
    }

    /**
     * Récupère l'image par défaut pour un événement
     */
    public function getDefaultImage() {
        return $this->config['images']['default_hero'];
    }

    /**
     * Récupère tous les événements avec filtres et pagination
     * 
     * @param array $filters Filtres (category, period, search)
     * @param int $page Numéro de page
     * @param int $limit Nombre d'éléments par page
     * @return array
     */
    public function getAll($filters = [], $page = 1, $limit = 12)
    {
        try {
            $query = "SELECT SQL_CALC_FOUND_ROWS e.* FROM events e WHERE e.status = 'approved' AND 1=1";
            $params = [];

            // Filtre par type
            if (!empty($filters['category']) && $filters['category'] !== 'all') {
                $query .= " AND e.category = :category";
                $params[':category'] = $filters['category'];
            }

            // Filtre par période
            if (!empty($filters['period'])) {
                switch ($filters['period']) {
                    case 'today':
                        $query .= " AND DATE(e.date) = CURDATE()";
                        break;
                    case 'past':
                        $query .= " AND e.date < CURDATE()";
                        break;
                    case 'upcoming':
                    default:
                        $query .= " AND e.date >= CURDATE()";
                        break;
                }
            }

            // Filtre par recherche
            if (!empty($filters['search'])) {
                $query .= " AND (e.title LIKE :search OR e.description LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            // Ajout de l'ordre et de la pagination
            $query .= " ORDER BY e.date ASC";
            $offset = ($page - 1) * $limit;
            $query .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            // Exécution de la requête principale
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':offset') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->execute();
            $events = $stmt->fetchAll();

            // Récupération du nombre total de résultats
            $total = $this->db->query("SELECT FOUND_ROWS()")->fetchColumn();

            // Requête pour les compteurs
            $countQuery = "SELECT category, COUNT(*) as count 
                          FROM events 
                          WHERE status = 'approved' AND date >= CURDATE() 
                          GROUP BY category";
            $countStmt = $this->db->query($countQuery);
            $categoryCounts = $countStmt->fetchAll();

            // Formatage des compteurs
            $counts = ['all' => 0];
            foreach ($categoryCounts as $count) {
                $counts[$count['category']] = (int)$count['count'];
                $counts['all'] += (int)$count['count'];
            }

            return [
                'events' => $events,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit),
                'counts' => $counts
            ];

        } catch (Exception $e) {
            error_log("Erreur dans Event::getAll : " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère un événement par son ID
     */
    public function getById($id) {
        try {
            $query = "SELECT e.*, u.name as organizer_name 
                     FROM events e 
                     LEFT JOIN users u ON e.organizer_id = u.id 
                     WHERE e.id = :id AND e.status = 'approved'";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching event by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère un événement par son ID
     */
    public function get($id) {
        try {
            // Récupérer l'événement
            $sql = "SELECT * FROM events WHERE id = :id AND status = 'approved'";
            $this->log("SQL query for get method: " . $sql);
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $event = $stmt->fetch();

            if (!$event) {
                return null;
            }

            // Récupérer les images
            $sql = "SELECT * FROM event_images WHERE event_id = :event_id ORDER BY is_main DESC";
            $this->log("SQL query for images: " . $sql);
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['event_id' => $id]);
            $event['images'] = $stmt->fetchAll();

            // Récupérer les parcours
            $sql = "SELECT * FROM event_routes WHERE event_id = :event_id ORDER BY id ASC";
            $this->log("SQL query for routes: " . $sql);
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['event_id' => $id]);
            $event['routes'] = $stmt->fetchAll();

            $this->log("Event retrieved: " . print_r($event, true));

            return $event;
        } catch (Exception $e) {
            $this->log("Database error in get method: " . $e->getMessage());
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
            return null;
        }
    }

    public function create($data) {
        try {
            $this->db->beginTransaction();

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
                'category' => $data['category'],
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

            // Insertion de l'événement
            $sql = "INSERT INTO events (title, description, category, date, start_time, end_time, 
                    location, venue, coordinates, difficulty, max_participants, created_by, created_at) 
                    VALUES (:title, :description, :category, :date, :start_time, :end_time, 
                    :location, :venue, :coordinates, :difficulty, :max_participants, :created_by, :created_at)";
            
            $this->log("SQL query for create method: " . $sql);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($eventData);
            $eventId = $this->db->lastInsertId();

            $this->log("Event created with ID: " . $eventId);

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

                    $sql = "INSERT INTO event_routes (event_id, name, distance, elevation, gpx_file, price) 
                            VALUES (:event_id, :name, :distance, :elevation, :gpx_file, :price)";
                    $this->log("SQL query for route: " . $sql);
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
            return $eventId;

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->log("Database error in create method: " . $e->getMessage());
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

            $stmt = $this->db->prepare($sql);
            $stmt->execute($eventData);

            // Mise à jour des parcours
            // Suppression des anciens parcours
            $sql = "DELETE FROM event_routes WHERE event_id = :event_id";
            $this->log("SQL query for delete routes: " . $sql);
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

                    $sql = "INSERT INTO event_routes (event_id, name, distance, elevation, gpx_file, price) 
                            VALUES (:event_id, :name, :distance, :elevation, :gpx_file, :price)";
                    $this->log("SQL query for route: " . $sql);
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
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'main_id' => $data['main_image'],
                    'event_id' => $eventId
                ]);
            }

            $this->db->commit();
            $this->log("Event updated successfully");
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->log("Database error in update method: " . $e->getMessage());
            throw $e;
        }
    }

    private function uploadGpxFile($file, $eventId) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erreur lors de l'upload du fichier GPX");
        }

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/gpx/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'gpx') {
            throw new Exception("Le fichier doit être au format GPX");
        }

        $filename = uniqid('gpx_' . $eventId . '_') . '.gpx';
        $targetPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Erreur lors du déplacement du fichier GPX");
        }

        $this->log("GPX file uploaded successfully: " . $filename);

        return '/uploads/gpx/' . $filename;
    }

    private function uploadImages($files, $eventId) {
        $uploadedFiles = [];
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/events/';
        
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
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $uploadedFiles[] = '/uploads/events/' . $filename;
            }
        }

        $this->log("Images uploaded successfully: " . print_r($uploadedFiles, true));

        return $uploadedFiles;
    }

    private function deleteImage($imageId) {
        try {
            $sql = "SELECT image_path FROM event_images WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $imageId]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . $image['image_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $sql = "DELETE FROM event_images WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['id' => $imageId]);
            }

            $this->log("Image deleted successfully: " . $imageId);
        } catch (Exception $e) {
            $this->log("Database error in deleteImage method: " . $e->getMessage());
            throw $e;
        }
    }

    public function addParticipant($eventId, $userId) {
        try {
            $sql = "INSERT INTO event_participants (event_id, user_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId, $userId]);
            $this->log("Participant added successfully: " . $userId);
            return true;
        } catch (PDOException $e) {
            $this->log("Database error in addParticipant method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de l'ajout du participant.");
        }
    }

    public function removeParticipant($eventId, $userId) {
        try {
            $sql = "DELETE FROM event_participants WHERE event_id = ? AND user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId, $userId]);
            $this->log("Participant removed successfully: " . $userId);
            return true;
        } catch (PDOException $e) {
            $this->log("Database error in removeParticipant method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la suppression du participant.");
        }
    }

    public function getParticipants($eventId) {
        try {
            $sql = "SELECT u.* FROM users u 
                    JOIN event_participants ep ON u.id = ep.user_id 
                    WHERE ep.event_id = ? AND e.status = 'approved'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$eventId]);
            $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->log("Participants retrieved successfully: " . print_r($participants, true));
            return $participants;
        } catch (PDOException $e) {
            $this->log("Database error in getParticipants method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la récupération des participants.");
        }
    }

    public function count($filters = []) {
        $sql = "SELECT COUNT(*) FROM events WHERE status = 'approved'";
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
            return $count;
        } catch (PDOException $e) {
            $this->log("Database error in count method: " . $e->getMessage());
            return 0;
        }
    }

    public function countParticipants() {
        $sql = "SELECT COUNT(*) FROM event_participants";
        try {
            $count = $this->db->query($sql)->fetchColumn();
            $this->log("Participants count: " . $count);
            return $count;
        } catch (PDOException $e) {
            $this->log("Database error in countParticipants method: " . $e->getMessage());
            return 0;
        }
    }

    public function countComments() {
        $sql = "SELECT COUNT(*) FROM event_comments";
        try {
            $count = $this->db->query($sql)->fetchColumn();
            $this->log("Comments count: " . $count);
            return $count;
        } catch (PDOException $e) {
            $this->log("Database error in countComments method: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Récupère tous les événements avec leur période et compte les totaux
     * 
     * @return array
     */
    public function getEventCounts() {
        // Requête pour obtenir tous les événements avec leur période
        $query = "SELECT 
                    e.*,
                    CASE 
                        WHEN DATE(e.date) = CURDATE() THEN 'today'
                        WHEN e.date > CURDATE() THEN 'upcoming'
                        ELSE 'past'
                    END as period
                FROM events e
                WHERE e.status = 'approved'
                ORDER BY e.date ASC";
        
        try {
            $stmt = $this->db->query($query);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
                $period = $event['period'];
                $counts[$period]++;
            }
            
            // Ensuite, compter les événements par catégorie pour la période sélectionnée
            $selectedPeriod = $_GET['period'] ?? 'upcoming';
            foreach ($events as $event) {
                if ($event['period'] === $selectedPeriod) {
                    $category = $event['category'];
                    $counts[$category]++;
                    $counts['all']++;
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
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . $imagePath;
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
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->log("Database error in delete method: " . $e->getMessage());
            throw new Exception("Une erreur est survenue lors de la suppression de l'événement.");
        }
    }
}
