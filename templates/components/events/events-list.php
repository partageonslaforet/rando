<?php
/**
 * localisation: templates/components/events/events-list.php
 * Role: Composant : Events List
 * Usage: Rendu de la liste d evenements
 * Dépendances: Aucune
 */
if (!function_exists('render_events_list')) {
    function render_events_list() {
        static $scriptIncluded = false;
        ?>

        <!-- Conteneur principal des événements -->
        <div class="events-list" id="events-container">
            <header class="events-list-header" style="display: none;">
                <h2 class="events-list-title">
                    <span class="events-list-title-text">Liste des événements</span>
                    <span class="events-list-summary" id="events-summary" aria-live="polite"></span>
                </h2>
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
                    <div class="cancelled-sticker">
                        <span>ANNULÉ</span>
                    </div>
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
        // S'assurer que currentFilters existe avant tout rendu (premier paint)
        (function ensureGlobalFilters(){
            if (typeof window.currentFilters === 'undefined' || !window.currentFilters) {
                window.currentFilters = { period: 'upcoming', category: null, search: null };
            } else {
                if (!('period' in window.currentFilters) || window.currentFilters.period == null) window.currentFilters.period = 'upcoming';
                if (!('category' in window.currentFilters)) window.currentFilters.category = null;
                if (!('search' in window.currentFilters)) window.currentFilters.search = null;
            }
        })();
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

        // Résumé des filtres sous forme de chips à côté du titre
        function escapeHtml(s) {
            return String(s || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        // Cache catégories -> { id: {id,name,icon,...} }
        window.__categoriesMap = window.__categoriesMap || null;
        async function ensureCategoriesMap() {
            if (window.__categoriesMap) return window.__categoriesMap;
            if (window.EventsAPI && typeof window.EventsAPI.getCategories === 'function') {
                try {
                    const cats = await window.EventsAPI.getCategories();
                    const map = {};
                    (cats || []).forEach(c => { map[String(c.id)] = c; });
                    window.__categoriesMap = map;
                    return map;
                } catch (e) { /* ignore */ }
            }
            window.__categoriesMap = {};
            return window.__categoriesMap;
        }

        function buildFiltersSummaryParts() {
            const cf = window.currentFilters || {};
            const parts = [];
            const periodMap = { upcoming: 'À venir', today: "Aujourd'hui", past: 'Passés' };

            // Période
            if (cf.period && periodMap[cf.period]) parts.push({ type: 'period', label: periodMap[cf.period] });

            // Mois/année (calendrier)
            if (cf.calendarMonth && cf.calendarYear) {
                try {
                    const dt = new Date(cf.calendarYear, cf.calendarMonth - 1, 1);
                    const label = dt.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
                    parts.push({ type: 'date', label: label.charAt(0).toUpperCase() + label.slice(1) });
                } catch (e) {}
            }

            // Catégorie (libellé sans pastille)
            if (cf.category) {
                let catLabel = cf.category;
                const btn = document.querySelector(`[data-category="${cf.category}"]`);
                if (btn) {
                    const clone = btn.cloneNode(true);
                    const badge = clone.querySelector('.badge');
                    if (badge) badge.remove();
                    catLabel = clone.textContent.trim().replace(/\s+/g, ' ');
                } else if (window.mapFunctions && typeof window.mapFunctions.getCategoryLabel === 'function') {
                    catLabel = window.mapFunctions.getCategoryLabel(cf.category);
                }
                parts.push({ type: 'category', label: catLabel, id: String(cf.category) });
            }

            // Terme de recherche
            if (cf.search) parts.push({ type: 'search', label: `“${cf.search}”` });

            return parts;
        }

        function renderFiltersSummary() {
            const el = document.getElementById('events-summary');
            if (!el) return;
            const parts = buildFiltersSummaryParts();
            if (!parts.length) { el.innerHTML = ''; return; }
            const iconFor = (p) => {
                if (p.type === 'period') return 'bi-calendar-event';
                if (p.type === 'date') return 'bi-calendar3';
                if (p.type === 'search') return 'bi-search';
                if (p.type === 'category') {
                    const map = window.__categoriesMap || {};
                    const entry = map && map[p.id];
                    const ic = entry && entry.icon ? String(entry.icon) : '';
                    if (ic && (ic.startsWith('bi-') || ic.startsWith('fa'))) return ic;
                    // Fallbacks basés sur le libellé de la catégorie
                    const lbl = (p.label || '').toLowerCase();
                    if (/(marche|rando|randonn|hiking|walk)/.test(lbl)) return 'bi-person-walking';
                    if (/(vtt|vélo|velo|cycl|bike)/.test(lbl)) return 'bi-bicycle';
                    if (/(course|running|trail|jog|run)/.test(lbl)) return 'bi-person-running';
                    return 'bi-tag';
                }
                return '';
            };

            // Rendu chips
            el.innerHTML = parts.map(p => (
                `<span class="summary-chip summary-chip--${p.type}" title="${escapeHtml(p.label)}">`
                + `<i class="${iconFor(p)}" aria-hidden="true"></i>`
                + `<span>${escapeHtml(p.label)}</span>`
                + `</span>`
            )).join('');

            // Titre reste statique
            const titleText = document.querySelector('.events-list-title-text');
            if (titleText) {
                titleText.textContent = 'Liste des événements';
            }

            // Charger les icônes catégorie si nécessaire puis re-render
            if (parts.some(p => p.type === 'category') && !window.__categoriesMap) {
                ensureCategoriesMap().then(() => {
                    // Re-rendu après chargement des icônes catégories
                    try { renderFiltersSummary(); } catch (e) {}
                });
            }
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
                    if (title) {
                        // Le texte de base reste dans le HTML; nous ajoutons seulement le résumé dynamique
                        renderFiltersSummary();
                    }
                    count.textContent = `${events.length} événement${events.length > 1 ? 's' : ''}`;
                }

                const difficultyLabels = {
                    'easy': 'Facile',
                    'medium': 'Intermédiaire',
                    'hard': 'Difficile'
                };

                // Regrouper par jour (clé YYYY-MM-DD)
                const byDate = events.reduce((acc, e) => {
                    const key = String(e.date).split(/[T ]/)[0];
                    (acc[key] || (acc[key] = [])).push(e);
                    return acc;
                }, {});

                // Trier les dates croissantes
                const sortedDays = Object.keys(byDate).sort();

                // Vider puis insérer sections par jour et leurs cartes
                container.innerHTML = '';

                sortedDays.forEach(dayKey => {
                    const sectionHeader = document.createElement('div');
                    sectionHeader.className = 'col-12 day-section';
                    const h = document.createElement('h3');
                    h.className = 'events-day-title';
                    h.textContent = formatDay(dayKey);
                    sectionHeader.appendChild(h);
                    container.appendChild(sectionHeader);

                    byDate[dayKey].forEach(event => {
                        const eventElement = template.content.cloneNode(true);

                        const summaryCard = eventElement.querySelector('.summary-card');
                        console.warn('[events-list]', event.id, event.title, 'is_cancelled=', event.is_cancelled);
                        if (summaryCard && /^(1|t|true|yes|on)$/i.test(String(event.is_cancelled))) {
                            console.warn('[events-list] ajout is-cancelled pour', event.id);
                            summaryCard.classList.add('is-cancelled');
                        }

                        const img = eventElement.querySelector('.summary-card-image img');
                        const placeholderSvg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0%25' stop-color='%23a8d5a2'/%3E%3Cstop offset='100%25' stop-color='%235d8c5f'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='400' height='300' fill='url(%23g)'/%3E%3C/svg%3E";
                        img.src = event.main_image_path || event.main_image || (event.event_image ? `/uploads/events/${event.event_image}` : placeholderSvg);
                        img.onerror = function() { this.onerror = null; this.src = placeholderSvg; };
                        img.alt = event.title ? `Image de ${event.title}` : 'Image de l\'événement';

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

                        const dateEl = eventElement.querySelector('.meta-date span');
                        if (dateEl) dateEl.textContent = formatDate(event.date);

                        eventElement.querySelector('.summary-card-title').textContent = event.title;

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

                        const imageLink = eventElement.querySelector('.summary-card-image');
                        if (imageLink) {
                            imageLink.href = img.src;
                        }

                        const bodyLink = eventElement.querySelector('.summary-card-body');
                        if (bodyLink) {
                            bodyLink.href = `/event?id=${event.id}`;
                        }

                        container.appendChild(eventElement);
                    });
                });

                },
                // Rendu de la pagination (Option B)
                renderPagination: function(pagination, onPageChange) {
                    try {
                        let nav = document.getElementById('events-pagination');
                        const container = document.querySelector('#events-container');
                        if (!container) return;
                        if (!nav) {
                            nav = document.createElement('nav');
                            nav.id = 'events-pagination';
                            nav.className = 'mt-3';
                            container.appendChild(nav);
                        }
                        nav.innerHTML = '';
                        if (!pagination || !pagination.total_pages || pagination.total_pages <= 1) return;

                        const ul = document.createElement('ul');
                        ul.className = 'pagination justify-content-center';

                        const addItem = (label, page, disabled=false, active=false) => {
                            const li = document.createElement('li');
                            li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
                            const a = document.createElement('a');
                            a.className = 'page-link';
                            a.href = '#';
                            a.textContent = label;
                            if (!disabled && !active) {
                                a.addEventListener('click', function(e) { e.preventDefault(); onPageChange(page); });
                            }
                            li.appendChild(a);
                            ul.appendChild(li);
                        };

                        const max = pagination.total_pages;
                        addItem('«', Math.max(1, pagination.page - 1), pagination.page === 1);
                        const start = Math.max(1, pagination.page - 2);
                        const end = Math.min(max, pagination.page + 2);
                        for (let p = start; p <= end; p++) {
                            addItem(String(p), p, false, p === pagination.page);
                        }
                        addItem('»', Math.min(max, pagination.page + 1), pagination.page === max);
                        nav.appendChild(ul);
                    } catch (e) {
                        console.warn('renderPagination error:', e);
                    }
                },
                // Contrôle "Par page" en bas de la liste
                renderPerPageControl: function(currentPerPage, onChange) {
                    try {
                        const container = document.querySelector('#events-container');
                        if (!container) return;
                        let wrapper = document.getElementById('events-per-page-wrapper');
                        if (!wrapper) {
                            wrapper = document.createElement('div');
                            wrapper.id = 'events-per-page-wrapper';
                            wrapper.className = 'events-page-size text-center mt-2 mb-3';
                            const label = document.createElement('label');
                            label.setAttribute('for', 'events-per-page');
                            label.className = 'form-label mb-0 me-2';
                            label.textContent = 'Par page';
                            const select = document.createElement('select');
                            select.id = 'events-per-page';
                            select.className = 'form-select form-select-sm d-inline-block';
                            ['5','10','15','20'].forEach(v => {
                                const opt = document.createElement('option');
                                opt.value = v;
                                opt.textContent = v;
                                select.appendChild(opt);
                            });
                            wrapper.appendChild(label);
                            wrapper.appendChild(select);
                            container.appendChild(wrapper);
                        }
                        const selectEl = wrapper.querySelector('#events-per-page');
                        if (selectEl) {
                            if (String(selectEl.value) !== String(currentPerPage)) {
                                selectEl.value = String(currentPerPage);
                            }
                            selectEl.onchange = function(e) {
                                const v = parseInt(e.target.value, 10);
                                if (!Number.isNaN(v) && v > 0) onChange(v);
                            };
                        }
                    } catch (e) {
                        console.warn('renderPerPageControl error:', e);
                    }
                }
            };
        }
        </script>
        <?php endif; ?>
        <?php
    }
}
?>