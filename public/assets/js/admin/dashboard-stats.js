/**
 * Gestion des tuiles cliquables et modals de fréquentation.
 */
(function () {
  'use strict';

  const modalEl = document.getElementById('statsModal');
  let modal = null;
  if (modalEl) {
    // eslint-disable-next-line no-undef
    modal = new bootstrap.Modal(modalEl);
  }

  let currentType = 'visits';
  let currentPeriod = 7;
  let currentDate = '';
  let currentPage = 1;
  let currentLimit = 50;
  let currentData = [];
  let currentPagination = null;
  let currentCountry = null;
  let currentBack = null;
  let currentReferred = false;
  let ipDetailHtml = '';
  let sort = { field: null, asc: true };

  function escapeHtml(str) {
    if (typeof str !== 'string') return str;
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function formatDate(day) {
    if (!day) return '-';
    const d = new Date(day);
    if (isNaN(d.getTime())) return day;
    return d.toLocaleDateString('fr-BE', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function formatDateTime(dt) {
    if (!dt) return '-';
    const d = new Date(dt);
    if (isNaN(d.getTime())) return dt;
    return d.toLocaleString('fr-BE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function compareValues(a, b) {
    if (a === null || a === undefined) return 1;
    if (b === null || b === undefined) return -1;
    if (typeof a === 'number' && typeof b === 'number') return a - b;
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

  function th(label, field, align) {
    const cls = align === 'end' ? ' class="text-end"' : '';
    return `<th data-sort="${field}"${cls}>${label} ${sortIcon(field)}</th>`;
  }

  function renderVisits(rows) {
    const sorted = sortRows(rows, sort.field || 'day', sort.asc);
    let html = '<table class="table align-middle"><thead><tr>';
    html += th('DATE', 'day');
    html += th('VISITES', 'visits', 'end');
    html += th('SESSIONS', 'sessions', 'end');
    html += th('VISITEURS UNIQUES (IP)', 'unique_ips', 'end');
    html += th('NON-HUMAINS', 'non_humans', 'end');
    html += '</tr></thead><tbody>';
    if (!sorted || !sorted.length) {
      html += '<tr><td colspan="5" class="text-center text-muted py-4">Aucune donnée</td></tr>';
    } else {
      sorted.forEach(function (r) {
        html += `<tr><td><a href="#" data-visit-date="${escapeHtml(r.day)}">${formatDate(r.day)}</a></td><td class="text-end">${parseInt(r.visits, 10)}</td><td class="text-end">${parseInt(r.sessions, 10)}</td><td class="text-end">${parseInt(r.unique_ips, 10)}</td><td class="text-end">${parseInt(r.non_humans, 10)}</td></tr>`;
      });
    }
    html += '</tbody></table>';
    return html;
  }

  function renderPages(rows) {
    const sorted = sortRows(rows, sort.field || 'visits', sort.asc);
    let html = '<table class="table align-middle"><thead><tr>';
    html += th('PAGE', 'label');
    html += th('VISITES', 'visits', 'end');
    html += '</tr></thead><tbody>';
    if (!sorted || !sorted.length) {
      html += '<tr><td colspan="2" class="text-center text-muted py-4">Aucune donnée</td></tr>';
    } else {
      sorted.forEach(function (r) {
        html += `<tr><td>${escapeHtml(r.label)}</td><td class="text-end">${parseInt(r.visits, 10)}</td></tr>`;
      });
    }
    html += '</tbody></table>';
    return html;
  }

  function renderRecent(rows) {
    const enriched = rows.map(function (r) {
      const loc = [r.city, r.country].filter(Boolean).join(', ') || 'Inconnue';
      return Object.assign({}, r, { loc: loc, label: r.label || r.url });
    });
    const sorted = sortRows(enriched, sort.field || 'visited_at', sort.asc);
    let html = '<table class="table align-middle"><thead><tr>';
    html += th('DATE', 'visited_at');
    html += th('PAGE', 'label');
    html += th('IP', 'ip_address');
    html += th('LOCALISATION', 'loc');
    html += th('TYPE', 'is_human');
    html += th('SOURCE', 'referrer');
    html += '</tr></thead><tbody>';
    if (!sorted || !sorted.length) {
      html += '<tr><td colspan="6" class="text-center text-muted py-4">Aucune donnée</td></tr>';
    } else {
      sorted.forEach(function (r) {
        const typeBadge = r.is_human ? '<span class="badge bg-success">Humain</span>' : '<span class="badge bg-warning text-dark">Non-humain</span>';
        const plfCell = (r.referrer
            && /partageonslaforet/i.test(r.referrer)
            && !/rando\.partageonslaforet/i.test(r.referrer))
          ? '<span class="badge bg-info text-dark" title="' + escapeHtml(r.referrer) + '">PLF</span>'
          : '<span class="text-muted">-</span>';
        const ipCell = r.ip_address
          ? `<a href="#" data-ip="${escapeHtml(r.ip_address)}">${escapeHtml(r.ip_address)}</a>`
          : '-';
        html += `<tr><td>${formatDateTime(r.visited_at)}</td><td>${escapeHtml(r.label)}</td><td>${ipCell}</td><td>${escapeHtml(r.loc)}</td><td>${typeBadge}</td><td>${plfCell}</td></tr>`;
      });
    }
    html += '</tbody></table>';
    return html;
  }

  function updateTitle(type, period, date, country) {
    const base = {
      visits: 'Visites',
      pages: 'Pages les plus visitées',
      recent: 'Dernières visites',
      countries: 'Visites par pays',
      sources: 'Visites référées'
    };
    const titleEl = document.getElementById('statsModalTitle');
    if (!titleEl) return;
    let label = base[type] || 'Statistiques';
    if (date) {
      const d = new Date(date);
      label += ' du ' + d.toLocaleDateString('fr-BE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } else if (period === 1) {
      label += ' aujourd\'hui';
    } else if (period) {
      label += ' sur les ' + period + ' derniers jours';
    }
    if (country !== null && country !== undefined) {
      label += ' — ' + (country || 'Inconnu');
    }
    titleEl.textContent = label;
  }

  function updatePeriodButtons(period) {
    document.querySelectorAll('[data-stat-period]').forEach(function (btn) {
      const p = parseInt(btn.getAttribute('data-stat-period'), 10);
      if (p === period) {
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-secondary');
      } else {
        btn.classList.add('btn-outline-secondary');
        btn.classList.remove('btn-secondary');
      }
    });
  }

  function renderSources(rows) {
    const sorted = sort.field ? sortRows(rows, sort.field, sort.asc) : sortRows(rows, 'day', false);
    let html = '<table class="table align-middle"><thead><tr>';
    html += th('DATE', 'day');
    html += th('RÉFÉRANT', 'referrer');
    html += th('VISITES', 'visits', 'end');
    html += '</tr></thead><tbody>';
    if (!sorted || !sorted.length) {
      html += '<tr><td colspan="3" class="text-center text-muted py-4">Aucune donnée</td></tr>';
    } else {
      sorted.forEach(function (r) {
        html += '<tr><td><a href="#" data-source-date="' + escapeHtml(r.day) + '">' + formatDate(r.day) + '</a></td><td>' + escapeHtml(r.referrer || '') + '</td><td class="text-end">' + parseInt(r.visits, 10) + '</td></tr>';
      });
    }
    html += '</tbody></table>';
    return html;
  }

  function renderCountries(rows) {
    const sorted = sortRows(rows, sort.field || 'visits', sort.asc);
    let html = '<table class="table align-middle"><thead><tr>';
    html += th('PAYS', 'country');
    html += th('VISITES', 'visits', 'end');
    html += '</tr></thead><tbody>';
    if (!sorted || !sorted.length) {
      html += '<tr><td colspan="2" class="text-center text-muted py-4">Aucune donnée</td></tr>';
    } else {
      sorted.forEach(function (r) {
        const country = r.country || 'Inconnu';
        html += '<tr><td><a href="#" data-country="' + escapeHtml(r.country || '') + '">' + escapeHtml(country) + '</a></td><td class="text-end">' + parseInt(r.visits, 10) + '</td></tr>';
      });
    }
    html += '</tbody></table>';
    return html;
  }

  function render() {
    const body = document.getElementById('statsModalBody');
    if (!body) return;
    let html = '';
    if (currentType === 'visits') html = renderVisits(currentData);
    else if (currentType === 'pages') html = renderPages(currentData);
    else if (currentType === 'countries') html = renderCountries(currentData);
    else if (currentType === 'sources') html = renderSources(currentData);
    else {
      if (currentBack === 'countries') {
        html += '<div class="mb-2"><a href="#" data-back class="small"><i class="bi bi-arrow-left"></i> Retour aux pays</a> <span class="badge bg-secondary">' + escapeHtml(currentCountry || 'Inconnu') + '</span></div>';
      } else if (currentBack === 'sources') {
        html += '<div class="mb-2"><a href="#" data-back class="small"><i class="bi bi-arrow-left"></i> Retour aux sources</a> <span class="badge bg-secondary">' + escapeHtml(formatDate(currentDate)) + '</span></div>';
      } else if (currentBack === 'visits') {
        html += '<div class="mb-2"><a href="#" data-back class="small"><i class="bi bi-arrow-left"></i> Retour aux visites</a> <span class="badge bg-secondary">' + escapeHtml(formatDate(currentDate)) + '</span></div>';
      }
      html += renderRecent(currentData);
    }
    body.innerHTML = ipDetailHtml + html;
    const footer = document.getElementById('statsModalFooter');
    if (footer) footer.innerHTML = renderPagination(currentPagination);
    attachPagination();
  }

  function renderIpDetail(d) {
    const coords = (d.lat !== null && d.lat !== undefined && d.lon !== null && d.lon !== undefined)
      ? d.lat + ', ' + d.lon
      : null;
    const rows = [
      ['IP', d.ip],
      ['Pays', d.country],
      ['Région', d.region],
      ['Ville', d.city],
      ['Code postal', d.zip],
      ['Coordonnées', coords],
      ['Fuseau horaire', d.timezone],
      ['FAI', d.isp],
      ['Organisation', d.org],
      ['AS', d.as]
    ].filter(function (r) { return r[1] !== null && r[1] !== undefined && r[1] !== ''; });
    let html = '<div class="card mb-3 border-secondary"><div class="card-body py-2">';
    html += '<div class="d-flex justify-content-between align-items-center mb-1"><strong>Détail IP</strong><button type="button" class="btn-close btn-sm" data-ip-close aria-label="Fermer"></button></div>';
    html += '<div class="row g-3">';
    html += '<div class="' + (coords ? 'col-md-6' : 'col-12') + '"><dl class="row mb-0 small">';
    rows.forEach(function (r) {
      html += '<dt class="col-4">' + escapeHtml(r[0]) + '</dt><dd class="col-8 mb-1">' + escapeHtml(String(r[1])) + '</dd>';
    });
    html += '</dl></div>';
    if (coords) {
      const delta = 0.05;
      const bbox = (d.lon - delta) + ',' + (d.lat - delta) + ',' + (d.lon + delta) + ',' + (d.lat + delta);
      html += '<div class="col-md-6"><iframe class="w-100 rounded border ip-location-map" loading="lazy" title="Carte de localisation IP" src="https://www.openstreetmap.org/export/embed.html?bbox=' + bbox + '&layer=mapnik&marker=' + d.lat + ',' + d.lon + '"></iframe></div>';
    }
    html += '</div></div></div>';
    return html;
  }

  function showIpDetail(ip) {
    ipDetailHtml = '<div class="alert alert-secondary py-2 small">Chargement de ' + escapeHtml(ip) + '…</div>';
    render();
    fetch('/api/admin/ip-detail.php?ip=' + encodeURIComponent(ip))
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json.success) throw new Error(json.message || 'Erreur serveur');
        ipDetailHtml = renderIpDetail(json.data);
        render();
      })
      .catch(function (err) {
        ipDetailHtml = '<div class="alert alert-danger py-2 small">' + escapeHtml(err.message) + '</div>';
        render();
      });
  }

  function renderPagination(pagination) {
    if (!pagination) return '';
    const total = pagination.total || 0;
    const page = pagination.page || 1;
    const totalPages = pagination.total_pages || 1;
    if (total <= 0) return '';
    return '<div class="d-flex justify-content-between align-items-center mt-3">'
      + '<button type="button" id="page-prev" class="btn btn-sm btn-outline-secondary"' + (page <= 1 ? ' disabled' : '') + ' data-page="prev">Précédent</button>'
      + '<span class="text-muted small">Page ' + page + ' / ' + totalPages + ' (' + total + ' résultat' + (total > 1 ? 's' : '') + ')</span>'
      + '<button type="button" id="page-next" class="btn btn-sm btn-outline-secondary"' + (page >= totalPages ? ' disabled' : '') + ' data-page="next">Suivant</button>'
      + '</div>';
  }

  function attachPagination() {
    const prev = document.getElementById('page-prev');
    const next = document.getElementById('page-next');
    if (prev) {
      prev.addEventListener('click', function () {
        if (currentPage > 1) {
          currentPage--;
          load(currentType, currentPeriod, currentDate);
        }
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        currentPage++;
        load(currentType, currentPeriod, currentDate);
      });
    }
  }

  async function load(type, period, date) {
    const body = document.getElementById('statsModalBody');
    if (!body) return;
    ipDetailHtml = '';
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
    const footer = document.getElementById('statsModalFooter');
    if (footer) footer.innerHTML = '';

    try {
      const url = '/api/admin/stats-detail.php?type=' + encodeURIComponent(type)
        + '&period=' + encodeURIComponent(period)
        + '&date=' + encodeURIComponent(date || '')
        + '&page=' + encodeURIComponent(currentPage)
        + '&limit=' + encodeURIComponent(currentLimit)
        + (currentCountry !== null ? '&country=' + encodeURIComponent(currentCountry) : '')
        + (currentReferred ? '&referred=1' : '');
      const res = await fetch(url);
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erreur serveur');

      currentType = type;
      currentPeriod = period;
      currentDate = date || '';
      updateTitle(type, period, currentDate, currentCountry);
      updatePeriodButtons(period);

      currentData = json.data || [];
      currentPagination = json.pagination || null;
      render();
    } catch (e) {
      body.innerHTML = '<div class="alert alert-danger">Erreur : ' + escapeHtml(e.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-stat-modal]').forEach(function (tile) {
      tile.addEventListener('click', function () {
        const type = tile.getAttribute('data-stat-modal');
        currentType = type;
        currentPage = 1;
        currentDate = '';
        currentCountry = null;
        currentBack = null;
        currentReferred = false;
        sort = { field: null, asc: true };
        const dateInput = document.getElementById('statsModalDate');
        if (dateInput) dateInput.value = '';
        load(type, currentPeriod, currentDate);
        if (modal) modal.show();
      });
    });

    document.querySelectorAll('[data-stat-period]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const period = parseInt(btn.getAttribute('data-stat-period'), 10);
        currentPeriod = period;
        currentPage = 1;
        load(currentType, currentPeriod, currentDate);
      });
    });

    const dateInput = document.getElementById('statsModalDate');
    if (dateInput) {
      dateInput.addEventListener('change', function () {
        currentDate = dateInput.value;
        currentPage = 1;
        load(currentType, currentPeriod, currentDate);
      });
    }

    const statsBody = document.getElementById('statsModalBody');
    if (statsBody) {
      statsBody.addEventListener('click', function (e) {
        const ipClose = e.target.closest('[data-ip-close]');
        if (ipClose) {
          e.preventDefault();
          ipDetailHtml = '';
          render();
          return;
        }
        const ipLink = e.target.closest('a[data-ip]');
        if (ipLink) {
          e.preventDefault();
          showIpDetail(ipLink.getAttribute('data-ip'));
          return;
        }
        const dayLink = e.target.closest('a[data-visit-date]');
        if (dayLink) {
          e.preventDefault();
          currentType = 'recent';
          currentDate = dayLink.getAttribute('data-visit-date');
          currentBack = 'visits';
          currentReferred = false;
          currentPage = 1;
          sort = { field: null, asc: true };
          const di = document.getElementById('statsModalDate');
          if (di) di.value = currentDate;
          load('recent', currentPeriod, currentDate);
          return;
        }
        const sourceLink = e.target.closest('a[data-source-date]');
        if (sourceLink) {
          e.preventDefault();
          currentType = 'recent';
          currentDate = sourceLink.getAttribute('data-source-date');
          currentBack = 'sources';
          currentReferred = true;
          currentPage = 1;
          sort = { field: null, asc: true };
          const di = document.getElementById('statsModalDate');
          if (di) di.value = currentDate;
          load('recent', currentPeriod, currentDate);
          return;
        }
        const link = e.target.closest('a[data-country]');
        if (link) {
          e.preventDefault();
          currentType = 'recent';
          currentCountry = link.getAttribute('data-country');
          currentBack = 'countries';
          currentReferred = false;
          currentPage = 1;
          sort = { field: null, asc: true };
          load('recent', currentPeriod, currentDate);
          return;
        }
        const back = e.target.closest('[data-back]');
        if (back) {
          e.preventDefault();
          if (currentBack === 'countries') {
            currentType = 'countries';
            currentCountry = null;
          } else if (currentBack === 'sources') {
            currentType = 'sources';
            currentReferred = false;
            currentDate = '';
            const di = document.getElementById('statsModalDate');
            if (di) di.value = '';
          } else {
            currentType = 'visits';
            currentDate = '';
            const di = document.getElementById('statsModalDate');
            if (di) di.value = '';
          }
          currentBack = null;
          currentPage = 1;
          load(currentType, currentPeriod, currentDate);
          return;
        }
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
        }
      });
    }

    // Liens IP / date dans les tableaux du dashboard (hors modal)
    document.addEventListener('click', function (e) {
      const body = document.getElementById('statsModalBody');
      if (body && body.contains(e.target)) return; // déjà géré par le handler du modal

      const dayLink = e.target.closest('a[data-visit-date]');
      if (dayLink) {
        e.preventDefault();
        currentType = 'recent';
        currentDate = dayLink.getAttribute('data-visit-date');
        currentBack = 'visits';
        currentCountry = null;
        currentReferred = false;
        currentPage = 1;
        sort = { field: null, asc: true };
        const di = document.getElementById('statsModalDate');
        if (di) di.value = currentDate;
        if (modal) modal.show();
        load('recent', currentPeriod, currentDate);
        return;
      }

      const ipLink = e.target.closest('a[data-ip]');
      if (ipLink) {
        e.preventDefault();
        const ip = ipLink.getAttribute('data-ip');
        currentType = 'recent';
        currentDate = '';
        currentCountry = null;
        currentBack = null;
        currentReferred = false;
        currentPage = 1;
        sort = { field: null, asc: true };
        const di = document.getElementById('statsModalDate');
        if (di) di.value = '';
        if (modal) modal.show();
        load('recent', currentPeriod, '').then(function () { showIpDetail(ip); });
      }
    });
  });
})();
