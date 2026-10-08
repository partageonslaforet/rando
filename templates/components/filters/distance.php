<?php
/**
 * localisation: templates/components/filters/distance.php
 * Role: Composant : Filtre distance (lieu de référence + rayon en km)
 * Usage: Affiche un rayon de X km autour d'un lieu sur la carte principale;
 *        la liste des événements est filtrée, les marqueurs hors rayon restent actifs.
 * Dépendances: window.currentFilters (filters.js), window.mapFunctions + distance.js
 */
function render_distance_filter() {
    ?>
    <div class="distance-filter" id="distanceFilter">
        <h3 class="distance-filter-title"><i class="bi bi-geo-alt" aria-hidden="true"></i> Distance</h3>
        <div class="distance-filter-row">
            <span class="distance-dot" aria-hidden="true"></span>
            <input type="text" id="distanceOrigin" class="form-control distance-origin"
                   placeholder="Choisissez un lieu ou cliquez sur la carte"
                   autocomplete="off" aria-label="Lieu de référence">
            <button type="button" id="distanceGeoloc" class="distance-geoloc"
                    title="Utiliser ma position" aria-label="Utiliser ma position">
                <i class="bi bi-crosshair"></i>
            </button>
            <button type="button" id="distanceClear" class="distance-clear"
                    title="Effacer le lieu" aria-label="Effacer le lieu" hidden>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="distance-filter-row distance-radius-row">
            <i class="bi bi-rulers" aria-hidden="true"></i>
            <input type="range" id="distanceRadius" class="distance-radius"
                   min="5" max="100" step="5" value="25" disabled
                   aria-label="Rayon en kilomètres" aria-describedby="distanceRadiusLabel">
            <output id="distanceRadiusLabel" class="distance-radius-label" for="distanceRadius">25 km</output>
        </div>
        <div id="distanceError" class="distance-error" role="alert" hidden></div>
    </div>
    <?php
}
?>
