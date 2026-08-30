<?php
function render_temporal_filters($pdo) {
    ?>
    <div class="temporal-filter-group" role="group" aria-label="Filtres temporels">
        <button type="button" class="btn btn-primary" data-period="upcoming">
            À venir
            <span class="badge">0</span>
        </button>
        <button type="button" class="btn btn-primary" data-period="today">
            Aujourd'hui
            <span class="badge">0</span>
        </button>
        <button type="button" class="btn btn-primary" data-period="past">
            Passés
            <span class="badge">0</span>
        </button>
    </div>
    <?php
}
?>