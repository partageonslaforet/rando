<?php
function render_category_filters($pdo) {
    // Récupérer les catégories uniques depuis la table events 
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM events WHERE category IS NOT NULL ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Récupérer la catégorie sélectionnée depuis l'URL si elle existe
    $selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
    ?>
    
    <div class="category-filters">
        <h4>Filtrer par catégorie</h4>
        <div class="btn-group-vertical" role="group" aria-label="Filtres de catégories">
            <a href="?<?= http_build_query(array_merge($_GET, ['category' => ''])) ?>" 
               class="btn <?= $selectedCategory === '' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Toutes les catégories
            </a>
            <?php foreach ($categories as $category): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['category' => $category])) ?>" 
                   class="btn <?= $selectedCategory === $category ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <?= htmlspecialchars($category) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
?>