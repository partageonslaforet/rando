/**
 * Fichier: /assets/js/admin/sponsors.js
 * Rôle: Gestion CRUD des sponsors côté admin (soumission formulaire, feedback, rechargement UI).
 * Utilisation: section Sponsors dans le dashboard admin.
 * Dépendances: Fetch API (/api/admin/sponsors/*.php), Bootstrap Modal.
 * Pattern identique à categories.js — la redirection utilise #section-sponsors
 * (lu par admin-dashboard.js au chargement).
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    function backToSponsors() {
        const url = new URL(window.location.href);
        url.hash = 'section-sponsors';
        window.location.href = url.toString();
        window.location.reload();
    }

    // Soumission du formulaire (création / édition)
    const sponsorForm = document.getElementById('sponsorForm');
    if (sponsorForm) {
        sponsorForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const sponsorId = formData.get('id');
            const isEdit = sponsorId && sponsorId !== '';

            try {
                const response = await fetch(`/api/admin/sponsors/${isEdit ? 'update' : 'create'}.php`, {
                    method: 'POST',
                    body: formData
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.error('Réponse non-JSON reçue:', await response.text());
                    throw new Error('Réponse invalide du serveur');
                }

                const data = await response.json();

                if (data.success) {
                    try {
                        const modalEl = document.getElementById('sponsorModal');
                        if (modalEl) {
                            const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                            bsModal.hide();
                        }
                    } catch (err) {
                        console.warn('Fermeture du modal: non critique', err);
                    }
                    backToSponsors();
                } else {
                    alert(data.message || 'Une erreur est survenue');
                }
            } catch (error) {
                console.error('Erreur complète:', error);
                alert('Une erreur est survenue lors de la communication avec le serveur: ' + error.message);
            }
        });
    }

    // Toggle actif/inactif (switch identique aux catégories)
    document.querySelectorAll('.toggle-sponsor').forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const sponsorId = this.dataset.id;
            const active = this.checked;

            try {
                const response = await fetch('/api/admin/sponsors/toggle.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: sponsorId, active: active })
                });

                const data = await response.json();

                if (!data.success) {
                    this.checked = !active;
                    alert(data.message || 'Une erreur est survenue');
                }
            } catch (error) {
                console.error('Erreur:', error);
                this.checked = !active;
                alert('Une erreur est survenue');
            }
        });
    });

    // Édition : pré-remplit la modale avec les data-* du bouton
    document.querySelectorAll('.edit-sponsor').forEach(button => {
        button.addEventListener('click', function() {
            const modal = document.getElementById('sponsorModal');
            const form = modal.querySelector('form');

            form.querySelector('#sponsor_id').value = this.dataset.id;
            form.querySelector('#sponsor_name').value = this.dataset.name;
            form.querySelector('#sponsor_link_url').value = this.dataset.link;
            form.querySelector('#sponsor_alt_text').value = this.dataset.alt;
            form.querySelector('#sponsor_position').value = this.dataset.position;
            form.querySelector('#sponsor_start_date').value = this.dataset.start || '';
            form.querySelector('#sponsor_end_date').value = this.dataset.end || '';
            form.querySelector('#sponsor_image_url').value = '';

            const title = document.getElementById('sponsorModalTitle');
            if (title) title.textContent = 'Modifier le sponsor';

            const box = document.getElementById('sponsorImageBox');
            const img = document.getElementById('sponsor_current_image');
            if (box && img) {
                if (this.dataset.image) {
                    img.src = this.dataset.image;
                    box.classList.add('has-image');
                } else {
                    img.removeAttribute('src');
                    box.classList.remove('has-image');
                }
            }

            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        });
    });

    // Suppression
    document.querySelectorAll('.delete-sponsor').forEach(button => {
        button.addEventListener('click', async function() {
            if (!confirm('Supprimer ce sponsor ?')) {
                return;
            }

            try {
                const response = await fetch('/api/admin/sponsors/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: this.dataset.id })
                });

                const data = await response.json();

                if (data.success) {
                    backToSponsors();
                } else {
                    alert(data.message || 'Une erreur est survenue');
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            }
        });
    });

    // Réinitialise le formulaire à l'ouverture pour un ajout (pas pour l'édition)
    const sponsorModal = document.getElementById('sponsorModal');
    if (sponsorModal) {
        sponsorModal.addEventListener('show.bs.modal', function(event) {
            if (!event.relatedTarget) return;
            const form = this.querySelector('form');
            form.reset();
            form.querySelector('#sponsor_id').value = '';
            const title = document.getElementById('sponsorModalTitle');
            if (title) title.textContent = 'Nouveau sponsor';
            const box = document.getElementById('sponsorImageBox');
            const img = document.getElementById('sponsor_current_image');
            if (img) img.removeAttribute('src');
            if (box) box.classList.remove('has-image');
        });
    }

    // Aperçu immédiat du fichier choisi dans la zone d'upload
    const sponsorImageInput = document.getElementById('sponsor_image');
    if (sponsorImageInput) {
        sponsorImageInput.addEventListener('change', function() {
            const box = document.getElementById('sponsorImageBox');
            const img = document.getElementById('sponsor_current_image');
            if (this.files && this.files[0] && box && img) {
                img.src = URL.createObjectURL(this.files[0]);
                box.classList.add('has-image');
            }
        });
    }
});
