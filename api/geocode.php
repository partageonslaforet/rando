<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Vérifier si une requête est fournie
if (!isset($_GET['q'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing query parameter']);
    exit;
}

// Paramètres pour Nominatim
$query = urlencode($_GET['q']);
$url = "https://nominatim.openstreetmap.org/search?format=json&q={$query}";

// Ajouter un User-Agent comme requis par Nominatim
$options = [
    'http' => [
        'method' => 'GET',
        'header' => [
            'User-Agent: PartageonsLaForet/1.0',
        ]
    ]
];

$context = stream_context_create($options);

try {
    // Ajouter un délai pour respecter la limite de taux de Nominatim
    usleep(1000000); // 1 seconde de délai

    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to fetch data from Nominatim');
    }
    
    echo $response;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Geocoding failed',
        'message' => $e->getMessage()
    ]);
}
