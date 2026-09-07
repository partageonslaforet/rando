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
            <div class="row row-cols-1 g-3 events-list-grid">
                <!-- Les événements seront chargés dynamiquement -->
            </div>
        </div>

        <!-- Carte de résumé d'événement -->
        <template id="event-template">
            <div class="col">
                <div class="summary-card">
                    <a href="" class="summary-card-image" target="_blank" rel="noopener" aria-label="Afficher l'image">
                        <img src="" alt="" loading="lazy">
                    </a>
                    <a href="" class="summary-card-body" aria-label="Voir l'événement">
                        <h3 class="summary-card-title"></h3>
                            <!-- Ligne 1: Lieu | Adresse (à droite) -->
                            <div class="summary-card-meta line-1">
                                <span class="meta-item meta-location">
                                    <i class="bi bi-geo-alt"></i>
                                    <span></span>
                                </span>
                                <span class="meta-item meta-address">
                                    <i class="bi bi-pin-map"></i>
                                    <span></span>
                                </span>
                            </div>

                            <!-- Ligne 2: Date | Heure départ | Heure fin -->
                            <div class="summary-card-meta line-2">
                                <span class="meta-item meta-date">
                                    <i class="bi bi-calendar3"></i>
                                    <span></span>
                                </span>
                                <span class="meta-item meta-time">
                                    <i class="bi bi-clock"></i>
                                    <span></span>
                                </span>
                                <span class="meta-item meta-end-time" style="display: none;">
                                    <i class="bi bi-clock-history"></i>
                                    <span></span>
                                </span>
                            </div>

                            <!-- Footer: Tag catégorie | Voir l'événement -->
                            <div class="summary-card-footer">
                                <div class="meta-categories"></div>
                                <span class="summary-card-action">
                                    Voir l'événement <span aria-hidden="true">→</span>
                                </span>
                            </div>
                    </a>
                </div>
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
                    const placeholderSvg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0%25' stop-color='%23a8d5a2'/%3E%3Cstop offset='100%25' stop-color='%235d8c5f'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='400' height='300' fill='url(%23g)'/%3E%3C/svg%3E";
                    img.src = event.main_image_path || event.main_image || (event.event_image ? `/uploads/events/${event.event_image}` : placeholderSvg);
                    img.onerror = function() { this.onerror = null; this.src = placeholderSvg; };
                    img.alt = event.title ? `Image de ${event.title}` : 'Image de l\'événement';

                    // Ligne 1: Lieu & Adresse (venue)
                    const locEl = eventElement.querySelector('.meta-location span');
                    if (locEl) locEl.textContent = event.location || 'Lieu non précisé';
                    const addrWrap = eventElement.querySelector('.meta-address');
                    if (addrWrap) {
                        const addrSpan = addrWrap.querySelector('span');
                        if (event.venue) {
                            addrWrap.style.display = 'inline-flex';
                            addrSpan.textContent = event.venue;
                        } else {
                            addrWrap.style.display = 'none';
                        }
                    }

                    // Ligne 2: Date
                    const dateEl = eventElement.querySelector('.meta-date span');
                    if (dateEl) dateEl.textContent = formatDate(event.date);

                    // Titre
                    eventElement.querySelector('.summary-card-title').textContent = event.title;

                    // Heures départ/fin (sur la même ligne que lieu/date)
                    const timeWrap = eventElement.querySelector('.meta-time');
                    const timeEl = timeWrap ? timeWrap.querySelector('span') : null;
                    if (timeWrap && event.start_time) {
                        timeWrap.style.display = 'inline-flex';
                        if (timeEl) timeEl.textContent = formatTime(event.start_time);
                    } else if (timeWrap) {
                        timeWrap.style.display = 'none';
                    }

                    const endTimeWrap = eventElement.querySelector('.meta-end-time');
                    if (event.end_time) {
                        endTimeWrap.style.display = 'inline-flex';
                        endTimeWrap.querySelector('span').textContent = formatTime(event.end_time);
                    } else if (endTimeWrap) {
                        endTimeWrap.style.display = 'none';
                    }

                    // Footer: plusieurs tags catégories à gauche de l'action
                    const catsWrap = eventElement.querySelector('.summary-card-footer .meta-categories');
                    if (catsWrap) {
                        catsWrap.innerHTML = '';
                        const iconFallback = { hiking: 'bi-person-walking', running: 'bi-person-walking', cycling: 'bi-bicycle' };
                        if (Array.isArray(event.categories) && event.categories.length > 0) {
                            event.categories.forEach(cat => {
                                const tag = document.createElement('span');
                                tag.className = 'meta-item meta-category';
                                const i = document.createElement('i');
                                const inferKey = (cat.name || '').toLowerCase();
                                let iconClass = '';
                                if (cat.icon) {
                                    if (cat.icon.startsWith('fa')) {
                                        // Font Awesome 6: ajouter le style par défaut si absent
                                        iconClass = (cat.icon.includes('fa-') && !cat.icon.includes('fa-solid') && !cat.icon.startsWith('fas '))
                                            ? `fa-solid ${cat.icon}`
                                            : cat.icon;
                                    } else if (cat.icon.startsWith('bi-')) {
                                        iconClass = `bi ${cat.icon}`;
                                    }
                                }
                                if (!iconClass) {
                                    const fb = iconFallback[inferKey] || 'bi-tree';
                                    iconClass = fb.startsWith('bi-') ? `bi ${fb}` : fb;
                                }
                                i.className = iconClass;
                                const text = document.createElement('span');
                                text.className = 'badge-text';
                                text.textContent = cat.name || '';
                                tag.appendChild(i);
                                tag.appendChild(text);
                                catsWrap.appendChild(tag);
                            });
                        } else if (event.category_name) {
                            // Rétrocompatibilité: un seul tag si pas de tableau fourni
                            const tag = document.createElement('span');
                            tag.className = 'meta-item meta-category';
                            const i = document.createElement('i');
                            let iconClass = '';
                            if (event.category_icon) {
                                if (event.category_icon.startsWith('fa')) {
                                    iconClass = (event.category_icon.includes('fa-') && !event.category_icon.includes('fa-solid') && !event.category_icon.startsWith('fas '))
                                        ? `fa-solid ${event.category_icon}`
                                        : event.category_icon;
                                } else if (event.category_icon.startsWith('bi-')) {
                                    iconClass = `bi ${event.category_icon}`;
                                }
                            }
                            if (!iconClass) {
                                const fallback = (event.category && iconFallback[event.category]) ? iconFallback[event.category] : 'bi-tree';
                                iconClass = fallback.startsWith('bi-') ? `bi ${fallback}` : fallback;
                            }
                            i.className = iconClass;
                            const text = document.createElement('span');
                            text.className = 'badge-text';
                            text.textContent = event.category_name;
                            tag.appendChild(i);
                            tag.appendChild(text);
                            catsWrap.appendChild(tag);
                        }
                    }


                    // Lien vers l'image (ouvre l'image)
                    const imageLink = eventElement.querySelector('.summary-card-image');
                    if (imageLink) {
                        imageLink.href = img.src;
                    }

                    // Lien vers la page événement (ouvre le détail)
                    const bodyLink = eventElement.querySelector('.summary-card-body');
                    if (bodyLink) {
                        bodyLink.href = `/event?id=${event.id}`;
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