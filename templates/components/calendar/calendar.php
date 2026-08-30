<?php
function render_calendar($pdo) {
    ?>
    <!-- FullCalendar Dependencies -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <link href="/assets/css/vendor/fullcalendar.min.css" rel="stylesheet">

    <!-- Conteneur du calendrier -->
    <div class="calendar-container">
        <div id="calendar"></div>
    </div>

    <!-- Styles et Scripts -->
    <link rel="stylesheet" href="/assets/css/components/calendar.css">
    <script src="/assets/js/calendar.js"></script>
    <?php
}
?>