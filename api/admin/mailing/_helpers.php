<?php
/**
 * Helpers partagés des endpoints mailing (admin).
 * Réponse JSON + garde admin + parsing du CSV calendrier clubs.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Envoie une réponse JSON et termine la requête (avec log centralisé). */
function sendJsonResponse(bool $success, string $message, ?array $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if (function_exists('logError')) {
        logError(basename($_SERVER['SCRIPT_NAME'] ?? __FILE__), $message, ['success' => $success, 'payload' => $data]);
    }
    echo json_encode($response);
    exit;
}

/** Vérifie que l'utilisateur connecté est admin, sinon réponse 403 JSON. */
function requireAdminApi() {
    $role = $_SESSION['user_role'] ?? ($_SESSION['user']['role'] ?? null);
    if ($role !== 'admin') {
        if (function_exists('logError')) {
            logError(basename($_SERVER['SCRIPT_NAME'] ?? __FILE__), "Tentative d'accès non autorisé", ['role' => $role]);
        }
        sendJsonResponse(false, 'Accès non autorisé');
    }
}

/**
 * Extrait les adresses email valides d'une cellule brute.
 * Gère : cellule multi-emails séparés par espaces, junk autour de l'email,
 * trailing "." / ";" et normalise en minuscules (dédoublonnage).
 */
function extractMailingEmails(string $raw): array {
    $emails = [];
    foreach (preg_split('/\s+/u', $raw) as $tok) {
        $tok = trim($tok, " \t\n\r\0\x0B.;");
        if ($tok === '' || stripos($tok, '@') === false) {
            continue;
        }
        $tok = strtolower($tok);
        if (filter_var($tok, FILTER_VALIDATE_EMAIL)) {
            $emails[$tok] = $tok;
        }
    }
    return array_values($emails);
}

/**
 * Parse le CSV calendrier (séparateur ';', colonnes fixes :
 * 0 Statut | 1 Date | 2 Lieu | 3 Type | 4 Club | 5 Responsable | 6 Téléphone | 7 Email).
 * Retourne rows (affichage preview, avec flag) + contacts (emails uniques prêts à insérer)
 * + stats. Les lignes parasites (ni club ni email) sont ignorées.
 * Un club à plusieurs emails → une entrée par email.
 */
function parseMailingCsv(string $path): array {
    $rows = [];
    $contacts = [];
    $stats = ['total' => 0, 'no_email' => 0, 'invalid' => 0, 'emails' => 0, 'file_dupes' => 0];

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return compact('rows', 'contacts', 'stats');
    }
    // Le fichier est normalement UTF-8 ; fallback Windows-1252 si besoin.
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    $seen = [];
    $lineNo = 0;
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $lineNo++;
        if (trim($line) === '') {
            continue;
        }
        if ($lineNo === 1 && stripos($line, 'Club') !== false) {
            continue; // ligne d'en-tête
        }
        $f        = str_getcsv($line, ';');
        $club     = trim($f[4] ?? '');
        $rawEmail = trim($f[7] ?? '');
        if ($club === '' && $rawEmail === '') {
            continue; // ligne parasite (continuation de note, vide…)
        }
        $stats['total']++;

        $base = [
            'club'    => mb_substr($club, 0, 160),
            'contact' => mb_substr(trim($f[5] ?? ''), 0, 160),
            'phone'   => mb_substr(trim($f[6] ?? ''), 0, 60),
            'lieu'    => mb_substr(trim($f[2] ?? ''), 0, 160),
            'type'    => mb_substr(trim($f[3] ?? ''), 0, 80),
        ];
        $emails = extractMailingEmails($rawEmail);

        if ($rawEmail === '' || stripos($rawEmail, '@') === false) {
            $stats['no_email']++;
            $rows[] = $base + ['line' => $lineNo, 'email' => '', 'flag' => 'no_email'];
            continue;
        }
        if (empty($emails)) {
            $stats['invalid']++;
            $rows[] = $base + ['line' => $lineNo, 'email' => $rawEmail, 'flag' => 'invalid'];
            continue;
        }

        foreach ($emails as $email) {
            $flag = 'ok';
            if (isset($seen[$email])) {
                $flag = 'duplicate';
                $stats['file_dupes']++;
            }
            $seen[$email] = true;
            $contacts[$email] = $base + ['email' => $email]; // dernière occurrence gagne
            $rows[] = $base + ['line' => $lineNo, 'email' => $email, 'flag' => $flag];
        }
    }

    $stats['emails'] = count($contacts);
    return ['rows' => $rows, 'contacts' => array_values($contacts), 'stats' => $stats];
}

