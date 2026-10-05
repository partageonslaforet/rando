<?php
/**
 * localisation: templates/seo/sitemap.php
 * Role: Génération dynamique du sitemap XML
 * Usage: Route /sitemap.xml
 * Dépendances: $pdo, APP_URL
 */

if (headers_sent()) {
    return;
}

header('Content-Type: application/xml; charset=utf-8');

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
if (empty($baseUrl)) {
    $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rando.partageonslaforet.be');
}

$urls = [];
$urls[] = [
    'loc' => $baseUrl . '/',
    'lastmod' => date('Y-m-d'),
    'changefreq' => 'weekly',
    'priority' => '1.0'
];
$urls[] = [
    'loc' => $baseUrl . '/events',
    'lastmod' => date('Y-m-d'),
    'changefreq' => 'daily',
    'priority' => '0.9'
];

try {
    $stmt = $pdo->query("SELECT id, COALESCE(updated_at, created_at) AS lastmod, `date`, status FROM events WHERE status = 'approved' ORDER BY date DESC");
    $events = $stmt->fetchAll();

    foreach ($events as $event) {
        $lastmod = !empty($event['lastmod']) ? date('Y-m-d', strtotime($event['lastmod'])) : date('Y-m-d');
        $isFuture = !empty($event['date']) && $event['date'] >= date('Y-m-d');
        $priority = $isFuture ? '0.8' : '0.5';

        $urls[] = [
            'loc' => $baseUrl . '/event/' . (int) $event['id'],
            'lastmod' => $lastmod,
            'changefreq' => 'weekly',
            'priority' => $priority
        ];
    }
} catch (Exception $e) {
    if (function_exists('logError')) {
        logError(__FILE__, 'Sitemap events fetch failed', ['error' => $e->getMessage()]);
    } else {
        error_log('[sitemap] ' . $e->getMessage());
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $u) {
    echo "    <url>\n";
    echo "        <loc>" . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    echo "        <lastmod>" . htmlspecialchars($u['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
    echo "        <changefreq>" . htmlspecialchars($u['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</changefreq>\n";
    echo "        <priority>" . htmlspecialchars($u['priority'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</priority>\n";
    echo "    </url>\n";
}

echo '</urlset>' . "\n";
exit;
