<?php
/**
 * localisation: templates/modals/subscribers.php
 * Role: Modale : Abonnement aux notifications d'événements
 * Usage: Fenetre modale pour s'abonner aux événements
 * Dépendances: Aucune
 */
?>
<!-- Subscribers Modal -->
<div class="modal fade auth-modal" id="subscribersModal" tabindex="-1" aria-labelledby="subscribersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="auth-icon" aria-hidden="true">
                    <i class="bi bi-bell"></i>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <h5 class="modal-title" id="subscribersModalLabel">Recevez les nouveaux événements</h5>
                <p class="auth-intro">Restez informé des nouveaux événements qui vous intéressent.</p>
                <div class="auth-alert auth-alert-danger is-hidden" id="subscribersError"></div>
                
                <form id="subscribersForm" action="/api/subscribers/register.php" method="POST">
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="subscriberEmail" class="form-label">Adresse email</label>
                        <input type="email" class="form-control" id="subscriberEmail" name="email" required placeholder="vous@exemple.be">
                        <small class="form-text text-muted">Un email de confirmation vous sera envoyé.</small>
                    </div>

                    <!-- Catégories -->
                    <div class="mb-3">
                        <label class="form-label d-block mb-2">Catégories d'intérêt</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="cat_all">
                            <label class="form-check-label fw-semibold" for="cat_all">Toutes les catégories</label>
                        </div>
                        <div id="categoriesContainer" class="categories-checkboxes">
                            <!-- Rempli dynamiquement par JS -->
                        </div>
                        <small class="form-text text-muted d-block mt-2">Sélectionnez au moins une catégorie.</small>
                    </div>

                    <!-- Fréquence -->
                    <div class="mb-3">
                        <label class="form-label d-block mb-2">Fréquence des notifications</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="notification_frequency" id="freqImmediate" value="immediate" checked>
                            <label class="form-check-label" for="freqImmediate">
                                Immédiat (dès qu'un événement est publié)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="notification_frequency" id="freqWeekly" value="weekly">
                            <label class="form-check-label" for="freqWeekly">
                                Hebdomadaire (résumé chaque lundi)
                            </label>
                        </div>
                    </div>

                    <!-- Bouton -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-auth">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                            <span class="btn-label">S'abonner</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
