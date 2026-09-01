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
});
