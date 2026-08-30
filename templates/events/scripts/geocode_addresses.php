<?php

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

// Liste des adresses
$addresses = [
    'Rue du Bois, Braine-le-Comte, Belgique',
    'Rue de la Houssière, Braine-le-Comte, Belgique',
    'Rue de la Brainette, Braine-le-Comte, Belgique'
];

foreach ($addresses as $address) {
    echo "Adresse: {$address}\n";
    $coordinates = geocodeLocation($address);
    echo "Coordonnées: {$coordinates}\n\n";
    
    // Attendre 1 seconde entre chaque requête
    sleep(1);
}
