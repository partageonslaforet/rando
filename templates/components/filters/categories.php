<?php
/**
 * localisation: templates/components/filters/categories.php
 * Role: Composant : Categories
 * Usage: Filtres de categories d evenements
 * Dépendances: Aucune
 */
function render_category_filters($pdo) {
    // Récupération des catégories depuis la table event_categories
    $stmt = $pdo->query("
        SELECT id, name, icon, color 
        FROM event_categories 
        WHERE active = 1
        ORDER BY sort_order ASC, name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="category-filters">
        <div class="category-filter-group">
            <!-- Bouton "Toutes" -->
            <button class="btn btn-primary active disabled" data-category="all" data-template="filter-button">
                Toutes
                <span class="badge" data-count>0</span>
            </button>
            
            <!-- Boutons pour chaque catégorie -->
            <?php foreach ($categories as $category): ?>
                <button class="btn btn-primary disabled" 
                        data-category="<?= htmlspecialchars($category['id']) ?>" 
                        data-template="filter-button">
                    <?= htmlspecialchars($category['name']) ?>
                    <span class="badge" data-count>0</span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
?>