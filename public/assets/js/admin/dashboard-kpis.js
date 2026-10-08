/**
 * Gestion des tuiles KPI cliquables, modals, tri et actions.
 */
(function () {
  'use strict';

  const modalEl = document.getElementById('kpiModal');
  let modal = null;
  if (modalEl) {
    // eslint-disable-next-line no-undef
    modal = new bootstrap.Modal(modalEl);
  }

  function renderOrganizations(rows) {
    const sorted = sortRows(rows, sort.field || 'name', sort.asc);
    const pageSize = 10;
    const total = sorted.length;
    const pages = Math.max(1, Math.ceil(total / pageSize));
    if (typeof window._orgPage === 'undefined') window._orgPage = 1;
    const page = Math.min(Math.max(1, window._orgPage), pages);
    window._orgPage = page;
    const start = (page - 1) * pageSize;
    const pageRows = sorted.slice(start, start + pageSize);

    let html = '<table class="table stats-table align-middle kpi-table-orgs"><colgroup><col><col><col><col class="kpi-col-phone"><col><col><col></colgroup><thead><tr>';
    html += '<th data-sort="name" class="col-nowrap">Nom ' + sortIcon('name') + '</th>';
    html += '<th data-sort="email" class="col-nowrap">Email ' + sortIcon('email') + '</th>';
    html += '<th data-sort="owner_name" class="col-nowrap">Propriétaire ' + sortIcon('owner_name') + '</th>';
    html += '<th data-sort="phone">Téléphone ' + sortIcon('phone') + '</th>';
    html += '<th data-sort="live_events_count" class="col-events-narrow text-end">Événements (en vie) ' + sortIcon('live_events_count') + '</th>';
    html += '<th data-sort="total_events_count" class="col-events-narrow text-end">Événements (total) ' + sortIcon('total_events_count') + '</th>';
    html += '<th data-sort="created_at" class="col-nowrap">Créé le ' + sortIcon('created_at') + '</th>';
    html += '<th data-sort="website" class="col-nowrap">Site ' + sortIcon('website') + '</th>';
    html += '</tr></thead><tbody>';
    if (!pageRows.length) {
      html += '<tr><td colspan="8" class="text-center text-muted py-4">Aucune association</td></tr>';
    } else {
      pageRows.forEach(function(r){
        const site = r.website ? '<a href="' + escapeHtml(r.website) + '" target="_blank" rel="noopener">Site</a>' : '';
        const owner = r.owner_name ? (escapeHtml(r.owner_name) + (r.owner_email ? ' (' + escapeHtml(r.owner_email) + ')' : '')) : '';
        html += '<tr>' +
          '<td>' + escapeHtml(r.name || '') + '</td>' +
          '<td>' + escapeHtml(r.email || '') + '</td>' +
          '<td>' + owner + '</td>' +
          '<td>' + escapeHtml(r.phone || '') + '</td>' +
          '<td class="text-end">' + (parseInt(r.live_events_count,10) || 0) + '</td>' +
          '<td class="text-end">' + (parseInt(r.total_events_count,10) || 0) + '</td>' +
          '<td>' + formatDate(r.created_at) + '</td>' +
          '<td>' + site + '</td>' +
        '</tr>';
      });
    }
    html += '</tbody></table>';

    if (pages > 1) {
      html += '<div class="stats-pager d-flex justify-content-center align-items-center gap-3 mt-3">';
      html += '<div class="stats-pager-info text-muted small">Page ' + page + ' / ' + pages + ' · ' + total + ' éléments</div>';
      html += '<div class="btn-group">';
      html += '<button type="button" class="btn btn-sm btn-outline-secondary" data-page="first" ' + (page === 1 ? 'disabled' : '') + '>&laquo;</button>';
      html += '<button type="button" class="btn btn-sm btn-outline-secondary" data-page="prev" ' + (page === 1 ? 'disabled' : '') + '>&lsaquo;</button>';
      html += '<button type="button" class="btn btn-sm btn-outline-secondary" data-page="next" ' + (page === pages ? 'disabled' : '') + '>&rsaquo;</button>';
      html += '<button type="button" class="btn btn-sm btn-outline-secondary" data-page="last" ' + (page === pages ? 'disabled' : '') + '>&raquo;</button>';
      html += '</div></div>';
    }

    return html;
  }

  let currentType = '';
  let currentData = [];
  let sort = { field: null, asc: true };

  const titles = {
    users: 'Utilisateurs inscrits',
    events: 'Tous les événements',
    pending: 'Événements en attente',
    active: 'Événements actifs',
    categories: 'Catégories',
    subscribers: 'Abonnés aux notifications',
    organizations: 'Associations'
  };

  const dateFields = ['start_date', 'created_at', 'visited_at'];

  function escapeHtml(str) {
    if (typeof str !== 'string') return str;
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function formatDate(dt) {
    if (!dt) return '-';
    const d = new Date(dt);
    if (isNaN(d.getTime())) return dt;
    return d.toLocaleDateString('fr-BE', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function formatDateTime(dt) {
    if (!dt) return '-';
    const d = new Date(dt);
    if (isNaN(d.getTime())) return dt;
    return d.toLocaleString('fr-BE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function compareValues(a, b) {
    if (typeof a === 'number' && typeof b === 'number') return a - b;
    if (typeof a === 'number') return -1;
    if (typeof b === 'number') return 1;
    const da = new Date(a);
    const db = new Date(b);
    if (!isNaN(da.getTime()) && !isNaN(db.getTime())) return da - db;
    return String(a).localeCompare(String(b), 'fr', { numeric: true });
  }

  function sortRows(rows, field, asc) {
    const dir = asc ? 1 : -1;
    return rows.slice().sort(function (a, b) {
      return dir * compareValues(a[field], b[field]);
    });
  }

  function sortIcon(field) {
    if (sort.field !== field) return '<i class="bi bi-caret-up sort-icon text-muted"></i>';
    return sort.asc
      ? '<i class="bi bi-caret-up-fill sort-icon"></i>'
      : '<i class="bi bi-caret-down-fill sort-icon"></i>';
  }

  function th(label, field) {
    return `<th data-sort="${field}">${label} ${sortIcon(field)}</th>`;
  }

  function renderUsers(rows) {
    const sorted = sort.field ? sortRows(rows, sort.field, sort.asc) : sortRows(rows, 'created_at', false);
    let html = '<table class="table stats-table align-middle"><thead><tr>';
    html += th('Nom', 'name');
    html += th('Email', 'email');
    html += th('Rôle', 'role');
    html += th('Inscrit le', 'created_at');
    html += '</tr></thead><tbody>';
    if (!sorted.length) {
      html += '<tr><td colspan="4" class="text-center text-muted py-4">Aucun utilisateur</td></tr>';
    } else {
      sorted.forEach(function (r) {
        html += `<tr><td>${escapeHtml(r.name)}</td><td>${escapeHtml(r.email)}</td><td>${escapeHtml(r.role)}</td><td>${formatDate(r.created_at)}</td></tr>`;
      });
    }
    html += '</tbody></table>';
    return html;
  }

  function renderSubscribers(rows) {
    const sorted = sort.field ? sortRows(rows, sort.field, sort.asc) : sortRows(rows, 'verified_at', false);
    let html = '<table class="table stats-table align-middle"><thead><tr>';
    html += th('Email', 'email');
    html += th('Vérifié le', 'verified_at');
    html += th('Actif', 'is_active');
    html += th('Fréquence', 'notification_frequency');
    html += th('Catégories', 'categories_count');
    html += th('Inscrit le', 'created_at');
    html += '</tr></thead><tbody>';
    if (!sorted.length) {
      html += '<tr><td colspan="6" class="text-center text-muted py-4">Aucun abonné</td></tr>';
    } else {
      sorted.forEach(function (r) {
        html += `<tr>`;
        html += `<td>${escapeHtml(r.email)}</td>`;
        html += `<td>${formatDateTime(r.verified_at)}</td>`;
        html += `<td>${parseInt(r.is_active,10) ? 'Oui' : 'Non'}</td>`;
        html += `<td>${escapeHtml(r.notification_frequency || '-') }</td>`;
        html += `<td>${parseInt(r.categories_count,10) || 0}</td>`;
        html += `<td>${formatDateTime(r.created_at)}</td>`;
        html += `</tr>`;
      });
    }
    html += '</tbody></table>';
    return html;
  }

  function renderEvents(rows, isPending) {
    const sorted = sort.field ? sortRows(rows, sort.field, sort.asc) : sortRows(rows, 'start_date', false);
    let html = '<table class="table stats-table align-middle"><thead><tr>';
    html += th('Titre', 'title');
    html += th('Organisateur', 'organizer_name');
    html += th('Date', 'start_date');
    html += th('Statut', 'status');
    if (isPending) html += '<th class="text-end">Actions</th>';
    html += '</tr></thead><tbody>';
    if (!sorted.length) {
      html += `<tr><td colspan="${isPending ? 5 : 4}" class="text-center text-muted py-4">Aucun événement</td></tr>`;
    } else {
      sorted.forEach(function (r) {
        html += `<tr><td>${escapeHtml(r.title)}</td><td>${escapeHtml(r.organizer_name)}</td><td>${formatDate(r.start_date)}</td><td>${escapeHtml(r.status)}</td>`;
        if (isPending) {
          html += `<td class="text-end">`;
          html += `<a href="/templates/events/edit-event.php?id=${parseInt(r.id)}" class="btn btn-sm btn-outline-primary me-1" title="Modifier" onclick="try{sessionStorage.setItem('adminEdit','1')}catch(_){}"><i class="bi bi-pencil"></i></a>`;
          html += `<button type="button" class="btn btn-sm btn-success me-1" data-kpi-action="approve" data-event-id="${parseInt(r.id)}" title="Approuver"><i class="bi bi-check-lg"></i></button>`;
          html += `<button type="button" class="btn btn-sm btn-warning me-1" data-kpi-action="reject" data-event-id="${parseInt(r.id)}" title="Refuser"><i class="bi bi-x-lg"></i></button>`;
          html += `<button type="button" class="btn btn-sm btn-danger" data-kpi-action="delete" data-event-id="${parseInt(r.id)}" title="Supprimer"><i class="bi bi-trash"></i></button>`;
          html += `</td>`;
        }
        html += '</tr>';
      });
    }
    html += '</tbody></table>';
    return html;
  }

  function renderCategories(rows) {
    const sorted = sortRows(rows, sort.field || 'name', sort.asc);
    let html = '<div class="d-flex justify-content-between align-items-center mb-3">';
    html += '<button type="button" class="btn btn-success" data-kpi-action="new-category"><i class="bi bi-plus-lg"></i> Nouvelle catégorie</button>';
    html += '</div>';
    html += '<table class="table stats-table align-middle"><thead><tr>';
    html += th('Code', 'code');
    html += th('Nom', 'name');
    html += th('Icône', 'icon');
    html += th('Couleur', 'color');
    html += th('Événements', 'event_count');
    html += '</tr></thead><tbody>';
    if (!sorted.length) {
      html += '<tr><td colspan="5" class="text-center text-muted py-4">Aucune catégorie</td></tr>';
    } else {
      sorted.forEach(function (r) {
        html += `<tr>`;
        html += `<td>${escapeHtml(r.code)}</td>`;
        html += `<td>${escapeHtml(r.name)}</td>`;
        html += `<td>${r.icon ? '<i class="bi bi-' + escapeHtml(r.icon) + '"></i> ' + escapeHtml(r.icon) : '-'}</td>`;
        html += `<td>${r.color ? '<span class="color-preview" style="background-color:' + escapeHtml(r.color) + '"></span> ' + escapeHtml(r.color) : '-'}</td>`;
        html += `<td>${parseInt(r.event_count, 10)}</td>`;
        html += `</tr>`;
      });
    }
    html += '</tbody></table>';
    return html;
  }

  function render() {
    const body = document.getElementById('kpiModalBody');
    const title = document.getElementById('kpiModalTitle');
    if (!body) return;
    if (title) title.textContent = titles[currentType] || 'Détail';

    if (currentType === 'users') body.innerHTML = renderUsers(currentData);
    else if (currentType === 'categories') body.innerHTML = renderCategories(currentData);
    else if (currentType === 'subscribers') body.innerHTML = renderSubscribers(currentData);
    else if (currentType === 'organizations') body.innerHTML = renderOrganizations(currentData);
    else body.innerHTML = renderEvents(currentData, currentType === 'pending');
  }

  async function load(type) {
    const body = document.getElementById('kpiModalBody');
    if (!body) return;
    if (body) {
      body.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
    }

    currentType = type;
    sort = { field: null, asc: true };

    try {
      const res = await fetch('/api/admin/dashboard-detail.php?type=' + encodeURIComponent(type));
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erreur serveur');
      currentData = json.data || [];
      render();
      // Initialisation paresseuse du modal au cas où Bootstrap n'était pas prêt au chargement du script
      const el = document.getElementById('kpiModal');
      if (!modal && el && window.bootstrap && typeof bootstrap.Modal === 'function') {
        modal = new bootstrap.Modal(el);
      }
      if (modal) {
        try { modal.show(); } catch(e) {}
      } else if (el) {
        // Fallback minimal si bootstrap.Modal indisponible: afficher le modal en CSS
        el.classList.add('show');
        el.style.display = 'block';
        document.body.classList.add('modal-open');
        let backdrop = document.querySelector('.modal-backdrop');
        if (!backdrop) {
          backdrop = document.createElement('div');
          backdrop.className = 'modal-backdrop fade show';
          document.body.appendChild(backdrop);
        }
      } else {
        // modal introuvable
      }
    } catch (e) {
      body.innerHTML = '<div class="alert alert-danger">Erreur : ' + escapeHtml(e.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('[data-kpi-modal]');
    cards.forEach(function (card) {
      card.addEventListener('click', function () {
        load(card.getAttribute('data-kpi-modal'));
      });
    });

    const body = document.getElementById('kpiModalBody');
    if (body) {
      body.addEventListener('click', function (e) {
        const th = e.target.closest('th[data-sort]');
        if (th) {
          const field = th.getAttribute('data-sort');
          if (sort.field === field) {
            sort.asc = !sort.asc;
          } else {
            sort.field = field;
            sort.asc = true;
          }
          render();
          return;
        }

        // Pagination controls for organizations
        if (currentType === 'organizations') {
          const pgBtn = e.target.closest('button[data-page]');
          if (pgBtn) {
            const action = pgBtn.getAttribute('data-page');
            const pageSize = 10;
            const total = currentData.length;
            const pages = Math.max(1, Math.ceil(total / pageSize));
            let page = window._orgPage || 1;
            if (action === 'first') page = 1;
            else if (action === 'prev') page = Math.max(1, page - 1);
            else if (action === 'next') page = Math.min(pages, page + 1);
            else if (action === 'last') page = pages;
            window._orgPage = page;
            render();
            return;
          }
        }

        const btn = e.target.closest('[data-kpi-action]');
        if (!btn) return;

        const action = btn.getAttribute('data-kpi-action');
        const id = btn.getAttribute('data-event-id');

        if (action === 'new-category') {
          if (modal) modal.hide();
          const catModal = document.getElementById('categoryModal');
          if (catModal) {
            // eslint-disable-next-line no-undef
            new bootstrap.Modal(catModal).show();
          }
          return;
        }

        if (action === 'approve' && typeof window.updateEventStatus === 'function') {
          window.updateEventStatus(id, 'approved');
        } else if (action === 'reject' && typeof window.updateEventStatus === 'function') {
          window.updateEventStatus(id, 'rejected');
        } else if (action === 'delete' && typeof window.deleteEvent === 'function') {
          window.deleteEvent(id);
        }
      });
    }

    document.getElementById('kpiModal')?.addEventListener('shown.bs.modal', function () {
      // Si on veut préserver le focus
      const firstTh = document.querySelector('#kpiModalBody th[data-sort]');
      if (firstTh) firstTh.focus();
    });
  });
})();
