<?php
/**
 * API détail des statistiques de fréquentation pour le dashboard admin.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json');

session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$type = $_GET['type'] ?? '';
$allowedTypes = ['visits', 'pages', 'recent', 'countries', 'sources'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type invalide']);
    exit;
}

$period = (int) ($_GET['period'] ?? 7);
if (!in_array($period, [1, 7, 14, 30, 90], true)) {
    $period = 7;
}

$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = '';
}

// Filtre pays (pour type=recent) : chaîne vide = pays inconnu (NULL en base)
$country = array_key_exists('country', $_GET) ? (string) $_GET['country'] : null;

// Filtre visites référées (pour type=recent) : referrer externe non vide
$referredFilter = ($_GET['referred'] ?? '') === '1'
    ? " AND s.referrer IS NOT NULL AND s.referrer <> '' AND s.referrer NOT LIKE '%rando.partageonslaforet%' "
    : '';

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(1000, (int) ($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$total = 0;
$totalPages = 0;

try {
    $pdo = getConnection();

    $hasIsHuman = false;
    try {
        $hasIsHuman = (bool) $pdo->query("SHOW COLUMNS FROM site_visits LIKE 'is_human'")->fetchColumn();
    } catch (Throwable $e) {
        $hasIsHuman = false;
    }

    $days = $period - 1;
    $dateFilter = $date ? " AND DATE(visited_at) = :date " : '';
    $urlFilter = " AND url NOT REGEXP '\\\\.(png|jpg|jpeg|gif|svg|ico|css|js|txt|xml|json|woff|woff2|ttf|eot|map|pdf|webp|bmp|mp4|mp3|webm|ogg|avif|zip|gz|tar)$' ";

    if ($type === 'visits') {
        $countSql = "
            SELECT COUNT(DISTINCT DATE(visited_at))
            FROM site_visits
            WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            $urlFilter
            $dateFilter
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindValue(':days', $days, PDO::PARAM_INT);
        if ($date) $countStmt->bindValue(':date', $date);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($total / $limit);

        $sql = "
            SELECT DATE(visited_at) AS day,
                   COUNT(*) AS visits,
                   COUNT(DISTINCT session_id) AS sessions,
                   COUNT(DISTINCT ip_address) AS unique_ips
        ";
        if ($hasIsHuman) {
            $sql .= ", SUM(CASE WHEN is_human = 0 THEN 1 ELSE 0 END) AS non_humans";
        } else {
            $sql .= ", 0 AS non_humans";
        }
        $sql .= "
            FROM site_visits
            WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            $urlFilter
            $dateFilter
            GROUP BY DATE(visited_at)
            ORDER BY day DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($date) $stmt->bindValue(':date', $date);
        $stmt->execute();
        $data = $stmt->fetchAll();
    } elseif ($type === 'pages') {
        $countSql = "
            SELECT COUNT(DISTINCT url)
            FROM site_visits
            WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            $urlFilter
            $dateFilter
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindValue(':days', $days, PDO::PARAM_INT);
        if ($date) $countStmt->bindValue(':date', $date);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($total / $limit);

        $sql = "
            SELECT url, COUNT(*) AS visits
            FROM site_visits
            WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            $urlFilter
            $dateFilter
            GROUP BY url
            ORDER BY visits DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($date) $stmt->bindValue(':date', $date);
        $stmt->execute();
        $data = $stmt->fetchAll();

        // Enrichir avec les noms d'événements (support /event?id=XXX et /event/XXX)
        $eventIds = [];
        foreach ($data as $row) {
            $url = $row['url'] ?? '';
            if (preg_match('/^\/event\?id=(\d+)$/', $url, $m) || preg_match('/^\/event\/(\d+)$/', $url, $m)) {
                $eventIds[] = (int) $m[1];
            }
        }
        $eventTitles = [];
        if (!empty($eventIds)) {
            $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
            $titleStmt = $pdo->prepare("SELECT id, title FROM events WHERE id IN ($placeholders)");
            $titleStmt->execute($eventIds);
            $eventTitles = $titleStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        // Construire un libellé normalisé par ligne
        $normalized = [];
        foreach ($data as $row) {
            $url = $row['url'] ?? '';
            $label = $url;
            if (preg_match('/^\/event\?id=(\d+)$/', $url, $m) || preg_match('/^\/event\/(\d+)$/', $url, $m)) {
                $title = $eventTitles[(int) $m[1]] ?? '';
                $label = $title ? ('Événement : ' . $title) : $url;
            } elseif ($url === '/') {
                $label = 'Accueil';
            } elseif ($url === '/?create=1') {
                $label = 'Accueil (création)';
            }
            // Agréger par label
            if (!isset($normalized[$label])) {
                $normalized[$label] = ['label' => $label, 'visits' => (int)($row['visits'] ?? 0)];
            } else {
                $normalized[$label]['visits'] += (int)($row['visits'] ?? 0);
            }
        }
        // Transformer en tableau et trier par visites desc
        $data = array_values($normalized);
        usort($data, function($a, $b){ return $b['visits'] <=> $a['visits']; });
        // Mettre à jour la pagination (simple: tout sur une page)
        $total = count($data);
        $totalPages = 1;
        $limit = $total;
        $offset = 0;
    } elseif ($type === 'countries') {
        $countSql = "
            SELECT COUNT(DISTINCT g.country)
            FROM site_visits s
            INNER JOIN ip_geo_cache g ON s.ip_address = g.ip_address
            WHERE s.visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            $urlFilter
            $dateFilter
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindValue(':days', $days, PDO::PARAM_INT);
        if ($date) $countStmt->bindValue(':date', $date);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($total / $limit);

        $sql = "
            SELECT g.country,
                   COUNT(*) AS visits
            FROM site_visits s
            INNER JOIN ip_geo_cache g ON s.ip_address = g.ip_address
            WHERE s.visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            $urlFilter
            $dateFilter
            GROUP BY g.country
            ORDER BY visits DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($date) $stmt->bindValue(':date', $date);
        $stmt->execute();
        $data = $stmt->fetchAll();
    } elseif ($type === 'sources') {
        $refCond = " s.referrer IS NOT NULL AND s.referrer <> '' AND s.referrer NOT LIKE '%rando.partageonslaforet%' ";
        $countSql = "
            SELECT COUNT(*) FROM (
                SELECT 1 FROM site_visits s
                WHERE s.visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                $urlFilter
                $dateFilter
                AND $refCond
                GROUP BY DATE(s.visited_at), s.referrer
            ) t
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindValue(':days', $days, PDO::PARAM_INT);
        if ($date) $countStmt->bindValue(':date', $date);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($total / $limit);

        $sql = "
            SELECT DATE(s.visited_at) AS day, s.referrer, COUNT(*) AS visits
            FROM site_visits s
            WHERE s.visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            $urlFilter
            $dateFilter
            AND $refCond
            GROUP BY DATE(s.visited_at), s.referrer
            ORDER BY day DESC, visits DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($date) $stmt->bindValue(':date', $date);
        $stmt->execute();
        $data = $stmt->fetchAll();

        // Référants pointant vers un événement interne : afficher son titre
        $eventIds = [];
        foreach ($data as $row) {
            if (preg_match('#/event(?:\?id=|/)(\d+)#', (string) ($row['referrer'] ?? ''), $m)) {
                $eventIds[] = (int) $m[1];
            }
        }
        $eventTitles = [];
        if (!empty($eventIds)) {
            $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
            $titleStmt = $pdo->prepare("SELECT id, title FROM events WHERE id IN ($placeholders)");
            $titleStmt->execute($eventIds);
            $eventTitles = $titleStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        foreach ($data as &$row) {
            $ref = (string) ($row['referrer'] ?? '');
            $row['referrer_label'] = $ref;
            if (preg_match('#/event(?:\?id=|/)(\d+)#', $ref, $m)) {
                $t = $eventTitles[(int) $m[1]] ?? '';
                if ($t !== '') {
                    $row['referrer_label'] = 'Événement : ' . $t;
                }
            }
        }
        unset($row);
    } else {
        $countryJoin = '';
        $countryFilter = '';
        if ($country !== null) {
            $countryJoin = ' INNER JOIN ip_geo_cache g ON s.ip_address = g.ip_address ';
            $countryFilter = " AND COALESCE(g.country, '') = :country ";
        }
        $countSql = "
            SELECT COUNT(*)
            FROM site_visits s
            $countryJoin
            WHERE s.visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            $urlFilter
            $dateFilter
            $countryFilter
            $referredFilter
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindValue(':days', $days, PDO::PARAM_INT);
        if ($date) $countStmt->bindValue(':date', $date);
        if ($country !== null) $countStmt->bindValue(':country', $country);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($total / $limit);

        $sql = "
            SELECT s.url, s.ip_address, s.user_agent, s.referrer, s.visited_at
        ";
        if ($hasIsHuman) {
            $sql .= ", s.is_human";
        } else {
            $sql .= ", 1 AS is_human";
        }
        $sql .= "
            FROM site_visits s
            $countryJoin
            WHERE s.visited_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            $urlFilter
            $dateFilter
            $countryFilter
            $referredFilter
            ORDER BY s.visited_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($date) $stmt->bindValue(':date', $date);
        if ($country !== null) $stmt->bindValue(':country', $country);
        $stmt->execute();
        $data = $stmt->fetchAll();

        $ipCache = [];
        foreach ($data as &$row) {
            $ip = $row['ip_address'] ?? '';
            if (!isset($ipCache[$ip])) {
                $ipCache[$ip] = resolveIpLocation($ip);
            }
            $row['country'] = $ipCache[$ip]['country'];
            $row['city'] = $ipCache[$ip]['city'];
            $row['is_human'] = (int) ($row['is_human'] ?? 1);
        }
        unset($row);

        // Libellés lisibles : /event?id=XXX → titre de l'événement
        $eventIds = [];
        foreach ($data as $row) {
            $url = $row['url'] ?? '';
            if (preg_match('/^\/event\?id=(\d+)$/', $url, $m) || preg_match('/^\/event\/(\d+)$/', $url, $m)) {
                $eventIds[] = (int) $m[1];
            }
        }
        $eventTitles = [];
        if (!empty($eventIds)) {
            $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
            $titleStmt = $pdo->prepare("SELECT id, title FROM events WHERE id IN ($placeholders)");
            $titleStmt->execute($eventIds);
            $eventTitles = $titleStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        foreach ($data as &$row) {
            $url = $row['url'] ?? '';
            $row['label'] = $url;
            if (preg_match('/^\/event\?id=(\d+)$/', $url, $m) || preg_match('/^\/event\/(\d+)$/', $url, $m)) {
                $title = $eventTitles[(int) $m[1]] ?? '';
                $row['label'] = $title !== '' ? ('Événement : ' . $title) : $url;
            } elseif ($url === '/') {
                $row['label'] = 'Accueil';
            } elseif ($url === '/?create=1') {
                $row['label'] = 'Accueil (création)';
            }
        }
        unset($row);
    }

    $response = ['success' => true, 'data' => $data, 'period' => $period, 'date' => $date];
    $response['pagination'] = [
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages
    ];
    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
