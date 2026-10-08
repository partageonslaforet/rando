<?php
/**
 * localisation: templates/events/event-display.php
 * Role: Template evenement : Event Display
 * Usage: Template partage d affichage d un evenement (publie ou brouillon)
 * Dépendances: Aucune
 */

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

function displayValue(mixed $value): string
{
    return htmlspecialchars((string) $value);
}

function attrValue(mixed $value): string
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
        <div class="event-hero-bg" style="--hero-image: url('<?= htmlspecialchars($mainImage) ?>');"></div>

        <!-- Ligne 1 : bouton Partager, ancré en haut à droite du hero (indépendant du contenu) -->
        <button id="shareBtn" class="ehm-share btn-share hero-share-btn" type="button" aria-label="Partager l'événement">
            <i class="bi bi-share"></i>
        </button>

        <!-- Bouton Retour aux événements, en bas à droite du hero -->
        <a href="/" class="ehm-share btn-share hero-back-btn" aria-label="Retour aux événements">
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
                <?php 
                    $viewsToShow = isset($event['views_total']) && $event['views_total'] !== null
                        ? (int)$event['views_total']
                        : (int)($event['view_count'] ?? 0);
                ?>
                <span class="pill ehm-view-count"><i class="bi bi-eye"></i><?= $viewsToShow ?> vue<?= $viewsToShow > 1 ? 's' : '' ?></span>
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

            <!-- Ligne 4 : catégories / tags -->
            <?php if (!empty($categoryList)): ?>
                <div class="ehm-chips">
                    <?php foreach ($categoryList as $cat): ?>
                        <span class="chip"><i class="<?= (strpos($cat['icon'] ?? '', 'bi-') === 0 ? 'bi ' . $cat['icon'] : 'bi bi-tree') ?>"></i><?= displayValue($cat['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


        </div>

        <!-- Barre réduite affichée quand le hero est fermé -->
        <div class="event-hero-collapsed" aria-hidden="true">
            <a href="/" class="btn-share hero-back-link" aria-label="Retour aux événements">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="hero-collapsed-center">
                <span class="hero-collapsed-title"><?= displayValue($event['title'] ?? 'Événement') ?></span>
                <?php 
                    $viewsToShow2 = isset($event['views_total']) && $event['views_total'] !== null
                        ? (int)$event['views_total']
                        : (int)($event['view_count'] ?? 0);
                ?>
                <span class="pill"><i class="bi bi-eye"></i><?= $viewsToShow2 ?> vue<?= $viewsToShow2 > 1 ? 's' : '' ?></span>
            </div>
            <button type="button" class="btn-share hero-reopen-btn" aria-label="Ré-ouvrir le bandeau">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </header>

    <div class="event-topbar" id="eventTopbar">
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
                <?php if (filter_var($event['is_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN)): ?>
                    <div class="event-cancelled-banner" role="alert">
                        <i class="bi bi-x-circle-fill"></i>
                        <span>Événement annulé</span>
                        <?php if (!empty($event['cancellation_reason'])): ?>
                            <p class="cancelled-reason">Motif : <?= displayValue($event['cancellation_reason']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

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

                    <?php if (!empty($event['start_time']) || (!empty($event['end_time']) && $event['end_time'] !== '00:00:00')): ?>
                      <div class="info-times">
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
<!-- Lightbox Modal -->
<div id="image-lightbox" class="lightbox-modal is-hidden">
  <div class="lightbox-backdrop"></div>
  <img src="" alt="Image en grand" class="lightbox-img" />
  <button class="lightbox-close" aria-label="Fermer">&times;</button>
</div>

            <aside class="col-lg-4">
                <section class="event-section aside-actions">
                    <div class="action-bar">
                        <button class="btn-share" type="button" data-share="event" title="Partager cet événement">
                            <i class="bi bi-share"></i><span>Partager</span>
                        </button>
                        <a class="btn-ics" href="/api/events/ics.php?id=<?= (int)$event['id'] ?>"
                           title="Ajouter à mon calendrier" aria-label="Ajouter cet événement à mon calendrier">
                            <i class="bi bi-calendar-plus"></i><span>Calendrier</span>
                        </a>
                        <button class="btn-subscribe" type="button" data-bs-toggle="modal" data-bs-target="#subscribersModal" aria-controls="subscribersModal" aria-haspopup="dialog" title="S'abonner aux événements">
                            <i class="bi bi-bell"></i><span>S'abonner</span>
                        </button>
                    </div>
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

                
                <?php if ($hasCoords): ?>
                    <section class="event-section itinerary-card">
                        <h2><i class="bi bi-signpost-2"></i> Itinéraire</h2>

                        <div class="itinerary-form">
                            <div class="itinerary-row">
                                <span class="itinerary-dot itinerary-dot-start" aria-hidden="true"></span>
                                <input type="text" id="itineraryStart" class="form-control"
                                       placeholder="Point de départ"
                                       autocomplete="off" aria-label="Point de départ">
                                <button type="button" id="itineraryGeoloc" class="itinerary-geoloc"
                                        title="Votre position" aria-label="Utiliser ma position">
                                    <i class="bi bi-crosshair"></i>
                                </button>
                                <button type="button" id="itineraryReset" class="itinerary-geoloc itinerary-reset"
                                        title="Effacer le point de départ" aria-label="Effacer le point de départ" hidden>
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <div class="itinerary-row">
                                <i class="bi bi-geo-alt-fill itinerary-dot itinerary-dot-end" aria-hidden="true"></i>
                                <input type="text" class="form-control" readonly
                                       value="<?= attrValue($event['venue'] ?: $event['location']) ?>"
                                       aria-label="Destination — lieu de rendez-vous">
                            </div>
                        </div>

                        <div class="itinerary-profiles" role="group" aria-label="Mode de déplacement">
                            <button type="button" class="itinerary-profile active" data-profile="driving" title="En voiture" aria-label="En voiture">
                                <i class="bi bi-car-front"></i>
                            </button>
                            <button type="button" class="itinerary-profile" data-profile="cycling" title="À vélo" aria-label="À vélo">
                                <i class="bi bi-bicycle"></i>
                            </button>
                            <button type="button" class="itinerary-profile" data-profile="walking" title="À pied" aria-label="À pied">
                                <i class="bi bi-person-walking"></i>
                            </button>
                            <span id="itinerarySummary" class="itinerary-summary" aria-live="polite"></span>
                        </div>

                        <div id="itineraryError" class="itinerary-error" role="alert" hidden></div>

                        <div id="itineraryMap" class="itinerary-map" aria-label="Carte de l'itinéraire" hidden></div>
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

                <?php
                $sponsors = getActiveSponsors(function_exists('getConnection') ? getConnection() : null);
                if (!empty($sponsors)):
                ?>
                    <section class="event-section sponsor-card">
                        <h2><i class="bi bi-megaphone"></i> Nos partenaires</h2>
                        <div class="sponsor-list">
                            <?php foreach ($sponsors as $ad): ?>
                                <?php if (!empty($ad['link_url'])): ?>
                                    <a href="<?= displayValue($ad['link_url']) ?>" target="_blank" rel="noopener sponsored" class="sponsor-item">
                                        <img src="<?= displayValue($ad['image_path']) ?>" alt="<?= displayValue($ad['alt_text'] ?: $ad['name']) ?>" loading="lazy">
                                    </a>
                                <?php else: ?>
                                    <span class="sponsor-item">
                                        <img src="<?= displayValue($ad['image_path']) ?>" alt="<?= displayValue($ad['alt_text'] ?: $ad['name']) ?>" loading="lazy">
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </div>

</article>

<script src="/assets/js/events/event-display.js?v=<?= @filemtime((defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/public/assets/js/events/event-display.js') ?: 1 ?>"></script>
<script src="/assets/js/events/event-itinerary.js?v=<?= @filemtime((defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/public/assets/js/events/event-itinerary.js') ?: 1 ?>"></script>
