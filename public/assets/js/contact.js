document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    
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
        wrapper.className = `toast align-items-center text-bg-${type} border-0`;
        wrapper.setAttribute('role', 'status');
        wrapper.setAttribute('aria-live', 'polite');
        wrapper.setAttribute('aria-atomic', 'true');
        wrapper.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>`;
        container.appendChild(wrapper);
        const toast = new bootstrap.Toast(wrapper, { delay: 3500 });
        toast.show();
    } catch (e) {
        // Fallback si Bootstrap non dispo
        alert(message);
    }
}
