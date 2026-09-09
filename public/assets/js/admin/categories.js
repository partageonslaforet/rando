/**
 * Fichier: /assets/js/admin/categories.js
 * Rôle: Gestion CRUD des catégories côté admin (soumission formulaire, feedback, rechargement UI).
 * Utilisation: onglet/section Catégories dans l’admin.
 * Dépendances: Fetch API (/api/admin/categories/create.php, /api/admin/categories/update.php), Bootstrap Modal.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation de Sortable pour le réordonnancement
    // Tri alphabétique côté serveur: tri manuel par glisser désactivé
    // (si besoin de réactiver plus tard, utiliser draggable: 'tr' et un endpoint adapté)

    // Gestion du formulaire de catégorie
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const categoryId = formData.get('id');
            const isEdit = categoryId && categoryId !== '';
            
            try {
                console.log('Envoi des données:', Object.fromEntries(formData.entries()));
                
                const response = await fetch(`/api/admin/categories/${isEdit ? 'update' : 'create'}.php`, {
                    method: 'POST',
                    body: formData
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.error('Réponse non-JSON reçue:', await response.text());
                    throw new Error('Réponse invalide du serveur');
                }

                const data = await response.json();
                console.log('Réponse du serveur:', data);

                if (data.success) {
                    // Fermer le modal si présent puis recharger la page actuelle
                    try {
                        const modalEl = document.getElementById('categoryModal');
                        if (modalEl) {
                            const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                            bsModal.hide();
                        }
                    } catch (e) {
                        console.warn('Fermeture du modal: non critique', e);
                    }
                    // Rediriger vers la même page en forçant l'onglet catégories
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'categories');
                    console.debug('Catégorie sauvegardée, redirection vers:', url.toString());
                    window.location.href = url.toString();
                } else {
                    let errorMessage = data.message || 'Une erreur est survenue';
                    if (data.debug) {
                        console.error('Détails de débogage:', data.debug);
                        if (data.debug.error) {
                            errorMessage += '\n\nDétails: ' + data.debug.error.message;
                        }
                    }
                    alert(errorMessage);
                }
            } catch (error) {
                console.error('Erreur complète:', error);
                alert('Une erreur est survenue lors de la communication avec le serveur: ' + error.message);
            }
        });
    }

    // Gestion du toggle actif/inactif
    document.querySelectorAll('.toggle-category').forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const categoryId = this.dataset.id;
            const active = this.checked;

            try {
                const response = await fetch('/api/admin/categories/toggle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: categoryId,
                        active: active
                    })
                });

                const data = await response.json();
                
                if (!data.success) {
                    this.checked = !active; // Remettre dans l'état précédent
                    alert(data.message || 'Une erreur est survenue');
                }
            } catch (error) {
                console.error('Erreur:', error);
                this.checked = !active; // Remettre dans l'état précédent
                alert('Une erreur est survenue');
            }
        });
    });

    // Gestion de l'édition
    document.querySelectorAll('.edit-category').forEach(button => {
        button.addEventListener('click', function() {
            const modal = document.getElementById('categoryModal');
            const form = modal.querySelector('form');
            
            // Remplir le formulaire avec les données de la catégorie
            form.querySelector('#category_id').value = this.dataset.id;
            form.querySelector('#category_code').value = this.dataset.code;
            form.querySelector('#category_name').value = this.dataset.name;
            form.querySelector('#category_icon').value = this.dataset.icon;
            form.querySelector('#category_color').value = this.dataset.color;
            
            // Ouvrir le modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        });
    });

    // Gestion de la suppression
    document.querySelectorAll('.delete-category').forEach(button => {
        button.addEventListener('click', async function() {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')) {
                return;
            }

            const categoryId = this.dataset.id;

            try {
                const response = await fetch('/api/admin/categories/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: categoryId
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'categories');
                    console.debug('Catégorie supprimée, redirection vers:', url.toString());
                    window.location.href = url.toString();
                } else {
                    alert(data.message || 'Une erreur est survenue');
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            }
        });
    });

    // Réinitialiser le formulaire à l'ouverture du modal
    const categoryModal = document.getElementById('categoryModal');
    if (categoryModal) {
        categoryModal.addEventListener('show.bs.modal', function(event) {
            if (!event.relatedTarget) return; // Si ouvert par le bouton d'édition, ne pas réinitialiser
            
            const form = this.querySelector('form');
            form.reset();
            form.querySelector('#category_id').value = '';
        });
    }

    // Forcer l'activation de l'onglet passé en query (?tab=...)
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab) {
            const tabEl = document.querySelector(`#adminTabs a[href="#${tab}"]`);
            if (tabEl && window.bootstrap && typeof window.bootstrap.Tab === 'function') {
                new window.bootstrap.Tab(tabEl).show();
            }
        }
    } catch (e) {
        console.warn('Activation onglet via ?tab=...: non critique', e);
    }

    // Diagnostics: vérifier la présence du bouton "Nouvelle catégorie" et du modal
    try {
        const headers = Array.from(document.querySelectorAll('.card-header'));
        const catHeader = headers.find(h => /Gestion des catégories/i.test(h.textContent || ''));
        const hasButton = !!(catHeader && catHeader.querySelector('[data-bs-target="#categoryModal"]'));
        const hasModal = !!document.getElementById('categoryModal');
        console.debug('[Catégories][Diag] header trouvé:', !!catHeader, '| bouton présent:', hasButton, '| modal présent:', hasModal, '| url:', window.location.pathname + window.location.search);
    } catch (e) {
        console.warn('[Catégories][Diag] échec détection bouton/modal', e);
    }

    // Met à jour l'URL quand on change d'onglet pour conserver l'onglet actif au reload
    try {
        document.querySelectorAll('#adminTabs a[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                const id = e.target.getAttribute('href').substring(1);
                const url = new URL(window.location);
                url.searchParams.set('tab', id);
                window.history.replaceState({}, '', url);
            });
        });
    } catch (e) {
        console.warn('[Catégories][Diag] échec binding shown.bs.tab', e);
    }
});
