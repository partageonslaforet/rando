<?php
/**
 * Limitation basique du nombre de demandes par action et par session/IP.
 */

require_once __DIR__ . '/functions.php';

function checkRateLimit(string $action, int $maxAttempts = 3, int $windowSeconds = 3600): bool {
    initSession();

    $now = time();
    $key = 'rate_limit_' . $action;

    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first' => $now];
    }

    $entry = &$_SESSION[$key];

    if ($now - $entry['first'] > $windowSeconds) {
        $entry = ['count' => 0, 'first' => $now];
    }

    if ($entry['count'] >= $maxAttempts) {
        return false;
    }

    $entry['count']++;
    return true;
}

function rateLimitMessage(string $action): string {
    return 'Trop de demandes. Veuillez réessayer plus tard.';
}
