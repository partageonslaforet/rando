<?php
/**
 * localisation: includes/ip_ban.php
 * Role: Banissement automatique des IP qui scannent des pages inexistantes (404 répétés).
 * Usage: enforceIpBan($db) en tout début de traitement (après connexion DB),
 *        recordNotFound($db, $uri) sur chaque page introuvable.
 * Dépendances: PDO, logs/error.log.php (logError)
 * Note: IP_BAN_WHITELIST est codée en dur — ces IP ne sont jamais comptées ni bannies.
 */

const IP_BAN_WHITELIST = [
    '85.201.89.175', // Administrateur — ne jamais bannir
    '127.0.0.1',
    '::1',
];

const IP_BAN_404_THRESHOLD = 5;     // Nombre de 404 tolérés avant ban
const IP_BAN_404_WINDOW = 600;      // Fenêtre glissante en secondes (10 min)
const IP_BAN_LOG_RETENTION = 86400; // Conservation du log 404 en secondes (24h)

/**
 * IP cliente. REMOTE_ADDR uniquement : X-Forwarded-For est forgeable
 * côté client et ne doit jamais servir à un contrôle de sécurité.
 */
function clientIp(): string
{
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function isWhitelistedIp(string $ip): bool
{
    return in_array($ip, IP_BAN_WHITELIST, true);
}

/**
 * Chemins standards demandés automatiquement par les navigateurs et crawlers
 * (favicon, robots, icônes iOS, .well-known) : 404 légitime, non comptabilisé.
 */
function isBenign404Path(string $uri): bool
{
    return (bool) preg_match(
        '#^/(favicon\.ico|robots\.txt|sitemap\.xml|apple-touch-icon[^/]*\.png|\.well-known/)#i',
        $uri
    );
}

/**
 * Bloque la requête (403) si l'IP est bannie.
 * Fail-open : si la table n'existe pas encore (migration non appliquée),
 * on log et on laisse passer — le site ne casse pas.
 */
function enforceIpBan(PDO $db): void
{
    $ip = clientIp();
    if ($ip === '' || isWhitelistedIp($ip)) {
        return;
    }
    try {
        $stmt = $db->prepare('SELECT 1 FROM banned_ips WHERE ip_address = :ip LIMIT 1');
        $stmt->execute(['ip' => $ip]);
        if ($stmt->fetchColumn()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('403 - Accès interdit');
        }
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('includes/ip_ban.php', 'enforceIpBan error', ['error' => $e->getMessage()]);
        }
    }
}

/**
 * Comptabilise une requête vers une page inexistante.
 * Au-delà de IP_BAN_404_THRESHOLD hits dans IP_BAN_404_WINDOW secondes,
 * l'IP est insérée dans banned_ips et la requête courante est bloquée (403).
 */
function recordNotFound(PDO $db, string $uri): void
{
    $ip = clientIp();
    if ($ip === '' || isWhitelistedIp($ip)) {
        return;
    }
    try {
        $ins = $db->prepare(
            'INSERT INTO ip_404_log (ip_address, uri, user_agent, hit_at)
             VALUES (:ip, :uri, :ua, NOW())'
        );
        $ins->execute([
            'ip' => $ip,
            'uri' => substr($uri, 0, 500),
            'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);

        // Purge occasionnelle des vieux hits (1 appel sur 20) pour limiter la taille de la table
        if (random_int(1, 20) === 1) {
            $db->exec('DELETE FROM ip_404_log WHERE hit_at < NOW() - INTERVAL ' . IP_BAN_LOG_RETENTION . ' SECOND');
        }

        $cutoff = date('Y-m-d H:i:s', time() - IP_BAN_404_WINDOW);
        $cnt = $db->prepare(
            'SELECT COUNT(*) FROM ip_404_log WHERE ip_address = :ip AND hit_at >= :cutoff'
        );
        $cnt->execute(['ip' => $ip, 'cutoff' => $cutoff]);
        $hits = (int) $cnt->fetchColumn();

        if ($hits >= IP_BAN_404_THRESHOLD) {
            $reason = 'Auto-ban : ' . $hits . ' requêtes 404 en ' . (int) (IP_BAN_404_WINDOW / 60) . ' min';
            $ban = $db->prepare(
                'INSERT INTO banned_ips (ip_address, reason, hits, banned_at, last_hit_at)
                 VALUES (:ip, :reason, :hits, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE hits = :hits2, last_hit_at = NOW()'
            );
            $ban->execute(['ip' => $ip, 'reason' => $reason, 'hits' => $hits, 'hits2' => $hits]);

            if (function_exists('logError')) {
                logError('includes/ip_ban.php', 'IP bannie automatiquement', [
                    'ip' => $ip,
                    'hits' => $hits,
                    'last_uri' => substr($uri, 0, 200),
                ]);
            }
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('403 - Accès interdit');
        }
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('includes/ip_ban.php', 'recordNotFound error', [
                'error' => $e->getMessage(),
                'uri' => substr($uri, 0, 200),
            ]);
        }
    }
}
