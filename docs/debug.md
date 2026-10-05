---
title: Mode Debug
---

# Objectif
Activer des traces de debug côté serveur (PHP) et côté navigateur (JS) sans laisser de logs permanents. Le flag est désactivé par défaut et s’active via une variable d’environnement.

Fichiers concernés:
- includes/config.php
- logs/error.log.php
- templates/components/header/header.php
- public/assets/js/core/subscribers-manage.js

Fonctions:
- logError(context, message, extra) → logs d’erreurs (toujours actifs)
- debugLog(context, message, extra) → logs de debug (actifs uniquement quand DEBUG_SUBSCRIBERS = true)
- window.DEBUG_SUBSCRIBERS → flag JS pour afficher des console.debug conditionnels

---

## 1) Debug local

### Activation
- Dans votre terminal (avant de lancer PHP ou votre serveur local):
```bash
export DEBUG_SUBSCRIBERS=1
```
- Recharger la page.

### Ce qui s’active
- PHP: les appels `debugLog('subscribers/...', 'message', {...})` écrivent dans logs/error.log.
- JS: les appels `if (window.DEBUG_SUBSCRIBERS) console.debug('...', data)` s’affichent dans la console.

### Vérifier
- Navigateur: ouvrir la console et vérifier que `window.DEBUG_SUBSCRIBERS === true`.
- PHP: ouvrir logs/error.log et vérifier la présence de lignes correspondant aux contextes autorisés:
  - Prefix `subscribers/` ou `api/subscribers/`
  - Contexte `Subscribers.php`

### Désactivation
- Dans le terminal:
```bash
unset DEBUG_SUBSCRIBERS
# ou
export DEBUG_SUBSCRIBERS=0
```
- Recharger la page.

---

## 2) Debug prod

### Pré-requis
- Le code suivant doit être déployé (il est neutre par défaut):
  - includes/config.php: définition du flag via ENV
  - logs/error.log.php: ajout de `debugLog(...)`
  - templates/components/header/header.php: exposition `window.DEBUG_SUBSCRIBERS`
  - public/assets/js/core/subscribers-manage.js: console.debug conditionnels

Extraits (déjà en place dans le code):

- includes/config.php
```php
if (!defined('DEBUG_SUBSCRIBERS')) {
    $envDebug = getenv('DEBUG_SUBSCRIBERS');
    $debug = false;
    if ($envDebug !== false) {
        $debug = filter_var($envDebug, FILTER_VALIDATE_BOOL) || $envDebug === '1';
    }
    define('DEBUG_SUBSCRIBERS', $debug);
}
```

- logs/error.log.php
```php
function debugLog(string $context, string $message, array $extra = []): void {
    if (!defined('DEBUG_SUBSCRIBERS') || !DEBUG_SUBSCRIBERS) return;
    $isSubscribersScope = (
        strpos($context, 'subscribers/') === 0 ||
        strpos($context, 'api/subscribers/') === 0 ||
        $context === 'Subscribers.php'
    );
    if ($isSubscribersScope) {
        logError($context, $message, $extra);
    }
}
```

- templates/components/header/header.php
```php
<script>
  window.DEBUG_SUBSCRIBERS = <?= (defined('DEBUG_SUBSCRIBERS') && DEBUG_SUBSCRIBERS) ? 'true' : 'false' ?>;
</script>
```

- public/assets/js/core/subscribers-manage.js (exemple d’usage)
```js
if (window.DEBUG_SUBSCRIBERS) {
  console.debug('[subscribers] manage-data response', data);
}
```

### Activation en prod
- Activer via variable d’environnement sur le service PHP (sans commit):
  - PHP-FPM/Apache/Nginx: ajouter `DEBUG_SUBSCRIBERS=1` dans l’environnement du process/service.
  - Redémarrer le service si nécessaire.
- Purge cache CDN si le JS a été mis à jour récemment (ou ajouter un query param de version).

### Vérifier
- Front: ouvrir la console du navigateur, `window.DEBUG_SUBSCRIBERS` doit être `true`, les `console.debug` balisés apparaissent.
- Serveur: les entrées `debugLog(...)` doivent apparaître dans logs/error.log (permissions d’écriture correctes).

### Désactivation
- Retirer la variable d’environnement ou la mettre à `0`, redémarrer si nécessaire:
```bash
# Exemple systemd, variable dans l’environnement du service
DEBUG_SUBSCRIBERS=0
```
- Recharger la page.

---

## Bonnes pratiques
- Ne pas logguer de secrets côté front. Côté serveur, `logError` masque déjà des champs sensibles (password, token, secret, api_key, smtp_password).
- Utiliser `debugLog(...)` pour les informations de diagnostic non critiques. Garder `logError(...)` pour les erreurs réelles.
- Utiliser l’ENV en prod/staging pour activer/désactiver rapidement sans commit.
