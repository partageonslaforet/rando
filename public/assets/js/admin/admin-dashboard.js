/**
 * Fichier: /assets/js/admin/admin-dashboard.js
 * Rôle: UI du dashboard admin (pills de filtre, recherche dans le tableau, navigation de sections).
 * Utilisation: /pages/admin/dashboard.php.
 * Dépendances: DOM (tableau événements), éventuellement SortableJS, Bootstrap.
 */
(function () {
  'use strict';

  function setActivePill(status) {
    document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
    const active = document.querySelector(`.pill[data-filter="${status}"]`);
    if (active) active.classList.add('active');
  }

  let currentFilter = 'all';

  // data-status porte le statut dérivé côté PHP (expired = statut DB ou approuvé échu)
  function applyFilter(filter) {
    currentFilter = filter;
    const rows = document.querySelectorAll('tr[data-status]');
    let visible = 0;

    rows.forEach(row => {
      const status = row.getAttribute('data-status');
      const show = (filter === 'all') || (status === filter);
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    const emptyRow = document.getElementById('filterEmptyRow');
    if (emptyRow) emptyRow.classList.toggle('d-none', visible > 0);

    setActivePill(filter);
    // Ré-applique la recherche éventuelle sur le sous-ensemble filtré
    const search = document.getElementById('dashSearch');
    if (search && (search.value || '').trim() !== '') applySearch(search.value);
  }

  function applySearch(q) {
    const qn = (q || '').trim().toLowerCase();
    const rows = document.querySelectorAll('tr[data-title][data-org]');
    let visible = 0;
    rows.forEach(row => {
      const status = row.getAttribute('data-status');
      const matchesFilter = (currentFilter === 'all') || (status === currentFilter);
      const t = (row.getAttribute('data-title') || '').toLowerCase();
      const o = (row.getAttribute('data-org') || '').toLowerCase();
      const show = matchesFilter && (qn === '' || t.includes(qn) || o.includes(qn));
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    const emptyRow = document.getElementById('filterEmptyRow');
    if (emptyRow) emptyRow.classList.toggle('d-none', visible > 0);
  }

  // Tri des colonnes du tableau « Événements récents »
  function sortEventsTable(th) {
    const key = th.getAttribute('data-sort');
    if (!key) return;
    const dir = th.getAttribute('data-sort-dir') === 'desc' ? 'asc' : 'desc';
    th.setAttribute('data-sort-dir', dir);

    // Indicateur visuel : une seule colonne active à la fois
    document.querySelectorAll('th.sortable').forEach(h => {
      h.classList.remove('sorted');
      const icon = h.querySelector('.sort-icon');
      if (icon) icon.className = 'bi bi-arrow-down-up sort-icon';
    });
    th.classList.add('sorted');
    const icon = th.querySelector('.sort-icon');
    if (icon) icon.className = 'bi sort-icon ' + (dir === 'asc' ? 'bi-sort-down' : 'bi-sort-up');

    const tbody = th.closest('table').querySelector('tbody');
    if (!tbody) return;

    const getters = {
      date:   r => parseInt(r.getAttribute('data-date') || '0', 10),
      title:  r => (r.getAttribute('data-title') || '').toLowerCase(),
      org:    r => (r.getAttribute('data-org') || '').toLowerCase(),
      status: r => (r.getAttribute('data-status') || ''),
      fb:     r => parseInt(r.getAttribute('data-fb') || '0', 10)
    };
    const get = getters[key];
    if (!get) return;

    const rows = Array.from(tbody.querySelectorAll('tr[data-status]'));
    rows.sort((a, b) => {
      const va = get(a), vb = get(b);
      const cmp = (typeof va === 'number') ? va - vb : String(va).localeCompare(String(vb), 'fr');
      return dir === 'asc' ? cmp : -cmp;
    });
    // Ré-insérer dans l'ordre — les lignes spéciales (vide/filtre) restent en queue
    rows.forEach(r => tbody.appendChild(r));
  }

  // Filtrage combiné type + recherche pour « Emails envoyés »
  function applySentEmailsFilter() {
    const table = document.getElementById('sentEmailsTable');
    if (!table) return;
    const typeSel = document.getElementById('sentEmailsTypeFilter');
    const searchInp = document.getElementById('sentEmailsSearch');
    const type = typeSel ? typeSel.value : '';
    const q = ((searchInp && searchInp.value) || '').trim().toLowerCase();

    table.querySelectorAll('tbody tr[data-type]').forEach(row => {
      const matchType = type === '' || row.getAttribute('data-type') === type;
      const matchSearch = q === '' || (row.getAttribute('data-search') || '').includes(q);
      row.style.display = (matchType && matchSearch) ? '' : 'none';
    });
  }

  // Sidebar: bascule entre sections
  function activateSection(targetId) {
    document.querySelectorAll('[data-section]').forEach(sec => sec.classList.add('d-none'));
    const tgt = document.getElementById(targetId);
    if (tgt) tgt.classList.remove('d-none');

    document.querySelectorAll('.dash-sidenav .nav-link').forEach(a => a.classList.remove('active'));
    const current = document.querySelector(`.dash-sidenav .nav-link[data-target="${targetId}"]`);
    if (current) current.classList.add('active');
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Filtres pills
    document.querySelectorAll('.pill[data-filter]').forEach(pill => {
      pill.addEventListener('click', () => applyFilter(pill.getAttribute('data-filter')));
    });

    // Recherche
    const search = document.getElementById('dashSearch');
    if (search) search.addEventListener('input', () => applySearch(search.value || ''));

    // Tri des colonnes
    document.querySelectorAll('th.sortable[data-sort]').forEach(th => {
      th.addEventListener('click', () => sortEventsTable(th));
    });

    // Filtres « Emails envoyés »
    const sentTypeFilter = document.getElementById('sentEmailsTypeFilter');
    if (sentTypeFilter) sentTypeFilter.addEventListener('change', applySentEmailsFilter);
    const sentSearch = document.getElementById('sentEmailsSearch');
    if (sentSearch) sentSearch.addEventListener('input', applySentEmailsFilter);

    // Sidebar
    document.querySelectorAll('.dash-sidenav .nav-link[data-target]').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        activateSection(link.getAttribute('data-target'));
      });
    });

    // Section initiale : hash (#section-...) prioritaire, puis ?tab=..., sinon Événements
    const hash = (window.location.hash || '').replace('#', '');
    const tabParam = new URLSearchParams(window.location.search).get('tab');
    const initial = document.getElementById(hash) ? hash
        : (tabParam && document.getElementById('section-' + tabParam) ? 'section-' + tabParam
        : 'section-events');
    activateSection(initial);
    applyFilter('all');
  });
})();
