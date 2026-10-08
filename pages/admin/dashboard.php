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

        // Visites par pays (toutes périodes)
        try {
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT g.country) AS total_countries
                FROM site_visits s
                INNER JOIN ip_geo_cache g ON s.ip_address = g.ip_address
                WHERE s.url NOT REGEXP '\\\\.(png|jpg|jpeg|gif|svg|ico|css|js|txt|xml|json|woff|woff2|ttf|eot|map|pdf|webp|bmp|mp4|mp3|webm|ogg|avif|zip|gz|tar)$'
            ");
            $totalCountries = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Erreur visites par pays : " . $e->getMessage());
            $totalCountries = 0;
        }

        // Visites avec referrer externe, toutes périodes (hors rando.*)
        $totalReferred = (int) $pdo->query("
            SELECT COUNT(*) FROM site_visits
            WHERE referrer IS NOT NULL AND referrer <> ''
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
        $totalCountries = 0;
        $totalReferred = 0;
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

    // Totaux pour les tuiles de fréquentation (toutes périodes, sauf dernières visites)
    $totalVisits = 0;
    $totalPages = 0;
    try {
        $totalVisits = (int) $pdo->query("SELECT COUNT(*) FROM site_visits")->fetchColumn();
        $totalPages = (int) $pdo->query("SELECT COUNT(DISTINCT url) FROM site_visits")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur totaux tuiles fréquentation : " . $e->getMessage());
    }
    $totalRecent7 = count($recentVisits);

    // Récupérer tous les événements avec leurs catégories (les pills filtrent l'ensemble, comme les tuiles KPI)
    try {
        $recentEvents = $pdo->query("
            SELECT e.*, u.name as organizer_name, c.name as category_name, c.icon as category_icon, c.color as category_color
            FROM events e 
            JOIN users u ON e.user_id = u.id 
            LEFT JOIN event_categories c ON e.category_id = c.id
            ORDER BY e.created_at DESC
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des événements récents : " . $e->getMessage());
        $recentEvents = [];
    }

    // Statut dérivé identique à my-events.php : 'expired' = statut DB OU approuvé dont la date est passée
    $pillCounts = ['all' => count($recentEvents), 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'expired' => 0];
    foreach ($recentEvents as $e) {
        $st = strtolower($e['status'] ?? 'pending');
        $exp = !empty($e['date']) && strtotime($e['date']) < strtotime('today');
        if ($st === 'expired' || ($st === 'approved' && $exp)) $pillCounts['expired']++;
        elseif ($st === 'approved') $pillCounts['approved']++;
        elseif ($st === 'rejected') $pillCounts['rejected']++;
        else $pillCounts['pending']++;
    }

    // Sponsors pour la section dédiée
    try {
        $sponsors = $pdo->query('SELECT * FROM sponsors ORDER BY position ASC, id ASC')->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur récupération sponsors : " . $e->getMessage());
        $sponsors = [];
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
$additionalStyles = '<link rel="stylesheet" href="/assets/css/pages/admin/dashboard.css">' . "\n"
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
        <div class="summary-label">VISITES</div>
        <div class="summary-value"><?= (int)$totalVisits ?></div>
      </div>
    </div>
    <div class="summary-card" data-stat-modal="pages" role="button" tabindex="0">
      <div class="summary-icon views"><i class="bi bi-file-earmark-text"></i></div>
      <div>
        <div class="summary-label">PAGES VISITÉES</div>
        <div class="summary-value"><?= (int)$totalPages ?></div>
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
        <div class="summary-value"><?= (int)$totalCountries ?></div>
      </div>
    </div>
    <div class="summary-card" data-stat-modal="sources" role="button" tabindex="0">
      <div class="summary-icon users"><i class="bi bi-box-arrow-in-right"></i></div>
      <div>
        <div class="summary-label">VISITES RÉFÉRÉES</div>
        <div class="summary-value"><?= (int)$totalReferred ?></div>
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
      <button type="button" class="pill active" data-filter="all">Tous <span class="pill-count"><?= (int)$pillCounts['all'] ?></span></button>
      <button type="button" class="pill" data-filter="pending">En attente <span class="pill-count"><?= (int)$pillCounts['pending'] ?></span></button>
      <button type="button" class="pill" data-filter="approved">Actifs <span class="pill-count"><?= (int)$pillCounts['approved'] ?></span></button>
      <button type="button" class="pill" data-filter="rejected">Rejetés <span class="pill-count"><?= (int)$pillCounts['rejected'] ?></span></button>
      <button type="button" class="pill" data-filter="expired">Échus <span class="pill-count"><?= (int)$pillCounts['expired'] ?></span></button>
    </div>
    <div class="search"><input type="search" id="dashSearch" class="form-control" placeholder="Rechercher par titre ou organisateur…"></div>
  </div>

  <div class="row g-4">
    <aside class="col-lg-3">
      <div class="dash-sidenav">
        <nav class="nav flex-column">
          <a href="#" class="nav-link active" data-target="section-events"><i class="bi bi-calendar-event me-1"></i> Événements</a>
          <a href="#" class="nav-link" data-target="section-categories"><i class="bi bi-tags-fill me-1"></i> Catégories</a>
          <a href="#section-sponsors" class="nav-link" data-target="section-sponsors"><i class="bi bi-megaphone me-1"></i> Sponsors</a>
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
                  $rawStatus = strtolower($event['status'] ?? 'pending');
                  $isExpired = !empty($event['date']) && strtotime($event['date']) < strtotime('today');
                  // Même logique que les compteurs : 'expired' = statut DB ou approuvé échu
                  $status = ($rawStatus === 'expired' || ($rawStatus === 'approved' && $isExpired)) ? 'expired' : $rawStatus;
                  $badgeClass = $status === 'approved' ? 'status-approved' : ($status === 'rejected' ? 'status-rejected' : ($status === 'expired' ? 'status-expired' : 'status-pending'));
                  $dateTxt = !empty($event['date']) ? date('d/m/Y', strtotime($event['date'])) : '-';
                  $titleTxt = h($event['title'] ?? 'Sans titre');
                  $orgTxt = h($event['organizer_name'] ?? '-');
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
                    <td><span class="status-badge <?= $badgeClass ?>"><?php if ($status === 'approved'): ?>Actif<?php elseif ($status === 'rejected'): ?>Rejeté<?php elseif ($status === 'expired'): ?>Échu<?php else: ?>En attente<?php endif; ?></span></td>
                    <td class="text-end actions-cell">
                      <a href="/pages/admin/view_event.php?id=<?= (int)$event['id'] ?>" class="btn btn-sm btn-view btn-pill"><i class="bi bi-eye"></i> Voir</a>
                      <?php if ($status === 'approved'): ?>
                        <?php $alreadyNotified = !empty($event['organizer_notified_at']); ?>
                        <button type="button" class="btn btn-sm btn-<?= $alreadyNotified ? 'outline-secondary' : 'btn-notify' ?> btn-pill"
                                onclick="notifyOrganizer(<?= (int)$event['id'] ?>)"
                                title="<?= $alreadyNotified
                                    ? 'Organisateur déjà notifié le ' . date('d/m/Y H:i', strtotime($event['organizer_notified_at'])) . ' — cliquer pour renvoyer'
                                    : 'Notifier l\'organisateur par email' ?>">
                          <i class="bi <?= $alreadyNotified ? 'bi-envelope-check' : 'bi-envelope' ?>"></i>
                        </button>
                      <?php endif; ?>
                      <button type="button" class="btn btn-sm btn-delete btn-pill" onclick="deleteEvent(<?= (int)$event['id'] ?>)"><i class="bi bi-trash"></i> Suppr.</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($recentEvents)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-4">Aucun événement récent</td></tr>
                <?php endif; ?>
                <tr id="filterEmptyRow" class="d-none"><td colspan="5" class="text-center text-muted py-4">Aucun événement pour ce filtre</td></tr>
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
                    <th class="actions-col">Actions</th>
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
                      <td class="actions-cell">
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

      <!-- Section Sponsors -->
      <section id="section-sponsors" class="d-none" data-section>
        <div class="card">
          <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0">Gestion des sponsors</h5>
            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#sponsorModal">
              <i class="bi bi-plus"></i> Nouveau sponsor
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table align-middle sponsors-table">
                <thead>
                  <tr>
                    <th class="col-visual">Visuel</th>
                    <th>Nom</th>
                    <th>Lien</th>
                    <th class="col-order">Ordre</th>
                    <th class="col-period">Période</th>
                    <th class="col-active">Actif</th>
                    <th class="actions-col">Actions</th>
                  </tr>
                </thead>
                <tbody id="sponsorsTableBody">
                  <?php foreach ($sponsors as $s): ?>
                    <tr data-id="<?= (int)$s['id'] ?>">
                      <td><img src="<?= htmlspecialchars($s['image_path']) ?>" alt="" class="sponsor-thumb"></td>
                      <td><?= htmlspecialchars($s['name']) ?></td>
                      <td>
                        <?php if (!empty($s['link_url'])): ?>
                          <a href="<?= htmlspecialchars($s['link_url']) ?>" target="_blank" rel="noopener" class="d-inline-block text-truncate" style="max-width:180px"><?= htmlspecialchars($s['link_url']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                      </td>
                      <td><?= (int)$s['position'] ?></td>
                      <td>
                        <?php if (!empty($s['start_date']) || !empty($s['end_date'])): ?>
                          <div class="small text-nowrap">
                            <?= !empty($s['start_date']) ? htmlspecialchars(date('d/m/Y', strtotime($s['start_date']))) : '…' ?> →
                            <?= !empty($s['end_date']) ? htmlspecialchars(date('d/m/Y', strtotime($s['end_date']))) : '…' ?>
                          </div>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                        <?php if (!empty($s['start_date']) && $s['start_date'] > date('Y-m-d')): ?>
                          <span class="badge text-bg-warning">Programmé</span>
                        <?php elseif (!empty($s['end_date']) && $s['end_date'] < date('Y-m-d')): ?>
                          <span class="badge text-bg-secondary">Expiré</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="form-check form-switch">
                          <input type="checkbox" class="form-check-input toggle-sponsor" data-id="<?= (int)$s['id'] ?>" <?= $s['active'] ? 'checked' : '' ?>>
                        </div>
                      </td>
                      <td class="actions-cell">
                        <button type="button" class="btn btn-sm btn-outline-primary edit-sponsor"
                                data-id="<?= (int)$s['id'] ?>"
                                data-name="<?= htmlspecialchars($s['name']) ?>"
                                data-image="<?= htmlspecialchars($s['image_path']) ?>"
                                data-link="<?= htmlspecialchars($s['link_url'] ?? '') ?>"
                                data-alt="<?= htmlspecialchars($s['alt_text'] ?? '') ?>"
                                data-position="<?= (int)$s['position'] ?>"
                                data-start="<?= htmlspecialchars($s['start_date'] ?? '') ?>"
                                data-end="<?= htmlspecialchars($s['end_date'] ?? '') ?>">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-sponsor" data-id="<?= (int)$s['id'] ?>">
                          <i class="bi bi-trash"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($sponsors)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Aucun sponsor — la card ne s'affiche nulle part.</td></tr>
                  <?php endif; ?>
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
      <div class="modal-dialog modal-fit modal-dialog-scrollable">
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
      <div class="modal-dialog modal-fit modal-dialog-scrollable">
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
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="categoryForm">
                    <div class="modal-header border-0 pb-0">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-modal-header">
                            <div class="auth-icon" aria-hidden="true"><i class="bi bi-tags"></i></div>
                            <h5 class="page-title" id="categoryModalTitle">Nouvelle catégorie</h5>
                            <p class="page-subtitle">La catégorie sert à filtrer et illustrer les événements.</p>
                        </div>
                        <input type="hidden" id="category_id" name="id">

                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="card-title">Informations</h3>
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label for="category_code" class="form-label required-field">Code</label>
                                        <input type="text" class="form-control" id="category_code" name="code" required>
                                        <div class="form-text">Identifiant unique (ex : hiking)</div>
                                    </div>
                                    <div class="col-md-7">
                                        <label for="category_name" class="form-label required-field">Nom</label>
                                        <input type="text" class="form-control" id="category_name" name="name" required>
                                        <div class="form-text">Nom affiché (ex : Randonnée)</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="card-title">Apparence</h3>
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-7">
                                        <label for="category_icon" class="form-label">Icône</label>
                                        <input type="text" class="form-control" id="category_icon" name="icon">
                                        <div class="form-text">Bootstrap Icons (ex : bi-bicycle)</div>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="category_color" class="form-label">Couleur</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="color" class="form-control form-control-color" id="category_color" name="color">
                                            <span class="color-preview flex-shrink-0" id="categoryColorPreview"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal sponsor -->
    <div class="modal fade" id="sponsorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="sponsorForm" enctype="multipart/form-data">
                    <div class="modal-header border-0 pb-0">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-modal-header">
                            <div class="auth-icon" aria-hidden="true"><i class="bi bi-megaphone"></i></div>
                            <h5 class="page-title" id="sponsorModalTitle">Nouveau sponsor</h5>
                            <p class="page-subtitle">Le visuel s'affiche dans la card sponsor des événements et le pied de page de l'accueil.</p>
                        </div>
                        <input type="hidden" id="sponsor_id" name="id">

                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="card-title">Partenaire</h3>
                                <div class="mb-3">
                                    <label for="sponsor_name" class="form-label required-field">Nom</label>
                                    <input type="text" class="form-control" id="sponsor_name" name="name" required maxlength="255">
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label for="sponsor_link_url" class="form-label">Lien du site partenaire</label>
                                        <input type="url" class="form-control" id="sponsor_link_url" name="link_url" placeholder="https://…">
                                    </div>
                                    <div class="col-md-5">
                                        <label for="sponsor_alt_text" class="form-label">Texte alternatif</label>
                                        <input type="text" class="form-control" id="sponsor_alt_text" name="alt_text" maxlength="255">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="card-title">Visuel</h3>
                                <p class="form-intro">Fichier image (max 2 Mo) ou URL externe.</p>
                                <div class="row g-3 align-items-start">
                                    <div class="col-auto">
                                        <div class="sponsor-image-box" id="sponsorImageBox">
                                            <img id="sponsor_current_image" class="sponsor-image-preview" alt="Aperçu du visuel">
                                            <label class="image-upload-button">
                                                <i class="bi bi-upload"></i>
                                                <span>Choisir l'image</span>
                                                <input type="file" id="sponsor_image" name="image" accept="image/jpeg,image/png,image/webp,image/gif" class="d-none">
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <label for="sponsor_image_url" class="form-label">ou URL de l'image</label>
                                        <input type="url" class="form-control" id="sponsor_image_url" name="image_url" placeholder="https://…">
                                        <div class="form-text">En édition : laisser vide pour conserver l'image actuelle.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="card-title">Diffusion</h3>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="sponsor_position" class="form-label">Ordre</label>
                                        <input type="number" class="form-control" id="sponsor_position" name="position" value="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sponsor_start_date" class="form-label">Début d'affichage</label>
                                        <input type="date" class="form-control" id="sponsor_start_date" name="start_date">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sponsor_end_date" class="form-label">Fin d'affichage</label>
                                        <input type="date" class="form-control" id="sponsor_end_date" name="end_date">
                                    </div>
                                    <div class="col-12"><small class="text-muted">Dates vides = toujours visible.</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script src="/assets/js/admin/categories.js"></script>
    <script src="/assets/js/admin/sponsors.js"></script>
    <script src="/assets/js/admin/admin-dashboard.js"></script>
    <script src="/assets/js/admin/dashboard-stats.js"></script>
    <script src="/assets/js/admin/dashboard-kpis.js"></script>
    <script src="/assets/js/admin/notify-organizer.js"></script>
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
