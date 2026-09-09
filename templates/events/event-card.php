<?php
/**
 * localisation: templates/events/event-card.php
 * Role: Template evenement : Event Card
 * Usage: Carte evenement
 * Dépendances: Aucune
 */

// Logs de débogage détaillés

// Logs initiaux
echo "<!-- DEBUG START -->\n";
echo "<!-- Event ID: " . htmlspecialchars($event['id']) . " -->\n";
echo "<!-- Title: " . htmlspecialchars($event['title']) . " -->\n";
echo "<!-- Start time: " . htmlspecialchars($event['start_time']) . " -->\n";
echo "<!-- End time: " . htmlspecialchars($event['end_time']) . " -->\n";
echo "<!-- Location: " . htmlspecialchars($event['location']) . " -->\n";
echo "<!-- Venue: " . htmlspecialchars($event['venue']) . " -->\n";

// Détection automatique de la catégorie basée sur le titre
if (stripos($event['title'], 'vtt') !== false) {
    $categoryIcon = 'bi-bicycle';
    $categoryName = 'VTT';
} elseif (stripos($event['title'], 'marche') !== false) {
    $categoryIcon = 'bi-person-walking';
    $categoryName = 'Marche';
} elseif (stripos($event['title'], 'trail') !== false || stripos($event['title'], 'running') !== false) {
    $categoryIcon = 'bi-person-running';
    $categoryName = 'Trail/Running';
} else {
    $categoryIcon = 'bi-calendar-event';
    $categoryName = 'Événement';
}

// Utiliser l'image de fallback si l'image principale n'existe pas
$imagePath = !empty($event['main_image_path']) ? $event['main_image_path'] : 
            (!empty($event['fallback_image']) ? $event['fallback_image'] : '/assets/images/default-event.jpg');

$eventUrl = "/templates/events/event-detail.php?id=" . htmlspecialchars($event['id']);

echo "<!-- Image path: " . htmlspecialchars($imagePath) . " -->\n";


// Vérification des dates et heures

// Vérification du lieu et de l'adresse

// Vérification des autres informations


?>
<!-- DÉBUT CARTE -->
<div class="modern-event-card">
    <div class="modern-event-image-wrapper">
        <img class="modern-event-image" 
             src="<?= htmlspecialchars($imagePath) ?>" 
             alt="<?= htmlspecialchars($event['title']) ?>"
             onerror="this.src='/assets/images/default-event.jpg'">
        <div class="modern-event-badge">
            <i class="bi <?= $categoryIcon ?>"></i>
            <span><?= htmlspecialchars($categoryName) ?></span>
        </div>
    </div>
    
    <div class="modern-event-content">
        <h3 class="modern-event-title"><?= htmlspecialchars($event['title']) ?></h3>
        <!-- DÉBUT INFO -->
        <div class="modern-event-info">
            <!-- Date et heure -->
            <div class="modern-event-detail">
                <i class="bi bi-clock"></i>
                <div class="detail-text">
                   <!--  <span class="detail-label">Date</span> -->
                    <span class="detail-value">
                        <?= date('d/m/Y', strtotime($event['date'])) ?>
                    </span>
                </div>
            </div>

            <!-- Horaires -->
            
            <!-- <?php //if (!empty($event['start_time']) && !empty($event['end_time'])): ?> -->
                <div class="modern-event-detail">
                <i class="bi bi-alarm"></i>
                <div class="detail-text">
                    <span class="detail-label">Inscriptions
                        <span class="detail-value">
                            de <?= date('H\hi', strtotime($event['start_time'])) ?>
                            à <?= date('H\hi', strtotime($event['end_time'])) ?>
                        </span>
                    </span>
                </div>
            </div>
            <!-- <?php //endif; ?> -->
            
            <!-- Lieu -->
            <div class="modern-event-detail">
                <i class="bi bi-geo-alt"></i>
                <div class="detail-text">
                    <!-- <span class="detail-label">Lieu</span> -->
                    <span class="detail-value"><?= htmlspecialchars($event['location']) ?></span>
                </div>
            </div>
            <?php if (!empty($event['location'])): ?>
            
            <?php endif; ?>
            
            <!-- Adresse -->
            <div class="modern-event-detail">
                <i class="bi bi-map"></i>
                <div class="detail-text">
                    <!-- <span class="detail-label">Adresse</span> -->
                    <span class="detail-value"><?= htmlspecialchars($event['venue']) ?></span>
                </div>
            </div>
            <?php if (!empty($event['venue'])): ?>
            
            <?php endif; ?>
        </div>
        <!-- FIN INFO -->
        
        <!-- Lien vers les détails -->
        <a href="templates/events/event-detail.php?id=<?= $event['id'] ?>" class="modern-event-footer">
            <span class="voir-plus">Voir plus <i class="bi bi-arrow-right"></i></span>
        </a>
    </div>
</div>
<!-- FIN CARTE -->
<?php echo "<!-- DEBUG END -->\n"; ?>
