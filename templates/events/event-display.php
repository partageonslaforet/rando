<?php
/**
 * Template partagé d'affichage d'un événement (publié ou brouillon).
 * Attend un tableau $event normalisé (voir EventDisplayBuilder.php) et $mode ('published'|'draft').
 */

if (!function_exists('displayValue')) {
    function displayValue($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('attrValue')) {
    function attrValue($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!isset($event) || !is_array($event)) {
    echo '<div class="alert alert-danger">Événement non disponible.</div>';
    return;
}

$mode = $mode ?? 'published';
$isPreview = $mode === 'draft';
$mainImage = $event['main_image'] ?? '/assets/images/events/default-event.jpg';
$categoryList = $event['categories'] ?? [];
$routes = $event['routes'] ?? [];
$contacts = $event['contacts'] ?? [];
$secondaryImages = $event['secondary_images'] ?? [];
$organizer = $event['organizer'] ?? null;

$galleryImages = array_values(array_unique(array_merge([$mainImage], $secondaryImages)));

$lat = $event['latitude'] ?? null;
$lng = $event['longitude'] ?? null;
$hasCoords = !empty($lat) && !empty($lng);
// Regrouper les parcours par catégorie et trier par distance croissante
$routesByCategory = [];
foreach ($routes as $r) {
    $catKey = !empty($r['category_name']) ? $r['category_name'] : 'Autre';
    $routesByCategory[$catKey][] = $r;
}
foreach ($routesByCategory as &$catRoutes) {
    usort($catRoutes, fn($a, $b) => ((float) ($a['distance'] ?? 0)) <=> ((float) ($b['distance'] ?? 0)));
}
unset($catRoutes);

$gpxFiles = array_filter(array_column($routes, 'gpx_file'));
$hasGpx = !empty($gpxFiles);

function displayValue($value): string
{
    return htmlspecialchars((string) $value);
}

function attrValue($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<article class="event-display <?= $isPreview ? 'event-preview' : 'event-detail-page' ?>"
         data-lat="<?= attrValue($lat) ?>"
         data-lng="<?= attrValue($lng) ?>"
         data-location="<?= attrValue($event['location'] ?? '') ?>"
         data-venue="<?= attrValue($event['venue'] ?? '') ?>"
         data-routes="<?= attrValue(json_encode(array_values($gpxFiles))) ?>">

    <header class="event-hero">
        <div class="event-hero-bg" style="background-image: url('<?= htmlspecialchars($mainImage) ?>');"></div>

        <!-- Ligne 1 : bouton Partager, ancré en haut à droite du hero (indépendant du contenu) -->
        <button id="shareBtn" class="ehm-share btn-share hero-share-btn" type="button" aria-label="Partager l'événement">
            <i class="bi bi-share"></i>
        </button>

        <!-- Bouton Retour aux événements, en bas à droite du hero -->
        <a href="http://localhost:9999/" class="ehm-share btn-share hero-back-btn" aria-label="Retour aux événements">
            <i class="bi bi-arrow-left"></i>
        </a>

        <!-- Bouton fermer le hero -->
        <button type="button" class="ehm-share btn-share hero-close-btn" aria-label="Fermer le bandeau">
            <i class="bi bi-x-lg"></i>
        </button>

        <div class="event-hero-overlay">
            
    
            <!-- Ligne 2 : titre centré -->
            <div class="ehm-title-wrap">
                <h1 class="ehm-title"><?= displayValue($event['title'] ?? 'Événement') ?></h1>
                <?php if (isset($event['view_count'])): ?>
                    <span class="pill ehm-view-count"><i class="bi bi-eye"></i><?= (int) $event['view_count'] ?> vue<?= ($event['view_count'] ?? 0) > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>

            <!-- Ligne 3 : date et heure -->
            <div class="ehm-meta">
                <?php if (!empty($event['date'])): ?>
                    <span class="pill"><i class="bi bi-calendar3"></i><?= date('d/m/Y', strtotime($event['date'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($event['start_time'])): ?>
                    <span class="pill"><i class="bi bi-clock"></i><?= substr($event['start_time'],0,5) ?><?php if(!empty($event['end_time']) && $event['end_time']!=='00:00:00'): ?> – <?= substr($event['end_time'],0,5) ?><?php endif; ?></span>
                <?php endif; ?>
                <?php if (!empty($event['meeting_name'])): ?>
                    <span class="pill"><i class="bi bi-geo"></i><?= displayValue($event['meeting_name']) ?></span>
                <?php endif; ?>
                <?php
                $meetingCity = $event['meeting_city'] ?? '';
                if (empty($meetingCity) && !empty($event['meeting_address'])) {
                    if (preg_match('/\b\d{4,5}\s+(.+)$/', trim($event['meeting_address']), $m)) {
                        $meetingCity = trim($m[1]);
                    } else {
                        $parts = preg_split('/[,\s]+/', trim($event['meeting_address']));
                        $meetingCity = $parts ? trim(end($parts)) : '';
                    }
                }
                ?>
                <?php if (!empty($meetingCity)): ?>
                    <span class="pill"><i class="bi bi-geo-alt"></i><?= displayValue($meetingCity) ?></span>
                <?php endif; ?>
                <?php if ((!empty($event['location']) || !empty($event['venue'])) && trim($event['location'] ?: $event['venue']) !== trim($event['meeting_name'] ?: '')): ?>
                    <span class="pill"><i class="bi bi-pin-map"></i><?= displayValue($event['location'] ?: $event['venue']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Ligne 4 : tags catégories -->
            <?php if (!empty($categoryList)): ?>
                <div class="ehm-chips">
                <?php foreach ($categoryList as $cat): ?>
                    <span class="chip"><i class="<?= strpos($cat['icon']??'', 'bi-')===0 ? 'bi '.$cat['icon'] : 'bi bi-tree' ?>"></i><?= displayValue($cat['name'] ?? '') ?></span>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>


        </div>

        <!-- Barre réduite affichée quand le hero est fermé -->
        <div class="event-hero-collapsed" aria-hidden="true">
            <a href="http://localhost:9999/" class="btn-share hero-back-link" aria-label="Retour aux événements">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="hero-collapsed-center">
                <span class="hero-collapsed-title"><?= displayValue($event['title'] ?? 'Événement') ?></span>
                <?php if (isset($event['view_count'])): ?>
                    <span class="pill"><i class="bi bi-eye"></i><?= (int) $event['view_count'] ?> vue<?= ($event['view_count'] ?? 0) > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-share hero-reopen-btn" aria-label="Ré-ouvrir le bandeau">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </header>

    <div class="event-topbar" id="eventTopbar" style="transform: translateY(-120%); opacity: 0;">
      <div class="container topbar-row">
        <div class="topbar-left">
          <span class="topbar-title"><?= displayValue($event['title'] ?? 'Événement') ?></span>
          <div class="topbar-meta">
            <?php if (!empty($event['date'])): ?>
              <span class="pill"><i class="bi bi-calendar3"></i><?= date('d/m/Y', strtotime($event['date'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($event['start_time'])): ?>
              <span class="pill"><i class="bi bi-clock"></i><?= substr($event['start_time'],0,5) ?><?php if(!empty($event['end_time']) && $event['end_time']!=='00:00:00'): ?> – <?= substr($event['end_time'],0,5) ?><?php endif; ?></span>
            <?php endif; ?>
            <?php if (!empty($event['location']) || !empty($event['venue'])): ?>
              <span class="pill"><i class="bi bi-geo-alt"></i><?= displayValue($event['location'] ?: $event['venue']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="topbar-right">
          <button class="btn-share" type="button" data-share="event">
            <i class="bi bi-share"></i><span>Partager</span>
          </button>
        </div>
      </div>
    </div>

    <div class="container event-body">
        <div class="row">
            <main class="col-lg-8">
                <section class="event-section desc-card">
                  <div class="section-head">
                    <h2><i class="bi bi-info-circle"></i>Description</h2>
                  </div>
                 <?php if (!empty($event['description'])): ?>
                    <div class="desc-content">
                      <?= nl2br(displayValue($event['description'])) ?>
                    </div>
                  <?php endif; ?>
                  <div class="info-grid">
                    <?php if (!empty($event['location'])): ?>
                      <div class="info-item">
                        <i class="bi bi-geo-alt"></i>
                        <div>
                          <div class="label">Lieu</div>
                          <div class="value"><?= displayValue($event['location']) ?></div>
                        </div>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($event['venue'])): ?>
                      <div class="info-item">
                        <i class="bi bi-pin-map"></i>
                        <div>
                          <div class="label">Rendez-vous</div>
                          <div class="value"><?= displayValue($event['venue']) ?></div>
                        </div>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($event['start_time'])): ?>
                      <div class="info-item">
                        <i class="bi bi-clock"></i>
                        <div>
                          <div class="label">Début</div>
                          <div class="value"><?= substr($event['start_time'], 0, 5) ?></div>
                        </div>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($event['end_time']) && $event['end_time'] !== '00:00:00'): ?>
                      <div class="info-item">
                        <i class="bi bi-clock-history"></i>
                        <div>
                          <div class="label">Fin</div>
                          <div class="value"><?= substr($event['end_time'], 0, 5) ?></div>
                        </div>
                      </div>
                    <?php endif; ?>
                </div>

                <?php if ($hasCoords || $hasGpx): ?>
                    <section class="event-section">
                        <h2><i class="bi bi-pin-map"></i> Localisation</h2>
                        <div id="eventMap" class="event-map-canvas"></div>
                    </section>
                <?php endif; ?>
                </section>

                <?php if (!empty($routes)): ?>
                    <section class="event-section">
                        <h2><i class="bi bi-map"></i> Parcours disponibles</h2>

                        <?php foreach ($routesByCategory as $catName => $catRoutes): ?>
                            <div class="route-category-section">
                                <h3 class="category-title"><?= displayValue($catName) ?></h3>
                                <div class="routes-list">
                                    <?php foreach ($catRoutes as $route):
                                        $distanceValue = !empty($route['distance']) ? (float) $route['distance'] : null;
                                        $distance = $distanceValue !== null ? number_format($distanceValue, 1, ',', ' ') . ' km' : null;
                                        $elevationValue = isset($route['elevation']) && $route['elevation'] !== '' ? (int) $route['elevation'] : null;
                                        $elevation = $elevationValue !== null && $elevationValue !== 0 ? $elevationValue . ' m D+' : null;
                                        $priceValue = isset($route['price']) && $route['price'] !== '' ? (float) $route['price'] : null;
                                        $price = $priceValue !== null && $priceValue > 0 ? number_format($priceValue, 2, ',', ' ') . ' €' : null;
                                        $gpx = $route['gpx_file'] ?? null;

                                        if (!empty($route['name'])) {
                                            $routeName = displayValue($route['name']);
                                        } elseif ($distance) {
                                            $routeName = displayValue('Parcours ' . $distance);
                                        } else {
                                            $routeName = displayValue('Parcours');
                                        }
                                        $routeDesc = displayValue($route['description'] ?? '');
                                    ?>
                                        <div class="route-card">
                                            <h3><?= $routeName ?></h3>
                                            <div class="route-details">
                                                <?php if ($distance): ?>
                                                    <span><i class="bi bi-signpost-2"></i> <?= $distance ?></span>
                                                <?php endif; ?>
                                                <?php if ($elevation): ?>
                                                    <span><i class="bi bi-graph-up"></i> <?= $elevation ?></span>
                                                <?php endif; ?>
                                                <?php if ($price): ?>
                                                    <span><i class="bi bi-tag"></i> <?= $price ?></span>
                                                <?php endif; ?>
                                                <?php if ($gpx): ?>
                                                    <?php if (!empty($route['gpx_downloadable'])): ?>
                                                        <span class="gpx-badge"><i class="bi bi-download"></i> GPX</span>
                                                    <?php else: ?>
                                                        <span class="gpx-badge"><i class="bi bi-geo-alt"></i> GPX</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($routeDesc): ?>
                                                <div class="route-description"><?= nl2br($routeDesc) ?></div>
                                            <?php endif; ?>
                                            <?php if ($gpx): ?>
                                                <div class="route-actions">
                                                    <?php if (!empty($route['gpx_downloadable'])): ?>
                                                        <a href="<?= displayValue($gpx) ?>" class="btn btn-sm btn-outline-primary" download>
                                                            <i class="bi bi-download"></i> GPX
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showTrackOnMap('<?= displayValue($gpx) ?>')">
                                                        <i class="bi bi-eye"></i> Voir
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($hasGpx): ?>
                            <section class="event-section">
                                <h2><i class="bi bi-map"></i> Carte des parcours</h2>
                                <div id="routeMap" class="route-map-canvas"></div>
                            </section>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                

                <!-- <?php if (!empty($galleryImages)): ?>
                    <section class="event-section">
                        <h2><i class="bi bi-images"></i> Galerie photos</h2>
                        <div class="gallery-grid">
                            <?php foreach ($galleryImages as $img): ?>
                                <a href="<?= displayValue($img) ?>" data-fancybox="gallery" class="gallery-item">
                                    <img src="<?= displayValue($img) ?>" alt="Image de l'événement" class="img-fluid" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?> -->
            </main>

            <aside class="col-lg-4">
                <section class="event-section aside-actions">
                    <button class="btn-share w-100" type="button" data-share="event">
                        <i class="bi bi-share"></i><span>Partager cet événement</span>
                    </button>
                </section>

                <?php if ($organizer): ?>
                    <section class="event-section organizer-card">
                        <h2><i class="bi bi-person-circle"></i> Organisateur</h2>

                        <?php if (!empty($organizer['logo_path'])): ?>
                            <img src="<?= displayValue($organizer['logo_path']) ?>" alt="Logo organisateur" class="organizer-logo">
                        <?php endif; ?>

                        <?php if (!empty($organizer['name'])): ?>
                            <h3><?= displayValue($organizer['name']) ?></h3>
                        <?php endif; ?>

                        <?php if (!empty($organizer['description'])): ?>
                            <p><?= nl2br(displayValue($organizer['description'])) ?></p>
                        <?php endif; ?>

                        <div class="organizer-contact">
                            <?php if (!empty($organizer['email'])): ?>
                                <p><i class="bi bi-envelope"></i> <a href="mailto:<?= displayValue($organizer['email']) ?>"><?= displayValue($organizer['email']) ?></a></p>
                            <?php endif; ?>
                            <?php if (!empty($organizer['phone'])): ?>
                                <p><i class="bi bi-telephone"></i> <a href="tel:<?= displayValue($organizer['phone']) ?>"><?= displayValue($organizer['phone']) ?></a></p>
                            <?php endif; ?>
                            <?php if (!empty($organizer['website'])): ?>
                                <p><i class="bi bi-globe"></i> <a href="<?= displayValue($organizer['website']) ?>" target="_blank" rel="noopener"><?= displayValue($organizer['website']) ?></a></p>
                            <?php endif; ?>
                            <?php if (!empty($organizer['address'])): ?>
                                <p><i class="bi bi-geo-alt"></i> <?= displayValue($organizer['address']) ?></p>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($contacts)): ?>
                    <section class="event-section contacts-card">
                        <h2><i class="bi bi-telephone"></i> Contacts</h2>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="contact-item">
                                <?php if (!empty($contact['name'])): ?>
                                    <h4><?= displayValue($contact['name']) ?></h4>
                                <?php endif; ?>
                                <?php if (!empty($contact['email'])): ?>
                                    <p><i class="bi bi-envelope"></i> <a href="mailto:<?= displayValue($contact['email']) ?>"><?= displayValue($contact['email']) ?></a></p>
                                <?php endif; ?>
                                <?php if (!empty($contact['phone'])): ?>
                                    <p><i class="bi bi-telephone"></i> <a href="tel:<?= displayValue($contact['phone']) ?>"><?= displayValue($contact['phone']) ?></a></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                
                <?php if (!empty($galleryImages)): ?>
                    <section class="event-section aside-gallery">
                        <h2><i class="bi bi-images"></i> Galerie</h2>
                        <div class="aside-gallery-grid">
                            <?php foreach (array_slice($galleryImages, 0, 6) as $img): ?>
                                <a href="<?= displayValue($img) ?>" data-fancybox="aside-gallery" class="aside-gallery-item">
                                    <img src="<?= displayValue($img) ?>" alt="Image de l'événement" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </div>

</article>

<script src="/assets/js/events/event-display.js"></script>
