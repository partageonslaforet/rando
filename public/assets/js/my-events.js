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
});
