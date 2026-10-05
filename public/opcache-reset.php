<?php
/**
 * Page temporaire pour vider le cache opcode PHP.
 * À supprimer après utilisation.
 */
if (!isset($_GET['go'])) {
    http_response_code(400);
    echo 'Ajoutez ?go=1 à l\'URL pour vider le cache.';
    exit;
}

if (!function_exists('opcache_invalidate')) {
    echo 'OPcache n\'est pas activé sur ce serveur.';
    exit;
}

$files = [
    __DIR__ . '/../index.php',
    __DIR__ . '/../events/event-detail.php',
    __DIR__ . '/../templates/events/event-detail.php',
    __DIR__ . '/../templates/events/event-display.php',
    __DIR__ . '/../templates/components/events/events-list.php',
    __DIR__ . '/../templates/modals/create-event.php',
    __DIR__ . '/../templates/components/header/header.php',
    __DIR__ . '/../templates/components/footer/footer.php',
    __DIR__ . '/../templates/seo/sitemap.php',
    __DIR__ . '/../pages/user/my-events.php',
    __DIR__ . '/../api/events/participants.php',
    __DIR__ . '/../api/events/events-counts.php',
    __DIR__ . '/../api/events/save_draft.php',
    __DIR__ . '/../api/events/publish.php',
    __DIR__ . '/../api/admin/stats-detail.php',
    __DIR__ . '/../api/admin/ip-detail.php',
    __DIR__ . '/../src/Models/Event.php',
    __DIR__ . '/../includes/mailer.php',
];

clearstatcache(true);

$results = [];
foreach ($files as $file) {
    $real = realpath($file);
    if (!$real) {
        $results[] = ['file' => $file, 'status' => 'introuvable'];
        continue;
    }

    $invalidated = @opcache_invalidate($real, true);
    $compiled = false;
    if ($invalidated && function_exists('opcache_compile_file') && is_file($real)) {
        try {
            $compiled = @opcache_compile_file($real);
        } catch (Throwable $e) {
            $compiled = false;
        }
    }

    $results[] = [
        'file' => $real,
        'invalidated' => $invalidated ? 'ok' : 'ko',
        'compiled' => $compiled ? 'ok' : 'ko'
    ];
}

if (function_exists('opcache_reset')) {
    @opcache_reset();
}

header('Content-Type: text/plain; charset=utf-8');
echo "OPcache invalidation\n";
echo "====================\n";
foreach ($results as $r) {
    echo $r['file'] . " - " . ($r['status'] ?? "invalidate: {$r['invalidated']}, compile: {$r['compiled']}") . "\n";
}
echo "\nReset global effectué.\n";
echo "Rechargez la page fiche. Si les meta/OG n\'apparaissent toujours pas, redémarrez PHP-FPM.\n";
