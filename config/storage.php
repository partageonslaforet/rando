<?php

// Configuration du stockage des fichiers
return [
    // Chemin physique de base pour le stockage (dans l'espace web)
    'storage_base_path' => '/home/cool5792/rando.partageonslaforet.be/uploads',
    
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
        'organizers' => [
            'images' => [
                'storage_path' => '/organizers',
                'public_path' => '/organizers'
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
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'gpx' => ['gpx', 'xml'] 
    ],

    // Types MIME autorisés
    'allowed_mimes' => [
        'gpx' => ['application/gpx+xml', 'text/xml', 'application/xml']
    ],
    
    // Taille maximale des fichiers (en bytes)
    'max_file_size' => 5 * 1024 * 1024, // 5MB
];
