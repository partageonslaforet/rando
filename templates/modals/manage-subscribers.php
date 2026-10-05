<?php
/**
 * localisation: templates/modals/manage-subscribers.php
 * Rôle: Modale – Envoi d’un lien de gestion des abonnements
 */
?>
<div class="modal fade auth-modal" id="manageSubscribersModal" tabindex="-1" aria-labelledby="manageSubscribersModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="auth-icon" aria-hidden="true">
          <i class="bi bi-gear"></i>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <h5 class="modal-title" id="manageSubscribersModalLabel">Gérer mes abonnements</h5>
        <p class="auth-intro">Recevez un lien de gestion sécurisé pour modifier vos préférences ou vous désabonner.</p>

        <div class="auth-alert auth-alert-danger d-none" id="manageLinkError"></div>
        <div class="auth-alert auth-alert-success d-none" id="manageLinkSuccess"></div>

        <form id="manageLinkForm" action="/api/subscribers/request-manage-link.php" method="POST">
          <div class="mb-3">
            <label for="manageLinkEmail" class="form-label">Adresse email</label>
            <input type="email" class="form-control" id="manageLinkEmail" name="email" required placeholder="vous@exemple.be">
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-auth">
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              <span class="btn-label">Recevoir mon lien</span>
            </button>
          </div>
        </form>

        <p class="small text-muted mt-3 mb-0">Nous envoyons un message uniquement si l’adresse est vérifiée. Le lien expire dans 24h.</p>
      </div>
    </div>
  </div>
</div>
