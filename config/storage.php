<?php

// Configuration du stockage des fichiers
return [
    // Chemin physique de base pour le stockage (dans l'espace web)
    'storage_base_path' => $_ENV['STORAGE_BASE_PATH'] ?? __DIR__ . '/../public/uploads',
    
    // URL de base pour l'accès public
    'public_base_url' => '/uploads',
    
    // Sous-dossiers spécifiques
    'directories' => [
        'events' => [
            'images' => [
                'storage_path' => '/events',
                'public_path' => '/events'
            ]
        ],
        'organizer_logos' => [
            'images' => [
                'storage_path' => '/organizer_logos',
                'public_path' => '/organizer_logos'
            ]
        ],
        'sponsors' => [
            'images' => [
                'storage_path' => '/sponsors',
                'public_path' => '/sponsors'
            ]
        ],
        'gpx' => [
            'files' => [
                'storage_path' => '/gpx',
                'public_path' => '/gpx'
            ]
        ]
    ],
    
    // Types de fichiers autorisés
    'allowed_extensions' => [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'],
        'gpx' => ['gpx', 'xml'] 
    ],

    // Types MIME autorisés
    'allowed_mimes' => [
        'gpx' => ['application/gpx+xml', 'text/xml', 'application/xml', 'application/octet-stream', 'text/plain']
    ],
    
    // Taille maximale des fichiers (en bytes)
    'max_file_size' => 5 * 1024 * 1024, // 5MB
];
