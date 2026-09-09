<?php
require_once __DIR__ . '/../src/Models/Event.php';
require_once __DIR__ . '/../config/database.php';



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

// Réindexer le tableau pour éviter les problèmes avec array_slice
$filteredEvents = array_values($filteredEvents);

$totalEvents = count($filteredEvents);
$totalPages = ceil($totalEvents / $eventsPerPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($page - 1) * $eventsPerPage;

// Sélectionner uniquement les événements de la page courante
$currentPageEvents = array_slice($filteredEvents, $offset, $eventsPerPage);


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
    ?>
   <div class="events-wrapper">
    <?php 
    ?>
    <div class="d-flex flex-wrap">
        <?php foreach ($currentPageEvents as $event): 
        ?>
            <div class="col-12 col-sm-6 col-lg-4">
                <?php 
                include __DIR__ . '/events/event-card.php';
                ?>
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
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Log pour vérifier si le fichier CSS est chargé
    const eventsCSS = document.querySelector('link[href*="events.css"]');

    // Log pour vérifier les cartes d'événements
    const modernEventCards = document.querySelectorAll('.modern-event-card');
    
    // Vérifier les styles appliqués
    if (modernEventCards.length > 0) {
        const firstCard = modernEventCards[0];
        const computedStyle = window.getComputedStyle(firstCard);
        console.log({
            display: computedStyle.display,
            flexDirection: computedStyle.flexDirection,
            backgroundColor: computedStyle.backgroundColor,
            borderRadius: computedStyle.borderRadius,
            boxShadow: computedStyle.boxShadow
        });
    }

    // Vérifier si le bouton est présent
    const detailsButtons = document.querySelectorAll('.btn-voir-details');

});
</script>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>