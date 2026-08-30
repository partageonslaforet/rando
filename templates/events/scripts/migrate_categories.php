<?php

// Forcer l'environnement de production
$_SERVER['HTTP_HOST'] = 'rando.partageonslaforet.be';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/EventCategory.php';

// Obtenir la connexion à la base de données
$pdo = getConnection();
$categoryManager = new EventCategory($pdo);

// Définir les catégories de base avec leurs attributs
$baseCategories = [
    'running' => [
        'code' => 'running',
        'name' => 'Course à pied',
        'icon' => 'bi-person-running',
        'color' => '#FF4B4B',
        'active' => true,
        'sort_order' => 1
    ],
    'hiking' => [
        'code' => 'hiking',
        'name' => 'Randonnée',
        'icon' => 'bi-tree',
        'color' => '#4CAF50',
        'active' => true,
        'sort_order' => 2
    ],
    'cycling' => [
        'code' => 'cycling',
        'name' => 'Cyclisme',
        'icon' => 'bi-bicycle',
        'color' => '#2196F3',
        'active' => true,
        'sort_order' => 3
    ]
];

// Créer une table temporaire pour stocker les correspondances d'ID
$pdo->exec("CREATE TEMPORARY TABLE IF NOT EXISTS category_mapping (
    old_category VARCHAR(50),
    new_category_id INT
)");

echo "Migration des catégories...\n";

// Créer ou mettre à jour les catégories de base
foreach ($baseCategories as $code => $categoryData) {
    try {
        // Vérifier si la catégorie existe déjà
        $stmt = $pdo->prepare("SELECT id FROM event_categories WHERE code = ?");
        $stmt->execute([$code]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo "Mise à jour de la catégorie : {$categoryData['name']}\n";
            $categoryManager->update($existing['id'], $categoryData);
            $categoryId = $existing['id'];
        } else {
            echo "Création de la catégorie : {$categoryData['name']}\n";
            $categoryId = $categoryManager->create($categoryData);
        }

        // Stocker la correspondance dans la table temporaire
        $stmt = $pdo->prepare("INSERT INTO category_mapping (old_category, new_category_id) VALUES (?, ?)");
        $stmt->execute([$code, $categoryId]);

    } catch (Exception $e) {
        echo "Erreur lors du traitement de la catégorie {$code}: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration des événements...\n";

// Mettre à jour les événements avec les nouveaux IDs de catégorie
try {
    // Ajouter la colonne category_id si elle n'existe pas
    $pdo->exec("ALTER TABLE events ADD COLUMN IF NOT EXISTS category_id INT");

    // Mettre à jour les événements
    $stmt = $pdo->prepare("
        UPDATE events e
        JOIN category_mapping cm ON e.category = cm.old_category
        SET e.category_id = cm.new_category_id
        WHERE e.category_id IS NULL
    ");
    $stmt->execute();

    $updatedCount = $stmt->rowCount();
    echo "Nombre d'événements mis à jour : $updatedCount\n";

} catch (Exception $e) {
    echo "Erreur lors de la mise à jour des événements : " . $e->getMessage() . "\n";
}

// Nettoyage
$pdo->exec("DROP TEMPORARY TABLE IF EXISTS category_mapping");

echo "\nMigration terminée !\n";
