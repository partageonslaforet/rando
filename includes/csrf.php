<?php
/**
 * Gestion simplifiée des tokens CSRF.
 */

require_once __DIR__ . '/functions.php';

function csrfToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfHeader(): string {
    return csrfToken();
}

function verifyCsrf(?string $token): bool {
    initSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
