<?php
/**
 * Enregistre une erreur dans le fichier de log sécurisé du projet.
 *
 * @param string $context Fichier ou contexte de l'erreur
 * @param string $message Description de l'erreur
 * @param array $extra Données complémentaires (seront encodées en JSON)
 */
function logError(string $context, string $message, array $extra = []): void {
    $logDir = __DIR__;
    $logFile = $logDir . '/error.log';

    // Nettoyer les secrets éventuels des extra
    $safeExtra = $extra;
    foreach ($safeExtra as $key => $value) {
        if (is_string($value) && in_array(strtolower($key), ['password', 'token', 'secret', 'api_key', 'smtp_password'], true)) {
            $safeExtra[$key] = '[REDACTED]';
        }
    }

    $entry = [
        'time' => date('c'),
        'context' => $context,
        'message' => $message,
        'extra' => $safeExtra,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    error_log($line, 3, $logFile);
}
