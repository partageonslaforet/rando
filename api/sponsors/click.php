<?php
/**
 * localisation: api/sponsors/click.php
 * Role: Comptabilise un clic sponsor (sponsors.click_count + ligne sponsor_clicks)
 *       puis redirige en 302 vers le lien partenaire.
 * Usage: /api/sponsors/click.php?id=<sponsor_id>&ctx=footer|event|home
 * Note: GET public sans CSRF (navigation sortante) — la cible est toujours
 *       relue en DB et revalidée http(s), jamais prise depuis la requête.
 */
require_once __DIR__ . '/../../includes/init.php';

$redirect  = '/';
$sponsorId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$context   = in_array($_GET['ctx'] ?? '', ['footer', 'event', 'home'], true) ? $_GET['ctx'] : 'unknown';
// Événement d'origine du clic (uniquement pertinent pour ctx=event)
$eventId   = $context === 'event' && (int) ($_GET['eid'] ?? 0) > 0 ? (int) $_GET['eid'] : null;

try {
    $pdo  = getConnection();
    $stmt = $pdo->prepare('SELECT link_url FROM sponsors WHERE id = ? AND active = 1');
    $stmt->execute([$sponsorId]);
    $target = $stmt->fetchColumn();

    // Revalidation anti open-redirect : seules les URL http(s) en DB passent.
    if (is_string($target) && preg_match('#^https?://#i', $target)) {
        $redirect = $target;

        // Même politique que trackVisit : les crawlers connus ne polluent pas les stats.
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isCrawler = $userAgent !== ''
            && preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $userAgent);

        if (!$isCrawler) {
            // Même détection humain/outil que trackVisit
            $isHuman = 1;
            if ($userAgent === '') {
                $isHuman = 0;
            } elseif (preg_match('/Postman|curl|wget|python-requests|python-urllib|axios|node-fetch|got\(|httpie|insomnia|java\/|Apache-HttpClient|Go-http-client|okhttp|libwww-perl|Guzzle|GuzzleHttp/i', $userAgent)) {
                $isHuman = 0;
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ipHash = $ip !== null ? hash('sha256', (string) $ip) : null;

            // Compteur d'abord : même si le détail échoue, le clic est compté.
            try {
                $pdo->prepare('UPDATE sponsors SET click_count = click_count + 1 WHERE id = ?')
                    ->execute([$sponsorId]);
                $pdo->prepare(
                    'INSERT INTO sponsor_clicks (sponsor_id, context, event_id, ip_hash, ip_address, session_id, user_agent, referrer, is_human)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $sponsorId,
                    $context,
                    $eventId,
                    $ipHash,
                    $ip !== null ? substr((string) $ip, 0, 45) : null,
                    session_id() ?: null,
                    substr($userAgent, 0, 255),
                    isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 255) : null,
                    $isHuman,
                ]);
            } catch (Throwable $e) {
                if (function_exists('logError')) {
                    logError('api/sponsors/click.php', 'Sponsor click insert failed', [
                        'sponsor_id' => $sponsorId, 'context' => $context, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError('api/sponsors/click.php', 'Sponsor click lookup failed', [
            'sponsor_id' => $sponsorId, 'error' => $e->getMessage(),
        ]);
    }
}

header('Location: ' . $redirect, true, 302);
exit;
