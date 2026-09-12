/**
 * Fichier: /assets/js/user/my-events.js
 * Rôle: Gestion de la page Mes événements (suppression brouillon, auto-dismiss flash, création de brouillon).
 * Utilisation: /pages/user/my-events.php.
 * Dépendances: Fetch API (/api/events/create_draft_from_event.php), Bootstrap Modal (#deleteConfirmModal).
 */
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('deleteConfirmModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const draftId = button ? button.getAttribute('data-draft-id') : '';
            const draftIdInput = deleteModal.querySelector('#deleteDraftId');
            if (draftIdInput) {
                draftIdInput.value = draftId;
            }
        });
    }

    const cancelModal = document.getElementById('cancelEventModal');
    if (cancelModal) {
        cancelModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const eventId = button ? button.getAttribute('data-cancel-id') : '';
            const eventTitle = button ? button.getAttribute('data-cancel-title') : '';
            const idInput = cancelModal.querySelector('#cancelEventId');
            const titleEl = cancelModal.querySelector('#cancelEventTitle');
            const reasonInput = cancelModal.querySelector('#cancellationReason');
            if (idInput) idInput.value = eventId;
            if (titleEl) titleEl.textContent = eventTitle || '';
            if (reasonInput) reasonInput.value = '';
        });
    }

    // Auto-dismiss flash messages after 5 seconds
    const flashMessages = document.querySelector('.flash-messages');
    if (flashMessages) {
        const alerts = flashMessages.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            }, 5000);
        });
    }

    // Démarrer l'édition d'un événement publié: créer un brouillon et ouvrir la modale création
    document.querySelectorAll('.btn-edit-published').forEach(btn => {
        btn.addEventListener('click', async () => {
            const eventId = parseInt(btn.getAttribute('data-event-id'), 10);
            if (!eventId) return;
            try {
                const res = await fetch('/api/events/create_draft_from_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: eventId })
                });
                const data = await res.json();
                if (data && data.success && data.draft_id) {
                    window.location.href = '/?create=1&draft_id=' + encodeURIComponent(data.draft_id);
                } else {
                    console.error('[my-events] Echec création brouillon:', data);
                    alert((data && data.message) || 'Impossible de préparer la modification.');
                }
            } catch (e) {
                console.error('[my-events] Erreur requête create_draft_from_event:', e);
                alert('Erreur réseau lors de la préparation de la modification.');
            }
        });
    });

    // Tri des colonnes
    const table = document.getElementById('my-events-table');
    if (table) {
        const tbody = table.querySelector('tbody');
        const sortButtons = table.querySelectorAll('thead .sort-toggle');
        let currentSort = { key: null, dir: 'asc' };

        const compare = (a, b, key, dir) => {
            const mul = dir === 'asc' ? 1 : -1;
            if (key === 'title') {
                const va = (a.dataset.title || '').toLowerCase();
                const vb = (b.dataset.title || '').toLowerCase();
                return va.localeCompare(vb) * mul;
            }
            if (key === 'status') {
                const va = (a.dataset.status || '');
                const vb = (b.dataset.status || '');
                return va.localeCompare(vb) * mul;
            }
            if (key === 'date') {
                const va = parseInt(a.dataset.dateTs || '0', 10) || 0;
                const vb = parseInt(b.dataset.dateTs || '0', 10) || 0;
                return (va - vb) * mul;
            }
            if (key === 'recorded') {
                const va = parseInt(a.dataset.recordedTs || '0', 10) || 0;
                const vb = parseInt(b.dataset.recordedTs || '0', 10) || 0;
                return (va - vb) * mul;
            }
            return 0;
        };

        const setArrows = (btn, dir) => {
            // reset all
            table.querySelectorAll('thead .caret').forEach(c => c.classList.remove('active'));
            // activate on current
            const up = btn.querySelector('.caret.up');
            const down = btn.querySelector('.caret.down');
            if (dir === 'asc' && up) up.classList.add('active');
            if (dir === 'desc' && down) down.classList.add('active');
        };

        sortButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const key = btn.dataset.sort;
                let dir = 'asc';
                if (currentSort.key === key) {
                    dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
                }
                currentSort = { key, dir };
                setArrows(btn, dir);

                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((r1, r2) => compare(r1, r2, key, dir));
                rows.forEach(r => tbody.appendChild(r));
            });
        });
    }
});
