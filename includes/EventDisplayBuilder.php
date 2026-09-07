<?php
/**
 * Construit une représentation normalisée d'un événement (publié ou brouillon)
 * pour être affichée de manière identique par event-detail.php et preview.php.
 */

class EventDisplayBuilder
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Charge un événement prêt à l'affichage.
     *
     * @param string $mode 'published' ou 'draft'
     * @param int $id
     * @param int|null $userId requis pour un brouillon (vérification propriétaire)
     * @return array|null
     * @throws Exception
     */
    public function build(string $mode, int $id, ?int $userId = null): ?array
    {
        if ($mode !== 'published' && $mode !== 'draft') {
            throw new Exception("Mode inconnu : $mode");
        }

        if ($mode === 'draft' && !$userId) {
            throw new Exception('UserId requis pour charger un brouillon');
        }

        $event = $this->fetchEvent($mode, $id, $userId);
        if (!$event) {
            return null;
        }

        $event['routes']      = $this->fetchRoutes($mode, $id);
        $event['contacts']    = $this->fetchContacts($mode, $id);
        $event['images']      = $this->fetchImages($mode, $id);
        $event['organizer']   = $this->fetchOrganizer($mode, $event);
        $event['categories']  = $this->fetchCategories($mode, $id);

        // Parcours virtuel pour le GPX global de l'événement (s'il existe)
        if (!empty($event['gpx_path'])) {
            $event['routes'][] = [
                'name' => 'Parcours',
                'category_id' => null,
                'distance' => null,
                'elevation' => null,
                'description' => '',
                'gpx_file' => $this->normalizePath($event['gpx_path']),
                'gpx_downloadable' => !empty($event['gpx_downloadable']) ? 1 : 0,
                'price' => 0.00
            ];
        }

        // Coordonnées exploitable
        if (!empty($event['coordinates'])) {
            $coords = explode(',', $event['coordinates']);
            $event['latitude']  = $coords[0] ?? null;
            $event['longitude'] = $coords[1] ?? null;
        }

        // Image principale déjà dans $event['images']['main']
        $event['main_image'] = $event['images']['main'] ?? '/assets/images/events/default-event.jpg';
        $event['secondary_images'] = $event['images']['secondary'] ?? [];

        return $event;
    }

    private function fetchEvent(string $mode, int $id, ?int $userId): ?array
    {
        if ($mode === 'published') {
            $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = :id");
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM draft_events WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
        }

        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        return $event ?: null;
    }

    private function fetchRoutes(string $mode, int $id): array
    {
        $table = $mode === 'published' ? 'event_parcours' : 'draft_parcours';
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
            FROM {$table} p
            LEFT JOIN event_categories c ON p.category_id = c.id
            WHERE p.event_id = :event_id
            ORDER BY p.id ASC
        ");
        $stmt->execute(['event_id' => $id]);
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normaliser les clés entre brouillon et publié
        foreach ($routes as &$route) {
            if (isset($route['elevation_gain']) && !isset($route['elevation'])) {
                $route['elevation'] = $route['elevation_gain'];
            }
        }
        unset($route);

        return $routes;
    }

    private function fetchContacts(string $mode, int $id): array
    {
        $table = $mode === 'published' ? 'event_contacts' : 'draft_contacts';
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE event_id = :event_id ORDER BY id ASC");
        $stmt->execute(['event_id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchImages(string $mode, int $id): array
    {
        $table = $mode === 'published' ? 'event_images' : 'draft_images';

        $mainStmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE event_id = :event_id AND is_main = 1 ORDER BY id ASC LIMIT 1");
        $mainStmt->execute(['event_id' => $id]);
        $mainRow = $mainStmt->fetch(PDO::FETCH_ASSOC);
        $main = $mainRow ? $this->resolveImagePath($mainRow) : null;

        $secStmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE event_id = :event_id AND is_main = 0 ORDER BY id ASC");
        $secStmt->execute(['event_id' => $id]);
        $secondary = [];
        while ($row = $secStmt->fetch(PDO::FETCH_ASSOC)) {
            $path = $this->resolveImagePath($row);
            if ($path) {
                $secondary[] = $path;
            }
        }

        // Fallback pour les événements publiés qui stockeraient l'image principale directement dans events
        if (!$main && $mode === 'published') {
            $main = $this->getPublishedMainImageFallback($id);
        }

        // Fallback : si aucune image principale n'est définie, utiliser la première image secondaire
        if (!$main && !empty($secondary)) {
            $main = array_shift($secondary);
        }

        return [
            'main' => $this->normalizePath($main),
            'secondary' => array_map([$this, 'normalizePath'], array_filter($secondary)),
        ];
    }

    private function resolveImagePath(array $row): ?string
    {
        $imagePath = $row['image_path'] ?? null;
        $storagePath = $row['storage_path'] ?? null;

        if (!empty($imagePath)) {
            return $imagePath;
        }

        if (empty($storagePath)) {
            return null;
        }

        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($docRoot && strpos($storagePath, $docRoot) === 0) {
            return '/' . ltrim(str_replace($docRoot, '', $storagePath), '/');
        }

        return $storagePath;
    }

    private function getPublishedMainImageFallback(int $eventId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT main_image_path, main_image FROM events WHERE id = :id");
        $stmt->execute(['id' => $eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['main_image_path'] ?? $row['main_image'] ?? null;
    }

    private function fetchOrganizer(string $mode, array $event): ?array
    {
        $organizerId = !empty($event['organizer_id']) ? (int) $event['organizer_id'] : null;
        $userId      = !empty($event['user_id']) ? (int) $event['user_id'] : null;

        if ($organizerId) {
            $stmt = $this->pdo->prepare("SELECT * FROM organizer_profiles WHERE id = :id");
            $stmt->execute(['id' => $organizerId]);
            $org = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($org) {
                return $org;
            }
        }

        // Nom d'organisateur personnalisé stocké dans le brouillon/événement
        if (!empty($event['organisation'])) {
            return [
                'name' => $event['organisation'],
                'email' => null,
                'phone' => null,
                'description' => null,
                'logo_path' => null,
                'website' => null,
                'address' => null,
            ];
        }

        // Fallback sur le profil utilisateur
        if ($userId) {
            $stmt = $this->pdo->prepare("SELECT * FROM organizer_profiles WHERE user_id = :user_id ORDER BY id ASC LIMIT 1");
            $stmt->execute(['user_id' => $userId]);
            $org = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($org) {
                return $org;
            }

            // Dernier recours : informations du compte utilisateur
            $stmt = $this->pdo->prepare("SELECT id, first_name, last_name, name, email FROM users WHERE id = :user_id LIMIT 1");
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                return [
                    'name' => $user['name'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: null,
                    'email' => $user['email'] ?? null,
                    'phone' => $user['phone'] ?? null,
                    'description' => null,
                    'logo_path' => null,
                    'website' => null,
                    'address' => null,
                ];
            }
        }

        return null;
    }

    private function fetchCategories(string $mode, int $id): array
    {
        if ($mode === 'published') {
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.name, c.code, c.icon, c.color
                FROM event_category_links ecl
                JOIN event_categories c ON ecl.category_id = c.id
                WHERE ecl.event_id = :event_id
                ORDER BY c.name ASC
            ");
        } else {
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.name, c.code, c.icon, c.color
                FROM draft_event_category_links decl
                JOIN event_categories c ON decl.category_id = c.id
                WHERE decl.draft_event_id = :event_id
                ORDER BY c.name ASC
            ");
        }
        $stmt->execute(['event_id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assure un chemin absolu local ou une URL complète.
     */
    private function normalizePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (filter_var($path, FILTER_VALIDATE_URL) || strpos($path, '/') === 0) {
            return $path;
        }
        return '/' . ltrim($path, '/');
    }
}
