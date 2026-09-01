<?php
require_once __DIR__ . '/../src/Models/Event.php';
require_once __DIR__ . '/../config/database.php';

error_log("[HOME.PHP] Début du chargement de home.php");
error_log("[HOME.PHP] Variables disponibles: " . print_r(get_defined_vars(), true));

// Initialiser le gestionnaire d'événements
$eventManager = new Event($pdo);

// Ajouter le lien CSS dans le head
?>
<link rel="stylesheet" href="/assets/css/events.css">
<?php

// Récupérer les filtres depuis l'URL
$filters = [
    'period' => $_GET['period'] ?? 'upcoming',
    'type' => $_GET['type'] ?? 'all',
    'radius' => $_GET['radius'] ?? 10
];

// Récupérer les événements et les compteurs
$eventData = $eventManager->getEventCounts();
$eventCounts = $eventData['counts'];
$allEvents = $eventData['events'];

// Filtrage des événements
$filteredEvents = $allEvents;

// Filtre par date
if (!empty($filters['period']) && $filters['period'] !== 'all') {
    $filteredEvents = array_filter($filteredEvents, function($event) use ($filters) {
        if ($filters['period'] === 'upcoming') {
            return strtotime($event['date']) > time();
        } elseif ($filters['period'] === 'today') {
            return date('Y-m-d', strtotime($event['date'])) === date('Y-m-d');
        } elseif ($filters['period'] === 'past') {
            return strtotime($event['date']) < time();
        }
    });
}

// Filtre par catégorie
if (!empty($filters['type']) && $filters['type'] !== 'all') {
    $filteredEvents = array_filter($filteredEvents, function($event) use ($filters) {
        return $event['category'] === $filters['type'];
    });
}

// Calcul de la pagination sur les événements filtrés
$eventsPerPage = 3; 
error_log("=== DEBUG PAGINATION ===");
error_log("Nombre total d'événements filtrés: " . count($filteredEvents));
error_log("Événements par page: " . $eventsPerPage);

// Réindexer le tableau pour éviter les problèmes avec array_slice
$filteredEvents = array_values($filteredEvents);

