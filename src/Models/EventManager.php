<?php
/**
 * src/Models/EventManager.php
 * Role: Gestionnaire de la liste, du filtrage et du comptage des événements.
 * Usage: new EventManager($db)
 * Dépendances: PDO, DateTime
 */

class EventManager {
    private PDO $db;
    private array $config;
    private const DEFAULT_IMAGE = '/assets/images/default-event.jpg';
    private const VALID_CATEGORIES = ['running', 'hiking', 'cycling'];
    private const VALID_PERIODS = ['past', 'today', 'upcoming', 'all'];

    public function __construct(PDO $db) {
        if (!$db) {
            throw new Exception("Database connection is required");
        }
        $this->db = $db;
        $this->loadConfig();
    }

    private function loadConfig(): void {
        $configPath = dirname(__DIR__, 2) . '/config/assets.php';
        if (!file_exists($configPath)) {
            throw new Exception("Configuration file not found: $configPath");
        }
        $this->config = require $configPath;
    }

    public function getEvents(array $filters = []): array {
        try {
            // Construction de la requête de base
            $query = $this->buildBaseQuery();
            
            // Ajout des conditions de filtrage
            [$query, $params] = $this->addFilterConditions($query, $filters);
            
            // Exécution de la requête
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Classification temporelle des événements
            $eventsByPeriod = $this->classifyEventsByPeriod($events);
            
            // Calcul des compteurs
            $counts = $this->calculateCounts($eventsByPeriod);
            // Debug avant le retour
            error_log("Events data: " . print_r([
                'events' => $events,
                'eventsByPeriod' => $eventsByPeriod,
                'counts' => $counts
            ], true));
            return [
                'events' => $events,
                'eventsByPeriod' => $eventsByPeriod,
                'counts' => $counts
            ];

            
        } catch (Exception $e) {
            error_log("Error in EventManager::getEvents: " . $e->getMessage());
            throw $e;
        }
    }

    private function buildBaseQuery(): string {
        return "SELECT 
                e.id,
                e.title,
                e.description,
                e.date,
                e.start_time,
                e.end_time,
                e.location,
                e.coordinates,
                e.category,
                e.status,
                e.organisation,
                e.category_id,
                e.difficulty,
                e.max_participants,
                e.user_id,
                e.is_cancelled,
                e.cancellation_reason,
                e.main_image_path,
                u.name as creator_name,
                c.name as category_name,
                c.icon as category_icon,
                c.color as category_color
                FROM events e 
                LEFT JOIN users u ON e.user_id = u.id 
                LEFT JOIN event_categories c ON e.category_id = c.id
                WHERE e.status = 'approved'";
    }

    private function addFilterConditions(string $query, array $filters): array {
        $params = [];
        
        if (!empty($filters['category'])) {
            $query .= " AND e.category_id = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $query .= " AND (
                e.title LIKE :search 
                OR e.description LIKE :search 
                OR e.location LIKE :search 
                OR e.organisation LIKE :search
            )";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $query .= " ORDER BY e.date ASC, e.start_time ASC";

        return [$query, $params];
    }

    private function classifyEventsByPeriod(array $events): array {
        $classified = [
            'past' => [],
            'today' => [],
            'upcoming' => []
        ];

        $today = new DateTime();
        $today->setTime(0, 0, 0);

        foreach ($events as $event) {
            $eventDate = new DateTime($event['date']);
            $eventDate->setTime(0, 0, 0);

            if ($eventDate < $today) {
                $classified['past'][] = $event;
            } elseif ($eventDate == $today) {
                $classified['today'][] = $event;
            } else {
                $classified['upcoming'][] = $event;
            }
        }

        return $classified;
    }

    private function calculateCounts(array $eventsByPeriod): array {
        return [
            'past' => count($eventsByPeriod['past']),
            'today' => count($eventsByPeriod['today']),
            'upcoming' => count($eventsByPeriod['upcoming']),
            'all' => array_sum(array_map('count', $eventsByPeriod))
        ];
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT e.*, u.name as creator_name, c.name as category_name
            FROM events e 
            LEFT JOIN users u ON e.user_id = u.id 
            LEFT JOIN event_categories c ON e.category_id = c.id
            WHERE e.id = :id AND e.status = 'approved'
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}