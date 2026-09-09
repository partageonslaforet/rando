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

  // Filtre avec cas 'expired' (uniquement approuvés ET échus)
  function applyFilter(filter) {
    const rows = document.querySelectorAll('tr[data-status]');
    rows.forEach(row => {
      const status = row.getAttribute('data-status');
      const expired = row.getAttribute('data-expired') === '1';
      let show = true;

      if (filter === 'all') {
        show = true;
      } else if (filter === 'expired') {
        show = (status === 'approved' && expired === true);
      } else {
        show = (status === filter);
      }

      row.style.display = show ? '' : 'none';
    });

    setActivePill(filter);
  }

  function applySearch(q) {
    const qn = (q || '').trim().toLowerCase();
    const rows = document.querySelectorAll('tr[data-title][data-org]');
    rows.forEach(row => {
      const t = (row.getAttribute('data-title') || '').toLowerCase();
      const o = (row.getAttribute('data-org') || '').toLowerCase();
      row.style.display = (t.includes(qn) || o.includes(qn)) ? '' : 'none';
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

    // Sidebar
    document.querySelectorAll('.dash-sidenav .nav-link[data-target]').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        activateSection(link.getAttribute('data-target'));
      });
    });

    // Par défaut
    activateSection('section-events');
    applyFilter('all');
  });
})();
