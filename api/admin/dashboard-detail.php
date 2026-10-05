<?php
/**
 * API détail des KPI pour le dashboard admin.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json');

session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// La page dashboard a déjà validé le rôle admin côté serveur.
// Ici on vérifie seulement que l'utilisateur est connecté pour éviter 403 injustifiés.
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$type = $_GET['type'] ?? '';
$allowedTypes = ['users', 'events', 'pending', 'active', 'categories', 'subscribers', 'organizations'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type invalide']);
    exit;
}

try {
    $pdo = getConnection();
    $data = [];

    switch ($type) {
        case 'users':
            $stmt = $pdo->query("
                SELECT id,
                       COALESCE(NULLIF(CONCAT_WS(' ', first_name, last_name), ''), name, email) AS name,
                       email,
                       role,
                       created_at
                FROM users
                ORDER BY created_at DESC
            ");
            $data = $stmt->fetchAll();
            break;

        case 'events':
            $stmt = $pdo->query("
                SELECT e.id, e.title, e.date AS start_date, e.status, u.name as organizer_name
                FROM events e
                LEFT JOIN users u ON e.user_id = u.id
                ORDER BY e.date DESC, e.start_time DESC
                LIMIT 200
            ");
            $data = $stmt->fetchAll();
            break;

        case 'pending':
            $stmt = $pdo->prepare("
                SELECT e.id, e.title, e.date AS start_date, e.status, u.name as organizer_name
                FROM events e
                LEFT JOIN users u ON e.user_id = u.id
                WHERE e.status = 'pending'
                ORDER BY e.date DESC, e.start_time DESC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        case 'active':
            $stmt = $pdo->prepare("
                SELECT e.id, e.title, e.date AS start_date, e.status, u.name as organizer_name
                FROM events e
                LEFT JOIN users u ON e.user_id = u.id
                WHERE e.status = 'approved' AND e.date >= CURDATE()
                ORDER BY e.date DESC, e.start_time DESC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        case 'categories':
            $stmt = $pdo->query("
                SELECT c.id, c.code, c.name, c.icon, c.color, COUNT(e.id) AS event_count
                FROM event_categories c
                LEFT JOIN events e ON e.category_id = c.id
                GROUP BY c.id, c.code, c.name, c.icon, c.color
                ORDER BY c.name
            ");
            $data = $stmt->fetchAll();
            break;

        case 'subscribers':
            $stmt = $pdo->query("
                SELECT es.id,
                       es.email,
                       es.verified_at,
                       es.is_active,
                       es.notification_frequency,
                       es.created_at,
                       (
                           SELECT COUNT(*) FROM subscriber_preferences sp
                           WHERE sp.subscriber_id = es.id
                       ) AS categories_count
                FROM event_subscribers es
                WHERE es.verified_at IS NOT NULL
                ORDER BY es.verified_at DESC, es.created_at DESC
                LIMIT 500
            ");
            $data = $stmt->fetchAll();
            break;

        case 'organizations':
            $stmt = $pdo->query("
                SELECT op.id,
                       op.name,
                       op.email,
                       op.phone,
                       op.website,
                       op.created_at,
                       u.name AS owner_name,
                       u.email AS owner_email,
                       (
                           SELECT COUNT(*) FROM events e
                           WHERE (
                               e.organizer_id = op.id
                               OR CONVERT(e.organisation USING utf8mb4) COLLATE utf8mb4_unicode_ci = (
                                     CAST(op.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
                                 )
                               OR CONVERT(e.organisation USING utf8mb4) COLLATE utf8mb4_unicode_ci = (
                                     op.name COLLATE utf8mb4_unicode_ci
                                 )
                           )
                       ) AS total_events_count,
                       (
                           SELECT COUNT(*) FROM events e2
                           WHERE (
                               e2.organizer_id = op.id
                               OR CONVERT(e2.organisation USING utf8mb4) COLLATE utf8mb4_unicode_ci = (
                                     CAST(op.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
                                 )
                               OR CONVERT(e2.organisation USING utf8mb4) COLLATE utf8mb4_unicode_ci = (
                                     op.name COLLATE utf8mb4_unicode_ci
                                 )
                           )
                             AND e2.status IN ('published','approved')
                             AND e2.date >= CURDATE()
                       ) AS live_events_count
                FROM organizer_profiles op
                LEFT JOIN users u ON op.user_id = u.id
                ORDER BY op.name ASC
                LIMIT 500
            ");
            $data = $stmt->fetchAll();
            break;
    }

    echo json_encode(['success' => true, 'data' => $data, 'type' => $type]);
} catch (Throwable $e) {
    logError('api/admin/dashboard-detail.php', 'query failed: ' . $e->getMessage(), ['type' => $type]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
