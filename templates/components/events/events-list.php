<?php
if (!function_exists('render_events_list')) {
    function render_events_list() {
        static $scriptIncluded = false;
        ?>

         <!-- Conteneur principal des événements -->
         <div class="events-list" id="events-container">
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <!-- Les événements seront chargés dynamiquement -->
            </div>
        </div>

        <!-- Template basé sur la structure HTML existante -->
        <template id="event-template">
            <div class="col">
                <a href="" class="event-card-link">
                    <div class="event-card">
                        <div class="event-image-wrapper">
                            <img src="" alt="" class="event-image">
                            <div class="event-category">
                                <i class="bi bi-tree"></i>
                                <span class="category-text"></span>
                            </div>
                        </div>
                        <div class="event-details">
                            <h3 class="event-title"></h3>
                            <div class="event-metadata">
                                <div class="event-date">
                                    <i class="bi bi-calendar"></i>
                                    <span></span>
                                </div>
                                <div class="event-registration">
                                    <i class="bi bi-clock"></i>
                                    <span>Inscriptions : </span>
                                    <span class="registration-time"></span>
                                </div>
                                <div class="event-location">
                                    <i class="bi bi-geo-alt"></i>
                                    <span></span>
                                </div>
                                <div class="event-venue">
                                    <i class="bi bi-pin-map"></i>
                                    <span></span>
                                </div>
                            </div>
                        </div>
                    </div>
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

        if (typeof window.eventListFunctions === 'undefined') {
            window.eventListFunctions = {
                updateEventsList: function(events) {
                const container = document.querySelector('#events-container .row');
                const template = document.getElementById('event-template');
                
                container.innerHTML = '';
                
                if (!events || events.length === 0) {
                    container.innerHTML = '<div class="alert alert-info">Aucun événement trouvé</div>';
                    return;
                }

                events.forEach(event => {
                    const eventElement = template.content.cloneNode(true);
                    
                    // Image
                    const img = eventElement.querySelector('.event-image');
                    img.src = event.event_image 
                        ? `/uploads/events/${event.event_image}`
                        : '/assets/images/events/default-event.jpg';
                    img.alt = event.title || 'Image de l\'événement';

                    // Titre
                    eventElement.querySelector('.event-title').textContent = event.title;
                    
                    // Date
                    eventElement.querySelector('.event-date span').textContent = formatDate(event.date);
                    
                    // Heures d'inscription
                    const registrationTime = eventElement.querySelector('.registration-time');
                    if (event.start_time && event.end_time) {
                        // Formatage des heures sans les secondes
                        const formatTime = (time) => {
                            return time.split(':').slice(0, 2).join('h');
                        };
                        registrationTime.textContent = `${formatTime(event.start_time)} à ${formatTime(event.end_time)}`;
                    }
                    
                    // Location
                    eventElement.querySelector('.event-location span').textContent = event.location;
                    
                    // Venue (adresse précise)
                    if (event.venue) {
                        eventElement.querySelector('.event-venue span').textContent = event.venue;
                    }
                    
                    // Catégorie
                    if (event.category_name) {
                        const categoryElement = eventElement.querySelector('.event-category');
                        categoryElement.innerHTML = `<i class="bi ${event.category_icon || 'bi-tree'}"></i> ${event.category_name}`;
                        if (event.category_color) {
                            categoryElement.style.backgroundColor = event.category_color;
                        }
                    }

                    eventElement.querySelector('.event-card-link').href = 
                        `https://rando.partageonslaforet.be/templates/events/event-detail.php?id=${event.id}`;

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