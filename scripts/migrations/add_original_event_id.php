<?php
// Migration MySQL: ajoute draft_events.original_event_id (+ index) si absents
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../logs/error.log.php';

    $db = getConnection();

    // Vérifier si la colonne existe déjà
    $stmt = $db->prepare("SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'draft_events'
          AND COLUMN_NAME = 'original_event_id'");
    $stmt->execute();
    $colExists = ((int)$stmt->fetchColumn() > 0);

    $result = [
        'success' => true,
        'column_added' => false,
        'index_created' => false,
        'messages' => []
    ];

    if (!$colExists) {
        $db->exec("ALTER TABLE draft_events ADD COLUMN original_event_id INT NULL AFTER user_id");
        $result['column_added'] = true;
        $result['messages'][] = 'Colonne original_event_id ajoutée.';
    } else {
        $result['messages'][] = 'Colonne original_event_id déjà présente.';
    }

    // Vérifier si l'index existe déjà
    $idxStmt = $db->prepare("SHOW INDEX FROM draft_events WHERE Key_name = 'idx_draft_events_original_event_id'");
    $idxStmt->execute();
    $idxExists = ($idxStmt->fetch(PDO::FETCH_ASSOC) !== false);

    if (!$idxExists) {
        $db->exec("CREATE INDEX idx_draft_events_original_event_id ON draft_events(original_event_id)");
        $result['index_created'] = true;
        $result['messages'][] = 'Index idx_draft_events_original_event_id créé.';
    } else {
        $result['messages'][] = 'Index déjà présent.';
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError('add_original_event_id.php', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
