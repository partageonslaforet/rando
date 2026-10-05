<?php
/**
 * Tableau de bord administrateur.
 */
// Activer l'affichage des erreurs
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: /?showLogin=1&redirect=' . urlencode('/pages/admin/dashboard.php'));
    exit();
}

// Inclure la configuration et les dépendances
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/Models/EventCategory.php';
require_once __DIR__ . '/../../src/Utils/helpers.php';

try {
    // Connexion à la base de données
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: /');
        exit();
    }

    if ($user['role'] !== 'admin') {
        header('Location: /?error=admin_required');
        exit();
    }

    // Récupérer les statistiques avec vérification des erreurs
    try {
        $stats = [
            'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'total_events' => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
            'pending_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'pending'")->fetchColumn(),
            'active_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved' AND date >= CURDATE()")->fetchColumn()
        ];
    } catch (PDOException $e) {
        logError('pages/admin/dashboard.php', 'stats query failed: ' . $e->getMessage());
        $stats = [
            'total_users' => 0,
            'total_events' => 0,
            'pending_events' => 0,
            'active_events' => 0
        ];
    }

    // Compter les abonnés email (actifs & vérifiés)
    try {
        $stats['total_subscribers'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM event_subscribers WHERE is_active = 1 AND verified_at IS NOT NULL")
            ->fetchColumn();
    } catch (PDOException $e) {
        logError('pages/admin/dashboard.php', 'subscribers count failed: ' . $e->getMessage());
        $stats['total_subscribers'] = 0;
    }

    try {
        $stats['total_organizations'] = $pdo->query("SELECT COUNT(*) FROM organizer_profiles")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur total_organizations : " . $e->getMessage());
        $stats['total_organizations'] = 0;
    }

    try {
        $stats['total_event_views'] = $pdo->query("SELECT COALESCE(SUM(view_count), 0) FROM events")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur total_event_views : " . $e->getMessage());
        $stats['total_event_views'] = 0;
    }

    // Statistiques de fréquentation (site_visits)
    $allowedPeriods = [7, 14, 30, 90];
    $statsPeriod = (int) ($_GET['period'] ?? 14);
    if (!in_array($statsPeriod, $allowedPeriods, true)) {
        $statsPeriod = 14;
    }

    // Vérifier si la colonne is_human est déployée dans la base courante
    $hasIsHuman = false;
    try {
        $hasIsHuman = (bool) $pdo->query("SHOW COLUMNS FROM site_visits LIKE 'is_human'")->fetchColumn();
    } catch (Throwable $e) {
        $hasIsHuman = false;
    }

    $eventTitles = [];

    try {
        $stats['visits_today'] = (int) $pdo->query("SELECT COUNT(*) FROM site_visits WHERE DATE(visited_at) = CURDATE()")->fetchColumn();
        $stats['sessions_today'] = (int) $pdo->query("SELECT COUNT(DISTINCT session_id) FROM site_visits WHERE DATE(visited_at) = CURDATE()")->fetchColumn();
        if ($hasIsHuman) {
            $stats['unique_ips_today'] = (int) $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM site_visits WHERE DATE(visited_at) = CURDATE() AND is_human = 1")->fetchColumn();
            $stats['non_humans_today'] = (int) $pdo->query("SELECT COUNT(*) FROM site_visits WHERE DATE(visited_at) = CURDATE() AND is_human = 0")->fetchColumn();
        } else {
            $stats['unique_ips_today'] = (int) $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM site_visits WHERE DATE(visited_at) = CURDATE()")->fetchColumn();
            $stats['non_humans_today'] = 0;
        }

        if ($hasIsHuman) {
            $stmt = $pdo->prepare("
                SELECT DATE(visited_at) AS day,
                       COUNT(*) AS visits,
                       COUNT(DISTINCT session_id) AS sessions,
                       COUNT(DISTINCT ip_address) AS unique_ips,
                       SUM(CASE WHEN is_human = 0 THEN 1 ELSE 0 END) AS non_humans
                FROM site_visits
                WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
                GROUP BY DATE(visited_at)
                ORDER BY day DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT DATE(visited_at) AS day,
                       COUNT(*) AS visits,
                       COUNT(DISTINCT session_id) AS sessions,
                       COUNT(DISTINCT ip_address) AS unique_ips,
                       0 AS non_humans
                FROM site_visits
                WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
                GROUP BY DATE(visited_at)
                ORDER BY day DESC
            ");
        }
        $stmt->bindValue(':period', $statsPeriod - 1, PDO::PARAM_INT);
        $stmt->execute();
        $visitsPerDay = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT url, COUNT(*) AS visits
            FROM site_visits
            WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
            GROUP BY url
            ORDER BY visits DESC
            LIMIT 50
        ");
        $stmt->bindValue(':period', $statsPeriod - 1, PDO::PARAM_INT);
        $stmt->execute();
        $topPages = $stmt->fetchAll();

        // Agréger les deux formes d'URL d'un même événement (/event?id=N -> /event/N)
        $mergedTopPages = [];
        foreach ($topPages as $row) {
            $u = (string)($row['url'] ?? '');
            if (preg_match('/^\/event\?id=(\d+)$/', $u, $m)) {
                $u = '/event/' . (int)$m[1];
            }
            if (!isset($mergedTopPages[$u])) {
                $mergedTopPages[$u] = ['url' => $u, 'visits' => 0];
            }
            $mergedTopPages[$u]['visits'] += (int)$row['visits'];
        }
        usort($mergedTopPages, function ($a, $b) { return $b['visits'] <=> $a['visits']; });
        $topPages = array_slice(array_values($mergedTopPages), 0, 10);

        if ($hasIsHuman) {
            $recentVisits = $pdo->query("
                SELECT url, ip_address, user_agent, is_human, referrer, visited_at
                FROM site_visits
                ORDER BY visited_at DESC
                LIMIT 30
            ")->fetchAll();
        } else {
            $recentVisits = $pdo->query("
                SELECT url, ip_address, user_agent, 1 AS is_human, referrer, visited_at
                FROM site_visits
                ORDER BY visited_at DESC
                LIMIT 30
            ")->fetchAll();
        }

        // Résolution géo (avec cache) uniquement pour les IP uniques affichées
        $ipCache = [];
        foreach ($recentVisits as &$visit) {
            $ip = $visit['ip_address'] ?? '';
            if (!isset($ipCache[$ip])) {
                $ipCache[$ip] = resolveIpLocation($ip);
            }
            $visit['country'] = $ipCache[$ip]['country'];
            $visit['city'] = $ipCache[$ip]['city'];
        }
        unset($visit);

        // Titres d'événements pour les URLs /event?id=XXX et /event/XXX
        $eventIds = [];
        foreach (array_merge($topPages, $recentVisits) as $r) {
            $url = $r['url'] ?? '';
            if (preg_match('/^\/event\?id=(\d+)$/', $url, $m) || preg_match('/^\/event\/(\d+)$/', $url, $m)) {
                $eventIds[(int) $m[1]] = true;
            }
        }
        if (!empty($eventIds)) {
            $ids = array_keys($eventIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $titleStmt = $pdo->prepare("SELECT id, title FROM events WHERE id IN ($placeholders)");
            $titleStmt->execute($ids);
            $eventTitles = $titleStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        // Visites par pays
        try {
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT g.country) AS total_countries
                FROM site_visits s
                INNER JOIN ip_geo_cache g ON s.ip_address = g.ip_address
                WHERE s.visited_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                  AND s.url NOT REGEXP '\\\\.(png|jpg|jpeg|gif|svg|ico|css|js|txt|xml|json|woff|woff2|ttf|eot|map|pdf|webp|bmp|mp4|mp3|webm|ogg|avif|zip|gz|tar)$'
            ");
            $totalCountries7 = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Erreur visites par pays : " . $e->getMessage());
            $totalCountries7 = 0;
        }

        // Visites avec referrer externe (hors rando.*)
        $totalReferred7 = (int) $pdo->query("
            SELECT COUNT(*) FROM site_visits
            WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
              AND referrer IS NOT NULL AND referrer <> ''
              AND referrer NOT LIKE '%rando.partageonslaforet%'
        ")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur statistiques de fréquentation : " . $e->getMessage());
        $statsError = $e->getMessage();
        $stats['visits_today'] = 0;
        $stats['sessions_today'] = 0;
        $stats['unique_ips_today'] = 0;
        $stats['non_humans_today'] = 0;
        $visitsPerDay = [];
        $topPages = [];
        $recentVisits = [];
        $totalCountries7 = 0;
        $totalReferred7 = 0;
    }

    // Libellé lisible d'une page : /event?id=XXX → titre de l'événement
    $pageLabel = function ($url) use ($eventTitles) {
        $url = (string) $url;
        if (preg_match('/^\/event\?id=(\d+)$/', $url, $m) || preg_match('/^\/event\/(\d+)$/', $url, $m)) {
            $t = $eventTitles[(int) $m[1]] ?? '';
            return $t !== '' ? 'Événement : ' . $t : $url;
        }
        if ($url === '/') return 'Accueil';
        if ($url === '/?create=1') return 'Accueil (création)';
        return $url;
    };

    // Totaux pour les 4 tuiles de fréquentation (7 derniers jours)
    $totalVisits7 = (int) array_sum(array_column($visitsPerDay, 'visits'));
    $totalPages7 = count($topPages);
    $totalRecent7 = count($recentVisits);

    // Récupérer les événements récents avec leurs catégories
    try {
        $recentEvents = $pdo->query("
            SELECT e.*, u.name as organizer_name, c.name as category_name, c.icon as category_icon, c.color as category_color
            FROM events e 
            JOIN users u ON e.user_id = u.id 
            LEFT JOIN event_categories c ON e.category_id = c.id
            ORDER BY e.created_at DESC 
            LIMIT 10
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des événements récents : " . $e->getMessage());
        $recentEvents = [];
    }

    // Récupérer toutes les catégories
    $categoryManager = new EventCategory($pdo);
    $categories = $categoryManager->getAll();
    // Tri alphabétique sur le nom uniquement (insensible à la casse)
    if (is_array($categories)) {
        usort($categories, function($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
    }

} catch (PDOException $e) {
    error_log("Erreur base de données: " . $e->getMessage());
    header('Location: /?error=db');
    exit();
}

// Titre de la page
$pageTitle = "Administration";
$additionalStyles = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.css">' . "\n"
                  . '<link rel="stylesheet" href="/assets/css/pages/admin/dashboard.css">' . "\n"
                  . '<link rel="stylesheet" href="/assets/css/pages/admin/admin-dashboard.css">';

// Inclure l'en-tête
include __DIR__ . '/../../templates/layouts/header-solid.php';
?>

<div class="container mt-4">
  <!-- Hero -->
  <div class="dash-hero d-flex justify-content-between align-items-center">
    <h1>Tableau de bord</h1>
    <div class="hero-actions">
      <!-- <a href="/?create=1" class="btn btn-success btn-pill"><i class="bi bi-plus-lg"></i> Créer un événement</a>
      <a href="/pages/admin/events.php" class="btn btn-outline-secondary btn-pill ms-2"><i class="bi bi-list-ul"></i> Tous les événements</a> -->
    </div>
  </div>

  <!-- Tuiles de fréquentation cliquables -->
  <div class="summary-tiles">
    <div class="summary-card" data-stat-modal="visits" role="button" tabindex="0">
      <div class="summary-icon views"><i class="bi bi-graph-up"></i></div>
      <div>
        <div class="summary-label">VISITES (7j)</div>
        <div class="summary-value"><?= (int)$totalVisits7 ?></div>
      </div>
    </div>
    <div class="summary-card" data-stat-modal="pages" role="button" tabindex="0">
      <div class="summary-icon views"><i class="bi bi-file-earmark-text"></i></div>
      <div>
        <div class="summary-label">PAGES LES PLUS VISITÉES (7j)</div>
        <div class="summary-value"><?= (int)$totalPages7 ?></div>
      </div>
    </div>
    <div class="summary-card" data-stat-modal="recent" role="button" tabindex="0">
      <div class="summary-icon users"><i class="bi bi-geo-alt"></i></div>
      <div>
        <div class="summary-label">DERNIÈRES VISITES (IP &amp; LOC)</div>
        <div class="summary-value"><?= (int)$totalRecent7 ?></div>
      </div>
    </div>
    <div class="summary-card" data-stat-modal="countries" role="button" tabindex="0">
      <div class="summary-icon users"><i class="bi bi-globe"></i></div>
      <div>
        <div class="summary-label">VISITES PAR PAYS</div>
        <div class="summary-value"><?= (int)$totalCountries7 ?></div>
      </div>
    </div>
    <div class="summary-card" data-stat-modal="sources" role="button" tabindex="0">
      <div class="summary-icon users"><i class="bi bi-box-arrow-in-right"></i></div>
      <div>
        <div class="summary-label">VISITES RÉFÉRÉES (7j)</div>
        <div class="summary-value"><?= (int)$totalReferred7 ?></div>
      </div>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpi-grid">
    <div class="kpi-card" data-kpi-modal="users" role="button" tabindex="0">
      <div class="kpi-icon users"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="kpi-label">UTILISATEURS</div>
        <div class="kpi-value"><?php echo (int)$stats['total_users']; ?></div>
      </div>
    </div>
    <div class="kpi-card" data-kpi-modal="organizations" role="button" tabindex="0">
      <div class="kpi-icon users"><i class="bi bi-building"></i></div>
      <div>
        <div class="kpi-label">ASSOCIATIONS</div>
        <div class="kpi-value"><?php echo (int)($stats['total_organizations'] ?? 0); ?></div>
      </div>
    </div>
    <div class="kpi-card" data-kpi-modal="subscribers" role="button" tabindex="0" title="Abonnés aux notifications (actifs &amp; vérifiés)">
      <div class="kpi-icon users"><i class="bi bi-bell-fill"></i></div>
      <div>
        <div class="kpi-label">ABONNÉS NOTIFS</div>
        <div class="kpi-value"><?php echo (int)($stats['total_subscribers'] ?? 0); ?></div>
      </div>
    </div>
    <div class="kpi-card" data-kpi-modal="events" role="button" tabindex="0">
      <div class="kpi-icon events"><i class="bi bi-calendar-event-fill"></i></div>
      <div>
        <div class="kpi-label">ÉVÉNEMENTS</div>
        <div class="kpi-value"><?php echo (int)$stats['total_events']; ?></div>
      </div>
    </div>
    <div class="kpi-card" data-kpi-modal="pending" role="button" tabindex="0">
      <div class="kpi-icon pending"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="kpi-label">EN ATTENTE</div>
        <div class="kpi-value"><?php echo (int)$stats['pending_events']; ?></div>
      </div>
    </div>
    <div class="kpi-card" data-kpi-modal="active" role="button" tabindex="0">
      <div class="kpi-icon active"><i class="bi bi-check2-circle"></i></div>
      <div>
        <div class="kpi-label">ACTIFS</div>
        <div class="kpi-value"><?php echo (int)$stats['active_events']; ?></div>
      </div>
    </div>
    <div class="kpi-card" data-kpi-modal="categories" role="button" tabindex="0">
      <div class="kpi-icon categories"><i class="bi bi-tags-fill"></i></div>
      <div>
        <div class="kpi-label">CATÉGORIES</div>
        <div class="kpi-value"><?php echo is_array($categories) ? count($categories) : 0; ?></div>
      </div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="dash-toolbar">
    <div class="pills">
      <button type="button" class="pill active" data-filter="all">Tous</button>
      <button type="button" class="pill" data-filter="pending">En attente</button>
      <button type="button" class="pill" data-filter="approved">Actifs</button>
      <button type="button" class="pill" data-filter="rejected">Rejetés</button>
      <button type="button" class="pill" data-filter="expired">Échus</button>
    </div>
    <div class="search"><input type="search" id="dashSearch" class="form-control" placeholder="Rechercher par titre ou organisateur…"></div>
  </div>

  <div class="row g-4">
    <aside class="col-lg-3">
      <div class="dash-sidenav">
        <nav class="nav flex-column">
          <a href="#" class="nav-link active" data-target="section-events"><i class="bi bi-calendar-event me-1"></i> Événements</a>
          <a href="#" class="nav-link" data-target="section-categories"><i class="bi bi-tags-fill me-1"></i> Catégories</a>
          <a href="/pages/admin/sponsors.php" class="nav-link"><i class="bi bi-megaphone me-1"></i> Sponsors</a>
        </nav>
      </div>
    </aside>

    <main class="col-lg-9">
      <!-- Section Événements -->
      <section id="section-events" data-section>
        <!-- Événements récents -->
        <div class="card">
          <div class="card-body">
            <h5 class="card-title mb-3"><i class="bi bi-clock-history"></i> Événements récents</h5>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>DATE</th>
                    <th>TITRE</th>
                    <th>ORGANISATEUR</th>
                    <th>STATUT</th>
                    <th class="text-end actions-col">ACTIONS</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($recentEvents as $event): 
                  $status = strtolower($event['status'] ?? 'pending');
                  $badgeClass = $status === 'approved' ? 'status-approved' : ($status === 'rejected' ? 'status-rejected' : 'status-pending');
                  $dateTxt = !empty($event['date']) ? date('d/m/Y', strtotime($event['date'])) : '-';
                  $titleTxt = h($event['title'] ?? 'Sans titre');
                  $orgTxt = h($event['organizer_name'] ?? '-');
                  $isExpired = !empty($event['date']) && (strtotime($event['date']) < strtotime(date('Y-m-d')));
                ?>
                  <tr data-status="<?= $status ?>" data-title="<?= $titleTxt ?>" data-org="<?= $orgTxt ?>" data-expired="<?= $isExpired ? '1' : '0' ?>">
                    <td><?= $dateTxt ?></td>
                    <td>
                      <?= $titleTxt ?><br>
                      <small class="text-muted">
                        <?php
                          $rawIcon = $event['category_icon'] ?? '';
                          $iconClass = 'bi bi-tree';
                          if ($rawIcon) {
                            if (str_starts_with($rawIcon, 'fa')) {
                              $iconClass = (str_contains($rawIcon, 'fa-') && !str_contains($rawIcon, 'fa-solid') && !str_starts_with($rawIcon, 'fas ')) ? ('fa-solid ' . $rawIcon) : $rawIcon;
                            } elseif (str_starts_with($rawIcon, 'bi-')) {
                              $iconClass = 'bi ' . $rawIcon;
                            }
                          }
                        ?>
                        <i class="<?= htmlspecialchars($iconClass) ?> cat-icon" style="--cat-color: <?= htmlspecialchars($event['category_color'] ?? '') ?>"></i>
                        <?= h($event['category_name'] ?? '') ?>
                      </small>
                    </td>
                    <td><?= $orgTxt ?></td>
                    <td><span class="status-badge <?= $badgeClass ?>"><?php if ($status === 'approved'): ?>Actif<?php elseif ($status === 'rejected'): ?>Rejeté<?php else: ?>En attente<?php endif; ?></span></td>
                    <td class="text-end actions-cell">
                      <a href="/pages/admin/view_event.php?id=<?= (int)$event['id'] ?>" class="btn btn-sm btn-view btn-pill"><i class="bi bi-eye"></i> Voir</a>
                      <button type="button" class="btn btn-sm btn-delete btn-pill" onclick="deleteEvent(<?= (int)$event['id'] ?>)"><i class="bi bi-trash"></i> Suppr.</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($recentEvents)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-4">Aucun événement récent</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- Section Catégories -->
      <section id="section-categories" class="d-none" data-section>
        <div class="card">
          <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0">Gestion des catégories</h5>
            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#categoryModal">
              <i class="bi bi-plus"></i> Nouvelle catégorie
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Icône</th>
                    <th>Couleur</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="categoriesTableBody">
                  <?php foreach ($categories as $category): ?>
                    <tr data-id="<?= htmlspecialchars($category['id']) ?>">
                      <td><?= htmlspecialchars($category['code']) ?></td>
                      <td><?= htmlspecialchars($category['name']) ?></td>
                      <td>
                        <?php 
                          $rawIcon = $category['icon'] ?? '';
                          if ($rawIcon) {
                            $iconClass = 'bi bi-tree';
                            if (str_starts_with($rawIcon, 'fa')) {
                              $iconClass = (str_contains($rawIcon, 'fa-') && !str_contains($rawIcon, 'fa-solid') && !str_starts_with($rawIcon, 'fas '))
                                  ? ('fa-solid ' . $rawIcon)
                                  : $rawIcon;
                            } elseif (str_starts_with($rawIcon, 'bi-')) {
                              $iconClass = 'bi ' . $rawIcon;
                            }
                            echo '<i class="' . htmlspecialchars($iconClass) . '"></i>';
                          }
                        ?>
                      </td>
                      <td>
                        <?php if ($category['color']): ?>
                          <span class="color-preview" style="--cat-color: <?= htmlspecialchars($category['color']) ?>"></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn btn-sm btn-outline-primary edit-category"
                                data-id="<?= $category['id'] ?>"
                                data-code="<?= htmlspecialchars($category['code']) ?>"
                                data-name="<?= htmlspecialchars($category['name']) ?>"
                                data-icon="<?= htmlspecialchars($category['icon'] ?? '') ?>"
                                data-color="<?= htmlspecialchars($category['color'] ?? '') ?>">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-category" data-id="<?= $category['id'] ?>">
                          <i class="bi bi-trash"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- Section Statistiques -->
      <section id="section-stats" class="d-none" data-section>
        <?php if (!empty($statsError)): ?>
          <div class="alert alert-danger mb-3">
            <strong>Erreur SQL :</strong> <?= htmlspecialchars($statsError) ?>
          </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0"><i class="bi bi-graph-up"></i> Fréquentation</h5>
          <div class="btn-group" role="group" aria-label="Sélecteur de période">
            <?php foreach ($allowedPeriods as $p): ?>
              <a href="?period=<?= $p ?>#section-stats"
                 class="btn btn-sm <?= $p === $statsPeriod ? 'btn-secondary' : 'btn-outline-secondary' ?> period-link">
                <?= $p ?>j
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-7">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3"><i class="bi bi-calendar-week"></i> Visites des <?= $statsPeriod ?> derniers jours</h5>
                <div class="table-responsive">
                  <table class="table align-middle">
                    <thead>
                      <tr>
                        <th>DATE</th>
                        <th class="text-end">VISITES</th>
                        <th class="text-end">SESSIONS</th>
                        <th class="text-end">VISITEURS UNIQUES (IP)</th>
                        <th class="text-end">NON-HUMAINS</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visitsPerDay as $row): ?>
                      <tr>
                        <td><a href="#" data-visit-date="<?= htmlspecialchars($row['day']) ?>"><?= date('d/m/Y', strtotime($row['day'])) ?></a></td>
                        <td class="text-end"><?= (int)$row['visits'] ?></td>
                        <td class="text-end"><?= (int)$row['sessions'] ?></td>
                        <td class="text-end"><?= (int)$row['unique_ips'] ?></td>
                        <td class="text-end"><?= (int)$row['non_humans'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($visitsPerDay)): ?>
                      <tr><td colspan="5" class="text-center text-muted py-4">Aucune donnée de visite</td></tr>
                    <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3"><i class="bi bi-file-earmark-text"></i> Pages les plus visitées (<?= $statsPeriod ?>j)</h5>
                <div class="table-responsive">
                  <table class="table align-middle">
                    <thead>
                      <tr>
                        <th>PAGE</th>
                        <th class="text-end">VISITES</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topPages as $row): ?>
                      <tr>
                        <td><?= htmlspecialchars($pageLabel($row['url'])) ?></td>
                        <td class="text-end"><?= (int)$row['visits'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topPages)): ?>
                      <tr><td colspan="2" class="text-center text-muted py-4">Aucune donnée de visite</td></tr>
                    <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-12">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3"><i class="bi bi-geo-alt"></i> Dernières visites (IP &amp; localisation)</h5>
                <div class="table-responsive">
                  <table class="table align-middle">
                    <thead>
                      <tr>
                        <th>DATE</th>
                        <th>PAGE</th>
                        <th>IP</th>
                        <th>LOCALISATION</th>
                        <th>TYPE</th>
                        <th>SOURCE</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentVisits as $row): ?>
                      <tr>
                        <td><?= date('d/m/Y H:i', strtotime($row['visited_at'])) ?></td>
                        <td><?= htmlspecialchars($pageLabel($row['url'])) ?></td>
                        <td><?php if (!empty($row['ip_address'])): ?><a href="#" data-ip="<?= htmlspecialchars($row['ip_address']) ?>"><?= htmlspecialchars($row['ip_address']) ?></a><?php else: ?>-<?php endif; ?></td>
                        <td>
                          <?php
                            $loc = trim(implode(', ', array_filter([$row['city'] ?? null, $row['country'] ?? null])));
                            echo $loc !== '' ? htmlspecialchars($loc) : '<span class="text-muted">Inconnue</span>';
                          ?>
                        </td>
                        <td>
                          <?php if (empty($row['is_human'])): ?>
                            <span class="badge bg-warning text-dark">Non-humain</span>
                          <?php else: ?>
                            <span class="badge bg-success">Humain</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php
                            $ref = $row['referrer'] ?? '';
                            $fromPlf = $ref !== ''
                                && stripos($ref, 'partageonslaforet') !== false
                                && stripos($ref, 'rando.partageonslaforet') === false;
                          ?>
                          <?php if ($fromPlf): ?>
                            <span class="badge bg-info text-dark" title="<?= htmlspecialchars($ref) ?>">PLF</span>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentVisits)): ?>
                      <tr><td colspan="6" class="text-center text-muted py-4">Aucune donnée de visite</td></tr>
                    <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

    <!-- Modal statistiques de fréquentation -->
    <div class="modal fade" id="statsModal" tabindex="-1" aria-labelledby="statsModalTitle" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="statsModalTitle">Fréquentation</h5>
            <div class="d-flex align-items-center flex-wrap gap-2 ms-auto me-2">
              <div class="btn-group" role="group" aria-label="Période">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-stat-period="1">Aujourd'hui</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-stat-period="7">7j</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-stat-period="14">14j</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-stat-period="30">30j</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-stat-period="90">90j</button>
              </div>
              <div class="input-group stats-date-input">
                <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                <input type="date" class="form-control form-control-sm" id="statsModalDate">
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="statsModalBody"></div>
          </div>
          <div class="modal-footer">
            <div id="statsModalFooter" class="flex-grow-1"></div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal détail KPI -->
    <div class="modal fade" id="kpiModal" tabindex="-1" aria-labelledby="kpiModalTitle" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="kpiModalTitle">Détail</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="kpiModalBody"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal pour les catégories -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="categoryForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Catégorie</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="category_id" name="id">
                        
                        <div class="mb-3">
                            <label for="category_code" class="form-label">Code</label>
                            <input type="text" class="form-control" id="category_code" name="code" required>
                            <div class="form-text">Code unique pour identifier la catégorie (ex: hiking)</div>
                        </div>

                        <div class="mb-3">
                            <label for="category_name" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="category_name" name="name" required>
                            <div class="form-text">Nom affiché aux utilisateurs (ex: Randonnée)</div>
                        </div>

                        <div class="mb-3">
                            <label for="category_icon" class="form-label">Icône</label>
                            <input type="text" class="form-control" id="category_icon" name="icon">
                            <div class="form-text">Classe d'icône Bootstrap (ex: bi-bicycle)</div>
                        </div>

                        <div class="mb-3">
                            <label for="category_color" class="form-label">Couleur</label>
                            <input type="color" class="form-control" id="category_color" name="color">
                            <div class="form-text">Couleur pour le badge de la catégorie</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-secondary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script src="/assets/js/admin/categories.js"></script>
    <script src="/assets/js/admin/admin-dashboard.js"></script>
    <script src="/assets/js/admin/dashboard-stats.js"></script>
    <script src="/assets/js/admin/dashboard-kpis.js"></script>
    <script>
    function updateEventStatus(eventId, status) {
        if (!confirm('Êtes-vous sûr de vouloir ' + (status === 'approved' ? 'approuver' : 'rejeter') + ' cet événement ?')) {
            return;
        }

        fetch('/api/admin/events/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                event_id: eventId,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    }

    function deleteEvent(eventId) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')) {
            return;
        }

        fetch('/api/admin/events/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                event_id: eventId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    }

    // Ancien filtre retiré (remplacé par admin-dashboard.js)

    </script>

</div>
    <?php include __DIR__ . '/../../templates/layouts/footer.php'; ?>
