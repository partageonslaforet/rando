<?php
/**
 * Fichier: /api/events/ics.php
 * Rôle: Génère un fichier .ics (iCalendar) pour un événement publié —
 *       "Ajouter à mon calendrier" (bouton de la fiche événement).
 * Usage: GET /api/events/ics.php?id={event_id}
 * Champs émis : UID, DTSTAMP, DTSTART, DTEND, SUMMARY, LOCATION, URL.
 * Dépendances: config/database.php (getConnection, APP_URL), logs/error.log.php.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../logs/error.log.php';

const ICS_TZ = 'Europe/Brussels';

// Échappement iCalendar (RFC 5545 §3.3.11) : \, ; , et sauts de ligne
function ics_escape(?string $text): string
{
    $text = (string)$text;
    $text = str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $text);
    return $text;
}

// Repli des lignes à 75 octets max (RFC 5545 §3.1) — continuation = CRLF + espace
function ics_fold(string $line): string
{
    $out = '';
    while (strlen($line) > 75) {
        $chunk = substr($line, 0, 75);
        // Ne pas couper au milieu d'une séquence UTF-8 multi-octets
        while ($chunk !== '' && (ord($chunk[strlen($chunk) - 1]) & 0xC0) === 0x80) {
            $chunk = substr($chunk, 0, -1);
        }
        $out .= $chunk . "\r\n ";
        $line = substr($line, strlen($chunk));
    }
    return $out . $line;
}

try {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Paramètre id invalide');
    }

    $pdo = getConnection();
    $stmt = $pdo->prepare(
        "SELECT id, title, date, start_time, end_time,
                venue, location, meeting_name, meeting_address, meeting_city
         FROM events WHERE id = :id AND status = 'approved' LIMIT 1"
    );
    $stmt->execute(['id' => $id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Événement introuvable');
    }

    // --- Dates : heures locales Europe/Brussels ; journée entière si pas de start_time
    $tz = new DateTimeZone(ICS_TZ);
    $date = preg_replace('/\D/', '', (string)$event['date']); // YYYYMMDD
    $startTime = trim((string)$event['start_time']);
    $endTime   = trim((string)$event['end_time']);
    $endTime   = ($endTime === '00:00:00') ? '' : $endTime;   // convention métier : 00:00:00 = pas de fin

    if ($startTime !== '') {
        $dtStart = new DateTime($event['date'] . ' ' . $startTime, $tz);
        if ($endTime !== '') {
            $dtEnd = new DateTime($event['date'] . ' ' . $endTime, $tz);
            if ($dtEnd <= $dtStart) { // fin le lendemain (ex. 22:00 → 02:00)
                $dtEnd->modify('+1 day');
            }
        } else {
            $dtEnd = (clone $dtStart)->modify('+2 hours'); // durée par défaut
        }
        $dtStartIcs = 'DTSTART;TZID=' . ICS_TZ . ':' . $dtStart->format('Ymd\THis');
        $dtEndIcs   = 'DTEND;TZID=' . ICS_TZ . ':' . $dtEnd->format('Ymd\THis');
    } else {
        // Journée entière (DTEND exclusif = jour suivant)
        $dtEnd = (new DateTime($event['date'], $tz))->modify('+1 day');
        $dtStartIcs = 'DTSTART;VALUE=DATE:' . $date;
        $dtEndIcs   = 'DTEND;VALUE=DATE:' . $dtEnd->format('Ymd');
    }

    // --- Lieu : point de RDV si renseigné, sinon lieu de l'événement
    $locationParts = array_filter(array_map('trim', [
        $event['meeting_name'], $event['meeting_address'], $event['meeting_city'],
    ]));
    if (!$locationParts) {
        $locationParts = array_filter(array_map('trim', [$event['venue'], $event['location']]));
    }
    $location = implode(', ', $locationParts);

    $url = rtrim(APP_URL, '/') . '/event?id=' . $event['id'];
    $uid = 'event-' . $event['id'] . '@' . (parse_url(APP_URL, PHP_URL_HOST) ?: 'rando.partageonslaforet.be');

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//PartageonsLaForet//Rando Events//FR',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        $dtStartIcs,
        $dtEndIcs,
        'SUMMARY:' . ics_escape($event['title']),
    ];
    if ($location !== '') {
        $lines[] = 'LOCATION:' . ics_escape($location);
    }
    $lines[] = 'URL:' . $url;
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    $ics = implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";

    $filename = 'event-' . $event['id'] . '.ics';
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($ics));
    header('Cache-Control: no-store');
    echo $ics;
} catch (Throwable $e) {
    logError('api/events/ics.php', 'ICS generation failed', [
        'id'    => $id ?? null,
        'error' => $e->getMessage(),
    ]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Erreur lors de la génération du fichier calendrier');
}
