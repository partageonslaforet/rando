<?php
if (!function_exists('render_events_list')) {
    function render_events_list() {
        static $scriptIncluded = false;
        ?>

        <!-- Conteneur principal des événements -->
        <div class="events-list" id="events-container">
            <header class="events-list-header" style="display: none;">
                <h2 class="events-list-title"></h2>
                <span class="events-list-count"></span>
            </header>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 events-list-grid">
                <!-- Les événements seront chargés dynamiquement -->
            </div>
        </div>

        <!-- Carte de résumé d'événement -->
        <template id="event-template">
            <div class="col">
                <a href="" class="summary-card-link" aria-label="Voir l'événement">
                    <article class="summary-card">
                        <div class="summary-card-image">
                            <img src="" alt="" loading="lazy">
                            <span class="summary-card-badge">
                                <i class="bi"></i>
                                <span class="badge-text"></span>
                            </span>
                        </div>
                        <div class="summary-card-body">
                            <h3 class="summary-card-title"></h3>
                            <div class="summary-card-meta">
                                <span class="meta-item meta-location">
                                    <i class="bi bi-geo-alt"></i>
                                    <span></span>
                                </span>
                                <span class="meta-item meta-time">
                                    <i class="bi bi-clock"></i>
                                    <span></span>
                                </span>
                                <span class="meta-item meta-distance" style="display: none;">
                                    <i class="bi bi-signpost"></i>
                                    <span></span>
                                </span>
                                <span class="meta-item meta-difficulty" style="display: none;">
                                    <i class="bi bi-bar-chart"></i>
                                    <span></span>
                                </span>
                            </div>
                            <span class="summary-card-action">
                                Voir l'événement <span aria-hidden="true">→</span>
                            </span>
                        </div>
                    </article>
                </a>
            </div>
        </template>

        <?php if (!$scriptIncluded): 
            $scriptIncluded = true;
        ?>
        <script>
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        function formatDay(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                weekday: 'long',
                day: 'numeric',
                month: 'long'
            });
        }

        function formatTime(timeString) {
            if (!timeString) return '';
            return timeString.split(':').slice(0, 2).join(':');
        }

        if (typeof window.eventListFunctions === 'undefined') {
            window.eventListFunctions = {
                updateEventsList: function(events) {
                const container = document.querySelector('#events-container .events-list-grid');
                const template = document.getElementById('event-template');
                const header = document.querySelector('.events-list-header');
                const title = document.querySelector('.events-list-title');
                const count = document.querySelector('.events-list-count');

                container.innerHTML = '';

                if (!events || events.length === 0) {
                    if (header) header.style.display = 'none';
                    container.innerHTML = '<div class="alert alert-info">Aucun événement trouvé</div>';
                    return;
                }

                if (header) {
                    header.style.display = 'flex';
                    title.textContent = `Événements du ${formatDay(events[0].date)}`;
                    count.textContent = `${events.length} événement${events.length > 1 ? 's' : ''}`;
                }

                const difficultyLabels = {
                    'easy': 'Facile',
                    'medium': 'Intermédiaire',
                    'hard': 'Difficile'
                };

                events.forEach(event => {
                    const eventElement = template.content.cloneNode(true);

                    // Image
                    const img = eventElement.querySelector('.summary-card-image img');
                    img.src = event.event_image
                        ? `/uploads/events/${event.event_image}`
                        : '/assets/images/events/default-event.jpg';
                    img.alt = event.title ? `Image de ${event.title}` : 'Image de l\'événement';

                    // Badge catégorie
                    if (event.category_name) {
                        const badge = eventElement.querySelector('.summary-card-badge');
                        const badgeIcon = badge.querySelector('i');
                        const badgeText = badge.querySelector('.badge-text');
                        badgeIcon.className = `bi ${event.category_icon || 'bi-tree'}`;
                        badgeText.textContent = event.category_name;
                    } else {
                        eventElement.querySelector('.summary-card-badge').style.display = 'none';
                    }

                    // Titre
                    eventElement.querySelector('.summary-card-title').textContent = event.title;

                    // Lieu
                    eventElement.querySelector('.meta-location span').textContent = event.location || event.venue || 'Lieu non précisé';

                    // Heure de départ
                    const timeEl = eventElement.querySelector('.meta-time span');
                    if (event.start_time) {
                        timeEl.textContent = formatTime(event.start_time);
                    } else {
                        eventElement.querySelector('.meta-time').style.display = 'none';
                    }

                    // Distance
                    const distanceEl = eventElement.querySelector('.meta-distance');
                    if (event.distance) {
                        distanceEl.querySelector('span').textContent = `${event.distance} km`;
                        distanceEl.style.display = 'inline-flex';
                    }

                    // Difficulté
                    const difficultyEl = eventElement.querySelector('.meta-difficulty');
                    if (event.difficulty) {
                        const label = difficultyLabels[event.difficulty] || event.difficulty.charAt(0).toUpperCase() + event.difficulty.slice(1);
                        difficultyEl.querySelector('span').textContent = label;
                        difficultyEl.style.display = 'inline-flex';
                    }

                    // Lien
                    const cardLink = eventElement.querySelector('.summary-card-link');
                    if (cardLink) {
                        cardLink.href = `https://rando.partageonslaforet.be/templates/events/event-detail.php?id=${event.id}`;
                    }

                    container.appendChild(eventElement);
                });

                },
            };
        }
        </script>
        <?php endif; ?>
        <?php
    }
}
?>