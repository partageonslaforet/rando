<?php
// Template simplifié pour la prévisualisation d'événement
if (!isset($eventData) || !is_array($eventData)) {
    return '';
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function formatTime($time) {
    return substr($time, 0, 5);
}

function getCategoryLabel($category) {
    $labels = [
        'running' => 'Course à pied',
        'hiking' => 'Randonnée',
        'cycling' => 'Vélo'
    ];
    return $labels[$category] ?? $category;
}
?>

<div class="event-preview">
    <div class="preview-header">
        <?php if (!empty($eventData['main_image_path'])): ?>
            <img src="<?= htmlspecialchars(strpos($eventData['main_image_path'], 'http') === 0 ? $eventData['main_image_path'] : '/' . $eventData['main_image_path']) ?>" 
                 alt="<?= htmlspecialchars($eventData['title']) ?>" 
                 class="main-image">
        <?php endif; ?>
        
        <h1><?= htmlspecialchars($eventData['title']) ?></h1>
        <div class="event-meta">
            <span class="date"><?= formatDate($eventData['date']) ?></span>
            <span class="time"><?= formatTime($eventData['start_time']) ?><?= $eventData['end_time'] ? ' - ' . formatTime($eventData['end_time']) : '' ?></span>
            <span class="category"><?= getCategoryLabel($eventData['category']) ?></span>
        </div>
    </div>

    <div class="event-content">
        <div class="description">
            <?= nl2br(htmlspecialchars($eventData['description'])) ?>
        </div>

        <div class="location">
            <h2>Lieu</h2>
            <p><?= htmlspecialchars($eventData['location']) ?></p>
            <?php if (!empty($eventData['venue'])): ?>
                <p class="venue"><?= htmlspecialchars($eventData['venue']) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($eventData['routes'])): ?>
            <div class="routes">
                <h2>Parcours</h2>
                <?php foreach ($eventData['routes'] as $route): ?>
                    <div class="route">
                        <h3><?= htmlspecialchars($route['name']) ?></h3>
                        <div class="route-details">
                            <span class="distance"><?= number_format($route['distance'], 1) ?> km</span>
                            <span class="elevation">D+ <?= (int)$route['elevation_gain'] ?> m</span>
                            <?php if (!empty($route['price'])): ?>
                                <span class="price"><?= number_format($route['price'], 2) ?> €</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($eventData['organizer_name'])): ?>
            <div class="organizer">
                <h2>Organisateur</h2>
                <?php if (!empty($eventData['organizer_logo_path'])): ?>
                    <img src="<?= htmlspecialchars(strpos($eventData['organizer_logo_path'], 'http') === 0 ? $eventData['organizer_logo_path'] : '/' . $eventData['organizer_logo_path']) ?>" 
                         alt="Logo <?= htmlspecialchars($eventData['organizer_name']) ?>" 
                         class="organizer-logo">
                <?php endif; ?>
                <p class="organizer-name"><?= htmlspecialchars($eventData['organizer_name']) ?></p>
                <?php if (!empty($eventData['organizer_email'])): ?>
                    <p class="organizer-email"><?= htmlspecialchars($eventData['organizer_email']) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($eventData['contacts'])): ?>
            <div class="contacts">
                <h2>Contacts</h2>
                <?php foreach ($eventData['contacts'] as $contact): ?>
                    <div class="contact">
                        <p class="contact-name"><?= htmlspecialchars($contact['name']) ?></p>
                        <?php if (!empty($contact['email'])): ?>
                            <p class="contact-email"><?= htmlspecialchars($contact['email']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($contact['phone'])): ?>
                            <p class="contact-phone"><?= htmlspecialchars($contact['phone']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($eventData['secondary_images'])): ?>
            <div class="gallery">
                <h2>Galerie photos</h2>
                <div class="image-grid">
                    <?php foreach ($eventData['secondary_images'] as $image): ?>
                        <div class="gallery-image">
                            <img src="<?= htmlspecialchars(strpos($image, 'http') === 0 ? $image : '/' . $image) ?>" 
                                 alt="Image de l'événement">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.event-preview {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.preview-header {
    margin-bottom: 30px;
}

.main-image {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 20px;
}

.event-meta {
    color: #666;
    margin: 10px 0;
}

.event-meta span {
    margin-right: 15px;
}

.routes .route {
    background: #f5f5f5;
    padding: 15px;
    margin: 10px 0;
    border-radius: 5px;
}

.route-details {
    display: flex;
    gap: 15px;
    color: #666;
}

.contacts .contact {
    background: #f9f9f9;
    padding: 15px;
    margin: 10px 0;
    border-radius: 5px;
}

.gallery .image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.gallery-image img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 5px;
}

.organizer-logo {
    max-width: 200px;
    max-height: 100px;
    object-fit: contain;
    margin: 10px 0;
}
</style>
