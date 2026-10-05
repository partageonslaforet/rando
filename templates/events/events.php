<?php
/**
 * localisation: templates/events/events.php
 * Role: Template evenement : Events
 * Usage: Logique de listing et affichage des evenements
 * Dépendances: Aucune
 */
function events_log(string $message) {
    error_log("[EVENTS.PHP] " . $message);
    echo "<!-- DEBUG: " . htmlspecialchars($message) . " -->\n";
}

events_log("=== DÉBUT EVENTS.PHP ===");

// Vérification des dépendances
events_log("Vérification des dépendances");
if (!isset($eventManager)) {
    events_log("❌ Event Manager non défini");
    die('Event Manager non défini');
}
events_log("✓ Event Manager trouvé");

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
events_log("Pagination: page=" . $page . ", limit=" . $limit);

// Filtres
$filters = [
    'category' => $_GET['category'] ?? null,
    'period' => $_GET['period'] ?? 'upcoming'
];
events_log("Filtres: " . print_r($filters, true));

// Récupération des événements
try {
    events_log("Tentative de récupération des événements");
    $result = $eventManager->getAll($filters, $page, $limit);
    $events = $result['events'];
    $totalPages = $result['total_pages'];
    
    events_log("Événements récupérés: " . count($events) . " événements, " . $totalPages . " pages au total");
    
    events_log("=== DEBUG PAGINATION ===");
    events_log("Page demandée: " . $page);
    events_log("Limite par page: " . $limit);
    events_log("Nombre total de pages: " . $totalPages);
    events_log("Nombre d'événements sur cette page: " . count($events));
    events_log("=== FIN DEBUG PAGINATION ===");
} catch (Exception $e) {
    events_log("❌ Erreur: " . $e->getMessage());
    die('Erreur lors de la récupération des événements: ' . $e->getMessage());
}
?>

<!-- Liste des événements 
<div class="row">
     Filtres 
    <div class="col-md-3">
         div class="event-filters p-4 bg-white rounded shadow-sm sticky-top">
            <h4 class="mb-4">Filtres</h4>
            
             Filtres temporels 
            <div class="mb-4">
                <h5 class="mb-3">Période</h5>
                <div class="d-flex flex-column gap-2">
                    <?php events_log("Affichage des filtres de période"); ?>
                    <button class="btn btn-outline-primary <?php echo (!isset($_GET['period']) || $_GET['period'] === 'upcoming') ? 'active' : ''; ?>" 
                            onclick="window.location.href='?period=upcoming'">
                        <i class="bi bi-calendar-event me-2"></i>À venir
                    </button>
                    <button class="btn btn-outline-primary <?php echo isset($_GET['period']) && $_GET['period'] === 'today' ? 'active' : ''; ?>"
                            onclick="window.location.href='?period=today'">
                        <i class="bi bi-calendar-check me-2"></i>Aujourd'hui
                    </button>
                    <button class="btn btn-outline-primary <?php echo isset($_GET['period']) && $_GET['period'] === 'past' ? 'active' : ''; ?>"
                            onclick="window.location.href='?period=past'">
                        <i class="bi bi-calendar-x me-2"></i>Passés
                    </button>
                </div>
            </div> -->

            <!-- Catégories 
            <div class="mb-4">
                <h5 class="mb-3">Catégories</h5>
                <div class="d-flex flex-column gap-2">
                    <?php events_log("Affichage des filtres de catégorie"); ?>
                    <button class="btn btn-outline-primary <?php echo !isset($_GET['category']) ? 'active' : ''; ?>"
                            onclick="window.location.href='?'">
                        <i class="bi bi-grid me-2"></i>Toutes
                    </button>
                    <button class="btn btn-outline-primary <?php echo isset($_GET['category']) && $_GET['category'] === 'running' ? 'active' : ''; ?>"
                            onclick="window.location.href='?category=running'">
                        <i class="bi bi-person-running me-2"></i>Course à pied
                    </button>
                    <button class="btn btn-outline-primary <?php echo isset($_GET['category']) && $_GET['category'] === 'hiking' ? 'active' : ''; ?>"
                            onclick="window.location.href='?category=hiking'">
                        <i class="bi bi-signpost-2 me-2"></i>Randonnée
                    </button>
                    <button class="btn btn-outline-primary <?php echo isset($_GET['category']) && $_GET['category'] === 'cycling' ? 'active' : ''; ?>"
                            onclick="window.location.href='?category=cycling'">
                        <i class="bi bi-bicycle me-2"></i>Vélo
                    </button>
                </div>
            </div>

             Distance 
            <div class="mb-4">
                <h5 class="mb-3">Distance maximale</h5>
                <div class="range-container">
                    <input type="range" class="form-range" id="distanceRange" min="0" max="100" step="5" value="10">
                    <div class="range-value">
                        <span id="distanceValue">10</span> km
                    </div>
                </div>
            </div>
        </div>
    </div> --

    <!-- Liste des événements 
    <div class="col-md-9">
        <?php if (empty($events)): ?>
            <?php events_log("Aucun événement trouvé"); ?>
            <div class="alert alert-info">
                Aucun événement trouvé pour les critères sélectionnés.
            </div>
        <?php else: ?>
            <?php 
            error_log("[EVENTS.PHP] Chargement de la liste des événements");
            error_log("[EVENTS.PHP] Nombre d'événements à afficher: " . count($events));
            ?>
            <!--  -->

            <!-- Pagination -->
            <script>
            </script>
            <?php if ($totalPages > 1): ?>
                <?php 
                events_log("Affichage de la pagination");
                events_log("Page actuelle: " . $page);
                events_log("Nombre total de pages: " . $totalPages);
                ?>
                <nav aria-label="Navigation des pages" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['category']) ? '&category=' . htmlspecialchars($_GET['category']) : ''; ?><?php echo isset($_GET['period']) ? '&period=' . htmlspecialchars($_GET['period']) : ''; ?>" aria-label="Précédent">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['category']) ? '&category=' . htmlspecialchars($_GET['category']) : ''; ?><?php echo isset($_GET['period']) ? '&period=' . htmlspecialchars($_GET['period']) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['category']) ? '&category=' . htmlspecialchars($_GET['category']) : ''; ?><?php echo isset($_GET['period']) ? '&period=' . htmlspecialchars($_GET['period']) : ''; ?>" aria-label="Suivant">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Gestion du range de distance
const distanceRange = document.getElementById('distanceRange');
const distanceValue = document.getElementById('distanceValue');

distanceRange.addEventListener('input', function() {
    distanceValue.textContent = this.value;
});

// Fonction pour mettre à jour l'URL avec les filtres
function updateFilters(params) {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([key, value]) => {
        if (value === null) {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }
    });
    window.location.href = url.toString();
}

// Gestionnaire pour le filtre de distance
let timeoutId;
distanceRange.addEventListener('change', function() {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
        updateFilters({ distance: this.value });
    }, 500);
});
</script>

<?php 
error_log("[EVENTS.PHP] Début du chargement de events.php");
error_log("[EVENTS.PHP] Variables disponibles: " . print_r(get_defined_vars(), true));
events_log("=== FIN EVENTS.PHP ==="); ?>