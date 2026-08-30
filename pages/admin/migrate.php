<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

// Vérifier que l'utilisateur est admin
if (!isAdmin()) {
    header('Location: /');
    exit();
}

// Traitement de la migration si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'migrate') {
    try {
        // Forcer l'environnement de production
        $_SERVER['HTTP_HOST'] = 'rando.partageonslaforet.be';

        require_once __DIR__ . '/../../config/database.php';
        require_once __DIR__ . '/../../src/Models/EventCategory.php';

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

        $messages = [];

        // Créer une table temporaire pour stocker les correspondances d'ID
        $pdo->exec("CREATE TEMPORARY TABLE IF NOT EXISTS category_mapping (
            old_category VARCHAR(50),
            new_category_id INT
        )");

        $messages[] = "Migration des catégories...";

        // Créer ou mettre à jour les catégories de base
        foreach ($baseCategories as $code => $categoryData) {
            try {
                // Vérifier si la catégorie existe déjà
                $stmt = $pdo->prepare("SELECT id FROM event_categories WHERE code = ?");
                $stmt->execute([$code]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $messages[] = "Mise à jour de la catégorie : {$categoryData['name']}";
                    $categoryManager->update($existing['id'], $categoryData);
                    $categoryId = $existing['id'];
                } else {
                    $messages[] = "Création de la catégorie : {$categoryData['name']}";
                    $categoryId = $categoryManager->create($categoryData);
                }

                // Stocker la correspondance dans la table temporaire
                $stmt = $pdo->prepare("INSERT INTO category_mapping (old_category, new_category_id) VALUES (?, ?)");
                $stmt->execute([$code, $categoryId]);

            } catch (Exception $e) {
                $messages[] = "Erreur lors du traitement de la catégorie {$code}: " . $e->getMessage();
            }
        }

        $messages[] = "Migration des événements...";

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
            $messages[] = "Nombre d'événements mis à jour : $updatedCount";

        } catch (Exception $e) {
            $messages[] = "Erreur lors de la mise à jour des événements : " . $e->getMessage();
        }

        // Nettoyage
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS category_mapping");

        $messages[] = "Migration terminée !";
        $success = true;

    } catch (Exception $e) {
        $messages = ["Erreur critique : " . $e->getMessage()];
        $success = false;
    }
}

// Inclure l'en-tête
$pageTitle = "Migration des catégories";
include '../../templates/admin/header.php';
?>

<div class="container py-4">
    <h1>Migration des catégories</h1>
    
    <?php if (isset($messages)): ?>
        <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
            <?php foreach ($messages as $message): ?>
                <p class="mb-1"><?php echo htmlspecialchars($message); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Migration des catégories d'événements</h5>
            <p class="card-text">
                Cette page permet de migrer les anciennes catégories (enum) vers le nouveau système de catégories.
                Les catégories suivantes seront créées :
            </p>
            <ul>
                <li>Course à pied (running)</li>
                <li>Randonnée (hiking)</li>
                <li>Cyclisme (cycling)</li>
            </ul>
            <p class="card-text text-warning">
                <i class="bi bi-exclamation-triangle"></i>
                Attention : Cette opération ne peut être effectuée qu'une seule fois.
            </p>
            <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir lancer la migration ?');">
                <input type="hidden" name="action" value="migrate">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-arrow-repeat"></i>
                    Lancer la migration
                </button>
            </form>
        </div>
    </div>
</div>

<?php include '../../templates/admin/footer.php'; ?>