$totalEvents = count($filteredEvents);
$totalPages = ceil($totalEvents / $eventsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($page - 1) * $eventsPerPage;

// Sélectionner uniquement les événements de la page courante
$currentPageEvents = array_slice($filteredEvents, $offset, $eventsPerPage);

error_log("Page actuelle: " . $page);
error_log("Offset: " . $offset);
error_log("Nombre d'événements sur cette page: " . count($currentPageEvents));
error_log("Nombre total de pages: " . $totalPages);

// Construire l'URL de base pour la pagination
$queryParams = $_GET;
unset($queryParams['page']); // Retirer la page car elle sera ajoutée plus tard
$baseUrl = '?' . http_build_query($queryParams);
$baseUrl = $baseUrl ? $baseUrl . '&' : '?';

// Debug dans la console
echo "<script>
    window.eventData = " . json_encode($eventData) . ";
    window.allEvents = " . json_encode($allEvents) . ";
</script>";

// Nombre total d'événements trouvés
$totalEvents = $eventCounts['all'];

error_log("Template data - Filters: " . print_r($filters, true));
error_log("Template data - Event counts: " . print_r($eventCounts, true));
?>

<!-- Hero Section -->
<section class="hero" style="background-image: url('/assets/images/main-hero.jpg');">
    <div class="hero-content">
        <h1 class="display-2 fw-bold mb-4">Découvrez des événements sportifs près de chez vous</h1>
        <p class="slogan">Trouvez et rejoignez des activités sportives organisées par des passionnés dans votre région.</p>
        <div class="hero-search-container">
            <div class="input-group">
                <input type="text" 
                       id="searchInput"
                       class="form-control" 
                       placeholder="Rechercher un événement ou une organisation..." 
                       name="search"
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <button class="btn btn-outline-secondary" type="button" id="searchButton">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/?create=1" class="btn btn-success">
                    Créer un Événement
                </a>
            <?php else: ?>
                <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#loginModal">
                    Créer un Événement
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<main class="container py-4">
    <div class="row mb-4">
        <!-- Filtres -->
        <div class="col-12 col-lg-7 mb-4 mb-lg-0">
            <div class="event-filters p-4 bg-white rounded shadow-sm">
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="m-0"><?= $totalEvents ?> événements trouvés</h4>
                        <button type="button" id="resetFilters" class="btn btn-link text-success">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Réinitialiser
                        </button>
                    </div>

                    <!-- Filtres temporels -->
                    <div class="filter-group mb-3">
                        <button type="button" 
                                class="btn <?= $filters['period'] === 'upcoming' ? 'btn-primary' : 'btn-light' ?>"
                                data-period="upcoming">
                            À venir <span class="badge"><?= $eventCounts['upcoming'] ?? 0 ?></span>
                        </button>
                        <button type="button" 
                                class="btn <?= $filters['period'] === 'today' ? 'btn-primary' : 'btn-light' ?>"
                                data-period="today">
                            Aujourd'hui <span class="badge"><?= $eventCounts['today'] ?? 0 ?></span>
                        </button>
                        <button type="button" 
                                class="btn <?= $filters['period'] === 'past' ? 'btn-primary' : 'btn-light' ?>"
                                data-period="past">
                            Passés <span class="badge"><?= $eventCounts['past'] ?? 0 ?></span>
                        </button>
                    </div>

                    <!-- Filtres de catégorie -->
                    <div class="filter-group">
                        <button type="button" 
                                class="btn <?= $filters['type'] === 'all' ? 'btn-primary' : 'btn-light' ?>"
                                data-type="all">
                            Tous <span class="badge"><?= $eventCounts['all'] ?? 0 ?></span>
                        </button>
                        <button type="button" 
                                class="btn <?= $filters['type'] === 'running' ? 'btn-primary' : 'btn-light' ?>"
                                data-type="running">
                            Course à pied <span class="badge"><?= $eventCounts['running'] ?? 0 ?></span>
                        </button>
                        <button type="button" 
                                class="btn <?= $filters['type'] === 'hiking' ? 'btn-primary' : 'btn-light' ?>"
                                data-type="hiking">
                            Randonnée <span class="badge"><?= $eventCounts['hiking'] ?? 0 ?></span>
                        </button>
                        <button type="button" 
                                class="btn <?= $filters['type'] === 'cycling' ? 'btn-primary' : 'btn-light' ?>"
                                data-type="cycling">
                            Vélo <span class="badge"><?= $eventCounts['cycling'] ?? 0 ?></span>
                        </button>
                    </div>

                    <!-- Filtre de distance -->
                    <div class="filter-distance">
                        <div class="filter-distance-header">
                            <div class="d-flex align-items-center gap-2">
                                <span class="filter-distance-title">Distance maximale</span>
                                <div class="filter-distance-value">
                                    <span id="proximityValue">10</span> km
                                </div>
                            </div>
                            <label class="filter-distance-toggle">
                                <input type="checkbox" id="toggleProximity" checked>
                                <span class="filter-distance-slider"></span>
                            </label>
                        </div>
                        <div id="proximitySection" class="filter-distance-content active">
                            <input type="range" class="form-range" min="0" max="100" value="10" step="5" id="proximity">
                            <div class="range-ticks">
                                <div class="tick" style="left: 0%">
                                    <span class="tick-label">0</span>
                                </div>
                                <div class="tick" style="left: 25%">
                                    <span class="tick-label">25</span>
                                </div>
                                <div class="tick" style="left: 50%">
                                    <span class="tick-label">50</span>
                                </div>
                                <div class="tick" style="left: 75%">
                                    <span class="tick-label">75</span>
                                </div>
                                <div class="tick" style="left: 100%">
                                    <span class="tick-label">100</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendrier -->
        <div class="col-12 col-lg-5">
            <div class="calendar-container bg-white rounded shadow-sm h-100">
                <div class="calendar-nav p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" id="prevMonth" class="btn btn-link p-0">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span id="currentMonth" class="current-month"></span>
                        <button type="button" id="nextMonth" class="btn btn-link p-0">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div class="calendar-body p-3">
                    <table class="calendar">
                        <thead>
                            <tr>
                                <th>Lu</th>
                                <th>Ma</th>
                                <th>Me</th>
                                <th>Je</th>
                                <th>Ve</th>
                                <th>Sa</th>
                                <th>Di</th>
                            </tr>
                        </thead>
                        <tbody id="calendar-body">
                            <!-- Le contenu du calendrier sera généré en JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Carte -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div id="map" style="height: 600px;"></div>
            </div>
        </div>
    </div>

    <!-- Liste des événements -->
    <?php
    error_log("[HOME.PHP] Début de la section des événements");
    error_log("[HOME.PHP] Nombre d'événements à afficher: " . count($currentPageEvents));
    ?>
    <div class="events-wrapper">
        <div class="row g-4">
            <?php 
            if (empty($currentPageEvents)) {
                error_log("[HOME.PHP] Aucun événement à afficher");
            }
            foreach ($currentPageEvents as $event): 
                error_log("[HOME.PHP] Traitement de l'événement #" . $event['id']);
                $eventUrl = "/templates/events/event-detail.php?id=" . htmlspecialchars($event['id']);
            ?>
            <div class="col-md-6 col-lg-4">
                <?php error_log("[HOME.PHP] Génération de la carte pour l'événement #" . $event['id']); ?>
                <div class="modern-event-card">
                    <div class="modern-event-image-wrapper">
                        <img class="modern-event-image" 
                             src="<?= !empty($event['main_image_path']) ? $event['main_image_path'] : '/assets/images/default-event.jpg' ?>" 
                             alt="<?= htmlspecialchars($event['title']) ?>"
                             onerror="this.src='/assets/images/default-event.jpg'">
                        <?php
                        $categoryIcons = [
                            'hiking' => 'bi-person-walking',
                            'running' => 'bi-person-walking',
                            'cycling' => 'bi-bicycle'
                        ];
                        $categoryIcon = isset($categoryIcons[$event['category']]) ? $categoryIcons[$event['category']] : 'bi-calendar-event';
                        ?>
                        <div class="modern-event-badge">
                            <i class="bi <?= $categoryIcon ?>"></i>
                            <?= htmlspecialchars(ucfirst($event['category'] ?: 'Event')) ?>
                        </div>
                    </div>
                    <div class="modern-event-content">
                        <h3 class="modern-event-title"><?= htmlspecialchars($event['title']) ?></h3>
                        <div class="modern-event-info">
                            <div class="modern-event-detail">
                                <i class="bi bi-calendar-event"></i>
                                <span><?= date('d/m/Y', strtotime($event['date'])) ?></span>
                            </div>
                            <div class="modern-event-detail">
                                <i class="bi bi-clock"></i>
                                <span><?= date('H:i', strtotime($event['start_time'])) ?></span>
                            </div>
                            <?php if (isset($event['location']) && trim($event['location']) !== ''): ?>
                            <div class="modern-event-detail">
                                <i class="bi bi-geo-alt"></i>
                                <span><?= htmlspecialchars($event['location']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-primary w-100" href="<?= $eventUrl ?>">Voir les détails</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Navigation des pages">
        <ul class="custom-pagination">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $baseUrl; ?>page=<?php echo $page - 1; ?>" aria-label="Précédent">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $baseUrl; ?>page=<?php echo $page + 1; ?>" aria-label="Suivant">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</main>

<!-- Debug CSS -->
<?php
$cssPath = __DIR__ . '/../assets/css/events.css';
error_log("[HOME.PHP] Vérification du fichier CSS: $cssPath");
error_log("[HOME.PHP] Le fichier CSS existe: " . (file_exists($cssPath) ? 'oui' : 'non'));
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Log pour vérifier si le fichier CSS est chargé
    const eventsCSS = document.querySelector('link[href*="events.css"]');

    // Log pour vérifier les cartes d'événements
    const eventCards = document.querySelectorAll('.event-card');

    // Log pour vérifier le chemin du CSS
    const siteUrl = window.location.origin;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>