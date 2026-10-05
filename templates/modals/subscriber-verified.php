<?php
/**
 * localisation: templates/modals/subscriber-verified.php
 * Role: Modale : Résultat de la vérification d'email d'abonnement
 * Usage: Affichée automatiquement par subscribers.js quand l'URL contient ?sub_verify=...
 *        (redirection depuis api/subscribers/verify.php)
 * Dépendances: Bootstrap modal, subscribers.js
 */
?>
<!-- Subscriber Verification Result Modal -->
<div class="modal fade auth-modal" id="subVerifyModal" tabindex="-1" aria-labelledby="subVerifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="auth-icon" aria-hidden="true">
                    <i class="bi bi-bell"></i>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <h5 class="modal-title" id="subVerifyModalLabel">Vérification de l'email</h5>
                <p class="auth-intro" id="subVerifyMessage"></p>
                <div class="d-grid">
                    <button type="button" class="btn btn-auth" data-bs-dismiss="modal" id="subVerifyBtn">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>
