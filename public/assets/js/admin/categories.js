document.addEventListener('DOMContentLoaded', function() {
    // Initialisation de Sortable pour le réordonnancement
    const tbody = document.getElementById('categoriesTableBody');
    if (tbody) {
        new Sortable(tbody, {
            handle: '.handle',
            animation: 150,
            onEnd: function() {
                const rows = tbody.getElementsByTagName('tr');
                const orderData = Array.from(rows).map((row, index) => ({
                    id: row.dataset.id,
                    order: index
                }));

                // Envoyer le nouvel ordre au serveur
                fetch('/api/admin/categories/reorder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(orderData)
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Erreur lors de la mise à jour de l\'ordre');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de la mise à jour de l\'ordre');
                });
            }
        });
    }

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
                    window.location.href = '/pages/admin/users.php?tab=categories';
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
                    window.location.reload();
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
});
