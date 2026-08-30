<?php
// Vérifier si nous avons les parcours
$stmtRoutes = $pdo->prepare("SELECT * FROM events_draft_parcours WHERE draft_id = :draft_id");
$stmtRoutes->execute(['draft_id' => $draft['id']]);
$routes = $stmtRoutes->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="preview-container">
    <!-- En-tête avec image principale -->
    <div class="preview-header" style="background-image: url('<?php echo htmlspecialchars($event['main_image_path']); ?>')">
        <div class="preview-header-overlay">
            <h1><?php echo htmlspecialchars($event['title']); ?></h1>
            <div class="event-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('d/m/Y', strtotime($event['date'])); ?>
                </div>
                <?php if ($event['start_time']): ?>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <?php 
                    echo htmlspecialchars($event['start_time']);
                    if ($event['end_time']) {
                        echo ' - ' . htmlspecialchars($event['end_time']);
                    }
                    ?>
                </div>
                <?php endif; ?>
                <div class="meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($event['location']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="preview-content">
        <div class="row">
            <!-- Colonne de gauche : Informations principales -->
            <div class="col-md-8">
                <!-- Description -->
                <div class="content-section">
                    <h2>Description</h2>
                    <div class="description">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                    </div>
                </div>

                <!-- Parcours -->
                <?php if ($routes): ?>
                <div class="content-section">
                    <h2>Parcours</h2>
                    <div class="routes-container">
                        <?php foreach ($routes as $route): ?>
                        <div class="route-card">
                            <div class="route-header">
                                <h3><?php echo htmlspecialchars($route['name']); ?></h3>
                                <?php if ($route['gpx_downloadable']): ?>
                                <span class="gpx-badge"><i class="fas fa-download"></i> GPX</span>
                                <?php endif; ?>
                            </div>
                            <div class="route-details">
                                <?php if ($route['distance']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-route"></i>
                                    <?php echo number_format($route['distance'], 1); ?> km
                                </div>
                                <?php endif; ?>
                                <?php if ($route['elevation_gain']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-mountain"></i>
                                    <?php echo $route['elevation_gain']; ?> m
                                </div>
                                <?php endif; ?>
                                <?php if ($route['price']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-euro-sign"></i>
                                    <?php echo number_format($route['price'], 2); ?> €
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($route['description']): ?>
                            <div class="route-description">
                                <?php echo nl2br(htmlspecialchars($route['description'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Carte -->
                <?php if ($event['coordinates']): ?>
                <div class="content-section">
                    <h2>Localisation</h2>
                    <div id="map" style="height: 400px;"></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Colonne de droite : Informations complémentaires -->
            <div class="col-md-4">
                <!-- Informations pratiques -->
                <div class="content-section info-card">
                    <h2>Informations pratiques</h2>
                    <ul class="info-list">
                        <li>
                            <i class="bi bi-clock"></i>
                            <strong>Horaires:</strong>
                            <?php echo date('H:i', strtotime($event['start_time'])); ?>
                            <?php if (!empty($event['end_time'])): ?>
                                - <?php echo date('H:i', strtotime($event['end_time'])); ?>
                            <?php endif; ?>
                        </li>
                        <li>
                            <i class="bi bi-geo-alt"></i>
                            <strong>Lieu:</strong>
                            <?php echo htmlspecialchars($event['location']); ?>
                        </li>
                        <?php if (!empty($event['venue'])): ?>
                        <li>
                            <i class="bi bi-building"></i>
                            <strong>Lieu de rendez-vous:</strong>
                            <?php echo htmlspecialchars($event['venue']); ?>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($event['max_participants'])): ?>
                        <li>
                            <i class="bi bi-people"></i>
                            <strong>Places disponibles:</strong>
                            <?php echo $event['max_participants']; ?> places
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($event['difficulty'])): ?>
                        <li>
                            <i class="bi bi-bar-chart"></i>
                            <strong>Difficulté:</strong>
                            <?php echo htmlspecialchars($event['difficulty']); ?>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Parcours -->
                <?php if (!empty($routes)): ?>
                <div class="content-section">
                    <h2>Parcours</h2>
                    <?php foreach ($routes as $route): ?>
                    <div class="route-card mb-3">
                        <h3><?php echo htmlspecialchars($route['name']); ?></h3>
                        <div class="route-details">
                            <?php if (!empty($route['distance'])): ?>
                            <div class="detail-item">
                                <i class="bi bi-signpost-2"></i>
                                <?php echo number_format($route['distance'], 1); ?> km
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($route['elevation_gain'])): ?>
                            <div class="detail-item">
                                <i class="bi bi-graph-up-arrow"></i>
                                <?php echo $route['elevation_gain']; ?> m D+
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($route['price'])): ?>
                            <div class="detail-item">
                                <i class="bi bi-currency-euro"></i>
                                <?php echo number_format($route['price'], 2); ?> €
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($route['description'])): ?>
                        <div class="route-description">
                            <?php echo nl2br(htmlspecialchars($route['description'])); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($route['gpx_file'])): ?>
                        <div class="gpx-badge">
                            <i class="bi bi-file-earmark-text"></i>
                            Fichier GPX inclus
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Carte avec les traces GPX -->
<?php if (!empty($routes)): ?>
<div class="content-section">
    <h2>Carte des parcours</h2>
    <div id="map" style="height: 400px;"></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-gpx/gpx.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser la carte
    var map = L.map('map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    var bounds = L.latLngBounds();
    var colors = ['#FF4136', '#0074D9', '#2ECC40', '#FF851B', '#B10DC9'];

    // Ajouter chaque trace GPX
    <?php foreach ($routes as $index => $route): ?>
    <?php if (!empty($route['gpx_file'])): ?>
    new L.GPX("<?php echo $route['gpx_file']; ?>", {
        async: true,
        polyline_options: {
            color: colors[<?php echo $index; ?> % colors.length],
            weight: 3,
            lineCap: 'round'
        },
        marker_options: {
            startIconUrl: '/assets/images/pin-icon-start.png',
            endIconUrl: '/assets/images/pin-icon-end.png',
            shadowUrl: '/assets/images/pin-shadow.png'
        }
    }).on('loaded', function(e) {
        bounds.extend(e.target.getBounds());
        map.fitBounds(bounds);
    }).addTo(map);
    <?php endif; ?>
    <?php endforeach; ?>

    // Ajouter le marqueur du lieu de rendez-vous
    <?php if (!empty($event['coordinates'])): ?>
    var coords = <?php echo json_encode(explode(',', $event['coordinates'])); ?>;
    L.marker([coords[0], coords[1]]).addTo(map)
        .bindPopup("<?php echo htmlspecialchars($event['venue'] ?: $event['location']); ?>");
    bounds.extend([coords[0], coords[1]]);
    <?php endif; ?>
});
</script>
<?php endif; ?>

<!-- Styles CSS -->
<style>
.preview-container {
    max-width: 1200px;
    margin: 0 auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.preview-header {
    height: 400px;
    background-size: cover;
    background-position: center;
    border-radius: 8px 8px 0 0;
    position: relative;
}

.preview-header-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 2rem;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
    color: white;
}

.preview-header h1 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.event-meta {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.preview-content {
    padding: 2rem;
}

.content-section {
    margin-bottom: 2rem;
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.content-section h2 {
    color: #333;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f0f0f0;
}

.routes-container {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

.route-card {
    background: #fff;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.route-details {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin: 10px 0;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9em;
    color: #666;
}

.detail-item i {
    color: #2196F3;
}

.route-description {
    font-size: 0.9em;
    color: #666;
    margin-top: 10px;
}

.gpx-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #e3f2fd;
    color: #1976D2;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.8em;
    margin-top: 10px;
}

#map {
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
