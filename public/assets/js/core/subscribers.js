/**
 * Fichier: /assets/js/core/subscribers.js
 * Rôle: Gestion du formulaire d'abonnement aux événements
 * Utilisation: Charge les catégories, gère l'envoi du formulaire
 * Dépendances: EventsAPI pour les catégories
 */

document.addEventListener('DOMContentLoaded', function() {
    const subscribersForm = document.getElementById('subscribersForm');
    const subscribersModal = document.getElementById('subscribersModal');
    const categoriesContainer = document.getElementById('categoriesContainer');
    const errorBox = document.getElementById('subscribersError');

    // Résultat de la vérification email (redirection depuis /api/subscribers/verify.php)
    const subVerifyParam = new URLSearchParams(window.location.search).get('sub_verify');
    const subVerifyModal = document.getElementById('subVerifyModal');
    if (subVerifyParam && subVerifyModal) {
        const isOk = subVerifyParam === 'success';
        const messages = {
            success: 'Votre adresse email est confirmée. Vous recevrez désormais les notifications des événements qui vous intéressent.',
            invalid: 'Ce lien de vérification est invalide ou a déjà été utilisé.',
            missing: 'Le lien de vérification est incomplet.',
            error:   'Une erreur est survenue lors de la vérification. Veuillez réessayer.'
        };

        const iconWrap = subVerifyModal.querySelector('.auth-icon');
        if (iconWrap) {
            const icon = iconWrap.querySelector('i');
            if (icon) icon.className = isOk ? 'bi bi-check-circle' : 'bi bi-x-circle';
            iconWrap.classList.toggle('is-error', !isOk);
        }

        const title = document.getElementById('subVerifyModalLabel');
        if (title) title.textContent = isOk ? 'Adresse confirmée' : 'Vérification échouée';

        const msg = document.getElementById('subVerifyMessage');
        if (msg) msg.textContent = messages[subVerifyParam] || messages.error;

        const btn = document.getElementById('subVerifyBtn');
        if (btn) btn.textContent = isOk ? 'Découvrir les événements' : 'Fermer';

        new bootstrap.Modal(subVerifyModal).show();
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (!subscribersForm) return;

    // Charger les catégories
    async function loadCategories() {
        try {
            const categories = await EventsAPI.getCategories();
            
            if (!Array.isArray(categories) || categories.length === 0) {
                categoriesContainer.innerHTML = '<p class="text-muted">Aucune catégorie disponible</p>';
                return;
            }

            let html = '';
            categories.forEach(cat => {
                html += `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="categories[]" value="${cat.id}" id="cat_${cat.id}">
                        <label class="form-check-label" for="cat_${cat.id}">
                            ${cat.icon ? `<i class="bi ${cat.icon}"></i> ` : ''}
                            ${escapeHtml(cat.name)}
                        </label>
                    </div>
                `;
            });
            categoriesContainer.innerHTML = html;

            // Checkbox "Toutes les catégories"
            const allBox = document.getElementById('cat_all');
            if (allBox) {
                allBox.addEventListener('change', function() {
                    categoriesContainer.querySelectorAll('input[name="categories[]"]')
                        .forEach(cb => { cb.checked = allBox.checked; });
                });
                categoriesContainer.querySelectorAll('input[name="categories[]"]').forEach(cb => {
                    cb.addEventListener('change', function() {
                        if (!cb.checked) {
                            allBox.checked = false;
                        } else {
                            allBox.checked = categoriesContainer
                                .querySelectorAll('input[name="categories[]"]:not(:checked)').length === 0;
                        }
                    });
                });
            }
        } catch (error) {
            categoriesContainer.innerHTML = '<p class="text-danger">Erreur: ' + error.message + '</p>';
        }
    }

    // Charger les catégories au démarrage
    loadCategories();

    // Gérer le submit du formulaire
    subscribersForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const sendBtn = subscribersForm.querySelector('button[type="submit"]');
        const spinner = sendBtn ? sendBtn.querySelector('.spinner-border') : null;
        const btnLabel = sendBtn ? sendBtn.querySelector('.btn-label') : null;

        // Vérifier qu'au moins une catégorie est sélectionnée
        const categoryCheckboxes = subscribersForm.querySelectorAll('input[name="categories[]"]');
        const selectedCategories = Array.from(categoryCheckboxes).filter(cb => cb.checked);
        
        if (selectedCategories.length === 0) {
            if (errorBox) {
                errorBox.textContent = 'Veuillez sélectionner au moins une catégorie';
                errorBox.style.display = 'block';
            }
            return;
        }
        if (errorBox) errorBox.style.display = 'none';

        // État loading
        if (spinner) spinner.classList.remove('d-none');
        if (btnLabel) btnLabel.textContent = 'Inscription…';
        if (sendBtn) sendBtn.disabled = true;

        try {
            const formData = new FormData(subscribersForm);
            
            const response = await fetch('/api/subscribers/register.php', {
                method: 'POST',
                body: formData
            });

            let data;
            try {
                const text = await response.text();
                data = JSON.parse(text);
            } catch (parseError) {
                throw new Error('Erreur serveur: réponse invalide');
            }

            if (data.success) {
                if (errorBox) errorBox.style.display = 'none';
                subscribersForm.reset();
                const allBox = document.getElementById('cat_all');
                if (allBox) allBox.checked = false;

                // Fermer le modal
                const modal = bootstrap.Modal.getInstance(subscribersModal);
                if (modal) modal.hide();

                if (typeof showToast === 'function') {
                    showToast('Inscription enregistrée. Vous allez recevoir un email de validation.', 'success');
                }
            } else {
                if (errorBox) {
                    errorBox.textContent = data.message || 'Une erreur est survenue';
                    errorBox.style.display = 'block';
                }
            }
        } catch (error) {
            if (errorBox) {
                errorBox.textContent = error.message || 'Erreur lors de l\'inscription. Veuillez réessayer.';
                errorBox.style.display = 'block';
            }
        } finally {
            // Réinitialiser l'état
            if (spinner) spinner.classList.add('d-none');
            if (btnLabel) btnLabel.textContent = 'S\'abonner';
            if (sendBtn) sendBtn.disabled = false;
        }
    });

    // Réinitialiser le formulaire quand le modal se ferme
    if (subscribersModal) {
        subscribersModal.addEventListener('hidden.bs.modal', function() {
            subscribersForm.reset();
        });
    }
});

// Fonction utilitaire pour échapper le HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
