/**
 * Fichier: /assets/js/core/contact.js
 * Rôle: Gestion du formulaire de contact en modal (états UI, envoi, toasts).
 * Utilisation: écoute le submit de #contactForm lorsqu’il est présent.
 * Dépendances: Fetch API (/api/contact/send.php), Bootstrap Toast (optionnel via showToast).
 */
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    const contactModal = document.getElementById('contactModal');
    
    if (contactForm) {
        const sendBtn   = contactForm.querySelector('.btn-send');
        const cancelBtn = contactForm.querySelector('.btn-cancel');
        const spinner   = sendBtn ? sendBtn.querySelector('.spinner-border') : null;
        const btnLabel  = sendBtn ? sendBtn.querySelector('.btn-label') : null;

        const setLoading = (isLoading) => {
            if (!sendBtn) return;
            if (isLoading) {
                if (spinner) spinner.classList.remove('d-none');
                if (btnLabel) btnLabel.textContent = 'Envoi…';
                sendBtn.disabled = true;
                if (cancelBtn) cancelBtn.disabled = true;
                contactForm.setAttribute('aria-busy', 'true');
            } else {
                if (spinner) spinner.classList.add('d-none');
                if (btnLabel) btnLabel.textContent = 'Envoyer';
                sendBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;
                contactForm.removeAttribute('aria-busy');
            }
        };

        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            setLoading(true);
            
            const formData = new FormData(contactForm);
            
            fetch('/api/contact/send.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json().catch(() => ({ success: false, error: 'Réponse invalide' }));
                if (data.success) {
                    showToast(data.message || 'Message envoyé. Merci !', 'success');
                    contactForm.reset();
                    const modalEl = document.getElementById('contactModal');
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                } else {
                    showToast(data.error || 'Une erreur est survenue', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Une erreur est survenue lors de l\'envoi du message', 'danger');
            })
            .finally(() => setLoading(false));
        });
    }

    // Diagnostics largeur modale de contact
    if (contactModal) {
        contactModal.addEventListener('shown.bs.modal', () => {
            try {
                const dialog = contactModal.querySelector('.modal-dialog');
                if (!dialog) return;
                const cs = getComputedStyle(dialog);
                const varWidth = cs.getPropertyValue('--bs-modal-width');
                const maxWidth = cs.getPropertyValue('max-width');
                const width = cs.getPropertyValue('width');
                const rect = dialog.getBoundingClientRect();

                console.group('[ContactModal] Diagnostics largeur');
                console.log('Computed --bs-modal-width:', varWidth);
                console.log('Computed max-width:', maxWidth);
                console.log('Computed width:', width);
                console.log('Rect width(px):', rect.width);

                // Rechercher les règles CSS pertinentes dans les styles chargés
                const targets = [
                    '.auth-modal .modal-dialog',
                    '#contactModal .modal-dialog',
                    '#contactModal.auth-modal .modal-dialog'
                ];
                for (const t of targets) {
                    try {
                        Array.from(document.styleSheets).forEach((sheet) => {
                            let rules;
                            try { rules = sheet.cssRules || []; } catch (e) { return; } // CORS
                            Array.from(rules).forEach((rule) => {
                                if (rule.selectorText && rule.selectorText.includes(t)) {
                                    console.log('Rule match:', t, 'from', sheet.href || 'inline', '=>', rule.cssText);
                                }
                            });
                        });
                    } catch (e) { /* ignore */ }
                }
                console.groupEnd();
            } catch (e) {
                console.warn('[ContactModal] Diagnostics échoués:', e);
            }
        });
    }
});

function showToast(message, type = 'success') {
    try {
        // Crée un conteneur global si absent
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(container);
        }

        const wrapper = document.createElement('div');
        const t = String(type || 'success');
        let cls;
        if (t.startsWith('text-bg-')) {
            cls = t;
        } else if (t.startsWith('bg-')) {
            cls = `text-${t}`; // e.g., bg-success -> text-bg-success
        } else {
            cls = `text-bg-${t}`; // e.g., success -> text-bg-success
        }
        wrapper.className = `toast align-items-center ${cls} border-0`;
        wrapper.setAttribute('role', 'status');
        wrapper.setAttribute('aria-live', 'polite');
        wrapper.setAttribute('aria-atomic', 'true');
        wrapper.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
            </div>`;
        container.appendChild(wrapper);
        const toast = new bootstrap.Toast(wrapper, { delay: 3500 });
        toast.show();
    } catch (e) {
        // Fallback si Bootstrap non dispo
        alert(message);
    }
}
