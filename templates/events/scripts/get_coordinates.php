<?php

require_once __DIR__ . '/../config/database.php';

function geocodeLocation($location) {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PartageonsLaForet/1.0'
            ]
        ]
    ];
    $context = stream_context_create($opts);
    
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($location);
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!empty($data)) {
        return $data[0]['lat'] . ',' . $data[0]['lon'];
    }
    
    return null;
}

try {
    // Récupérer tous les événements
    $stmt = $db->query("SELECT id, title, location FROM events");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($events as $event) {
        echo "Événement: {$event['title']}\n";
        echo "Adresse: {$event['location']}\n";
        $coordinates = geocodeLocation($event['location']);
        echo "Coordonnées: {$coordinates}\n\n";
        
        // Attendre 1 seconde entre chaque requête
        sleep(1);
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
