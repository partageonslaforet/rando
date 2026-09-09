<?php
/**
 * localisation: templates/layouts/footer.php
 * Role: Layout : Footer
 * Usage: Structure du pied de page
 * Dépendances: Aucune
 */
?>
    <?php if (strpos($_SERVER['SCRIPT_NAME'], 'header.php') !== false): ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/scripts.php'; ?>
</body>
</html>
