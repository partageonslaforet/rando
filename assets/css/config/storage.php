<?php

// Configuration du stockage des fichiers
return [
    // Chemin physique de base pour le stockage (dans l'espace web)
    'storage_base_path' => $_SERVER['DOCUMENT_ROOT'] . '/uploads',
    
    // URL de base pour l'accès public
    'public_base_url' => 'https://rando.partageonslaforet.be/uploads',
    
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
        ]
    ],
    
    // Types de fichiers autorisés
    'allowed_extensions' => [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp']
    ],
    
    // Taille maximale des fichiers (en bytes)
    'max_file_size' => 5 * 1024 * 1024, // 5MB
];
