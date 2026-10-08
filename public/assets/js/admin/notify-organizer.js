/**
 * Fichier: /assets/js/admin/notify-organizer.js
 * Rôle: notification organisateur — toast Bootstrap + modale de confirmation
 *       (créés dynamiquement, aucun markup requis sur la page).
 *       Fallback window.confirm/alert si bootstrap n'est pas chargé.
 * Expose: window.notifyOrganizer(eventId)
 * Dépendances: /api/admin/events/notify_organizer.php
 */
(function () {
  'use strict';

  // --- Toast : conteneur créé à la demande ---
  function toastContainer() {
    let c = document.getElementById('notifyToastContainer');
    if (!c) {
      c = document.createElement('div');
      c.id = 'notifyToastContainer';
      c.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      c.style.zIndex = '1080';
      document.body.appendChild(c);
    }
    return c;
  }

  function showToast(message, isSuccess) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
      (isSuccess ? console.info : console.error)(message);
      return alert(message);
    }
    const el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + (isSuccess ? 'success' : 'danger') + ' border-0';
    el.setAttribute('role', 'status');
    el.innerHTML =
      '<div class="d-flex">' +
        '<div class="toast-body"></div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>' +
      '</div>';
    el.querySelector('.toast-body').textContent = message;
    toastContainer().appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 4000 });
    el.addEventListener('hidden.bs.toast', () => el.remove());
    t.show();
  }

  // --- Confirmation : modale créée à la demande, résout une Promise ---
  function confirmDialog(message) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
      return Promise.resolve(window.confirm(message));
    }
    let modalEl = document.getElementById('notifyConfirmModal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.id = 'notifyConfirmModal';
      modalEl.tabIndex = -1;
      modalEl.innerHTML =
        '<div class="modal-dialog modal-dialog-centered">' +
          '<div class="modal-content">' +
            '<div class="modal-header">' +
              '<h5 class="modal-title">Notifier l\'organisateur</h5>' +
              '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>' +
            '</div>' +
            '<div class="modal-body" id="notifyConfirmBody"></div>' +
            '<div class="modal-footer">' +
              '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>' +
              '<button type="button" class="btn btn-success" id="notifyConfirmBtn">Envoyer l\'email</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      document.body.appendChild(modalEl);
    }

    return new Promise((resolve) => {
      const body = modalEl.querySelector('#notifyConfirmBody');
      const btn = modalEl.querySelector('#notifyConfirmBtn');
      body.textContent = message;

      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      const onClick = () => { cleanup(); modal.hide(); resolve(true); };
      const onHidden = () => { cleanup(); resolve(false); };
      function cleanup() {
        btn.removeEventListener('click', onClick);
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
      }
      btn.addEventListener('click', onClick);
      modalEl.addEventListener('hidden.bs.modal', onHidden);
      modal.show();
    });
  }

  // --- Action publique ---
  window.notifyOrganizer = async function (eventId) {
    const ok = await confirmDialog("Envoyer l'email « Votre événement est en ligne » à l'organisateur ?");
    if (!ok) return;

    try {
      const r = await fetch('/api/admin/events/notify_organizer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ event_id: eventId })
      });
      const data = await r.json();

      if (data.logs) {
        console.group('📝 Logs serveur:');
        data.logs.forEach((l) => console.log(l));
        console.groupEnd();
      }

      if (data.success) {
        showToast(data.message || 'Email envoyé', true);
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast('Erreur : ' + (data.message || 'envoi impossible'), false);
      }
    } catch (e) {
      console.error('notifyOrganizer:', e);
      showToast('Erreur de communication avec le serveur', false);
    }
  };
})();
