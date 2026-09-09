<!-- Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="auth-icon" aria-hidden="true">
                    <i class="bi bi-send"></i>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <h5 class="modal-title" id="contactModalLabel">Contactez-nous</h5>
                
            </div>
            <div class="modal-body">
                <form id="contactForm" action="/api/contact/send.php" method="POST">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="subject" class="form-label">Sujet</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="contactCopy" name="copy" value="1">
                        <label class="form-check-label" for="contactCopy">Recevoir une copie de ce message</label>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn-send">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                            <span class="btn-label">Envoyer</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
