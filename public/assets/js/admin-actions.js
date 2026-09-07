(function () {
  'use strict';

  function showConfirm({ title, message, variant = 'approve', confirmText = 'Confirmer', cancelText = 'Annuler' }) {
    return new Promise((resolve) => {
      const modalEl = document.getElementById('confirmActionModal');
      const titleEl = document.getElementById('confirmActionTitle');
      const msgEl = document.getElementById('confirmActionMessage');
      const btn = document.getElementById('confirmActionBtn');

      if (!modalEl || !titleEl || !msgEl || !btn || typeof bootstrap === 'undefined') {
        const ok = window.confirm(message || 'Confirmer ?');
        return resolve(ok);
      }

      titleEl.textContent = title || 'Confirmer';
      msgEl.textContent = message || 'Êtes-vous sûr ?';
      btn.textContent = confirmText;

      btn.className = 'btn';
      if (variant === 'approve') btn.classList.add('btn-confirm-approve');
      else if (variant === 'reject') btn.classList.add('btn-confirm-reject');
      else btn.classList.add('btn-primary');

      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      const onClick = () => { cleanup(); resolve(true); };
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

  function showToast({ title = 'Action', message = '', variant = 'success', delay = 2200 }) {
    const container = document.getElementById('adminToastContainer');
    if (!container) return;

    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center ' + (variant === 'success' ? 'toast-success' : 'toast-error');
    toastEl.role = 'status';
    toastEl.ariaLive = 'polite';
    toastEl.ariaAtomic = 'true';
    toastEl.innerHTML = `
      <div class="toast-header">
        <strong class="me-auto">${title}</strong>
        <small class="text-muted">Maintenant</small>
        <button type="button" class="btn-close ms-2 mb-1" data-bs-dismiss="toast" aria-label="Fermer"></button>
      </div>
      <div class="toast-body">${message}</div>
    `;
    container.appendChild(toastEl);
    const t = new bootstrap.Toast(toastEl, { delay });
    t.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
  }

  // Override updateEventStatus / deleteEvent if already defined
  window.updateEventStatus = async function (eventId, status, rejectionReason) {
    const label = status === 'approved' ? 'approuver' : (status === 'rejected' ? 'rejeter' : 'remettre en attente');
    const variant = status === 'approved' ? 'approve' : (status === 'rejected' ? 'reject' : 'info');

    const ok = await showConfirm({
      title: `Confirmer ${label}`,
      message: `Voulez-vous vraiment ${label} cet événement ?`,
      confirmText: 'Oui, confirmer',
      variant
    });
    if (!ok) return;

    const data = { event_id: parseInt(eventId, 10), status };
    if (status === 'rejected' && rejectionReason && rejectionReason.trim()) {
      data.rejection_reason = rejectionReason.trim();
    }

    try {
      const res = await fetch('/api/admin/events/update_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(data)
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Erreur serveur');

      showToast({
        title: 'Événement',
        message: status === 'approved' ? 'Événement approuvé avec succès.' : (status === 'rejected' ? 'Événement rejeté.' : 'Statut mis à jour.'),
        variant: 'success'
      });
      setTimeout(() => location.reload(), 900);
    } catch (e) {
      console.error(e);
      showToast({ title: 'Erreur', message: e.message || 'Action impossible', variant: 'error' });
    }
  };

  window.deleteEvent = async function (eventId) {
    const ok = await showConfirm({
      title: 'Supprimer',
      message: 'Voulez-vous vraiment supprimer cet événement ? Cette action est irréversible.',
      confirmText: 'Supprimer',
      variant: 'reject'
    });
    if (!ok) return;

    try {
      const res = await fetch('/api/admin/events/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ event_id: parseInt(eventId, 10) })
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Erreur serveur');
      showToast({ title: 'Événement', message: 'Supprimé avec succès.', variant: 'success' });
      setTimeout(() => location.href = '/pages/admin/events.php', 900);
    } catch (e) {
      console.error(e);
      showToast({ title: 'Erreur', message: e.message || 'Suppression impossible', variant: 'error' });
    }
  };
})();