/** Remplace les placeholders {club} {contact} {lieu} {type} dans un texte. */
function mailingPersonalize(string $text, array $contact): string {
    return strtr($text, [
        '{club}'    => $contact['club'] ?? '',
        '{contact}' => $contact['contact_name'] ?? '',
        '{lieu}'    => $contact['lieu'] ?? '',
        '{type}'    => $contact['type'] ?? '',
    ]);
}

/** Historise un envoi dans mailing_logs (silencieux si la table manque). */
function mailingLogSend(?int $contactId, string $email, string $subject, bool $ok, ?string $error = null, bool $isTest = false, ?int $campaignId = null): void {
    try {
        $db = getConnection();
        $db->prepare('INSERT INTO mailing_logs (campaign_id, contact_id, email, subject, status, error, is_test) VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([$campaignId, $contactId, $email, mb_substr($subject, 0, 255), $ok ? 'ok' : 'error', $error, $isTest ? 1 : 0]);
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('mailing/_helpers.php', 'Échec écriture mailing_logs', ['error' => $e->getMessage()]);
        }
    }
}

/**
 * Ajoute les paramètres UTM à toutes les URLs du domaine présentes dans $content
 * (attributs href et URLs en texte brut). Idempotent : les URLs déjà marquées utm_* sont ignorées.
 */
function mailingUtmLinks(string $content, string $campaign): string {
    $appHost = defined('APP_URL') ? (parse_url(APP_URL, PHP_URL_HOST) ?: '') : '';
    // Couvre toujours le domaine canonique (les mails le citent même en local) + le host APP_URL s'il diffère.
    $hosts = ['partageonslaforet.be'];
    $bare = preg_replace('/^www\./', '', $appHost);
    if ($bare !== '' && !in_array($bare, $hosts, true)) {
        $hosts[] = $bare;
    }
    $hostsRx = '(?:[a-z0-9-]+\.)*(?:' . implode('|', array_map(fn($h) => preg_quote($h, '/'), $hosts)) . ')';

    // (?<!>)        → ignore l'URL visible d'un lien <a href="..">URL</a> (sinon les utm_* s'afficheraient)
    // (?<!src="...)  → ignore les images du gabarit (logo)
    return preg_replace_callback(
        '#(?<!>)(?<!src=")(?<!src=\')https?://' . $hostsRx . '[^\s<>"\']*#i',
        function (array $m) use ($campaign) {
            $url = $m[0];
            // Ponctuation terminale collée à une URL en texte brut (.,;:!?) — ne fait pas partie du lien
            $trail = '';
            while (preg_match('/[.,;:!?\)\]]$/', $url)) {
                $trail = substr($url, -1) . $trail;
                $url   = substr($url, 0, -1);
            }
            if (strpos($url, 'utm_') !== false) {
                return $m[0]; // déjà tracée
            }
            if (strpos($url, '?') === false) {
                $sep = '?';
            } else {
                $sep = strpos($url, '&amp;') !== false ? '&amp;' : '&';
            }
            return $url . $sep
                . 'utm_source=mailing&utm_medium=email&utm_campaign=' . rawurlencode($campaign)
                . $trail;
        },
        $content
    ) ?? $content;
}

/** Rend le corps de l'email dans le layout charté du site (templates/emails/). */
function mailingBuildHtml(string $subject, string $persBody): string {
    $h = htmlspecialchars($persBody);
    // URLs complètes du domaine → liens cliquables (conserve chemin + query/UTM)
    $h = preg_replace(
        '#https?://[a-z0-9.-]*partageonslaforet\.be[^\s<]*#i',
        '<a href="$0">$0</a>',
        $h
    );
    // Sous-domaine rando nu (sans protocole, hors des <a> déjà produits) → page d'accueil du site
    $h = preg_replace(
        '/(?<!["\/.])\brando\.partageonslaforet\.be\b/i',
        '<a href="https://rando.partageonslaforet.be">rando.partageonslaforet.be</a>',
        $h
    );
    // Domaine parent nu → site Partageons la Forêt
    $h = preg_replace(
        '/(?<!["\/.a-z0-9-])(?:www\.)?partageonslaforet\.be\b/i',
        '<a href="https://partageonslaforet.be/websim.php">$0</a>',
        $h
    );
    $vars = [
        'headerTitle' => $subject,
        'bodyHtml'    => nl2br($h),
    ];
    ob_start();
    extract($vars, EXTR_SKIP);
    include __DIR__ . '/../../../templates/emails/mailing_campaign.php';
    return (string) ob_get_clean();
}
