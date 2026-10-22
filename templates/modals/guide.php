<?php
/**
 * localisation: templates/modals/guide.php
 * Role: Modale : Guide de démarrage + panneau de proposition première visite
 * Usage: Tutoriel en 6 étapes (inscription -> publication d'un événement).
 *        #guidePrompt est affiché par guide.js sur la home uniquement.
 * Dépendances: /assets/css/components/guide.css, /assets/js/core/guide.js
 */
?>
<link rel="stylesheet" href="/assets/css/components/guide.css?v=3">

<!-- Modale Guide -->
<div class="modal fade auth-modal" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="auth-icon" aria-hidden="true"><i class="bi bi-compass"></i></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <h5 class="modal-title" id="guideModalLabel">Guide de démarrage</h5>
                <p class="auth-intro">De l'inscription à la publication de votre premier événement.</p>

                <div class="guide-progress" aria-hidden="true">
                    <div class="guide-progress-bar" id="guideProgressBar"></div>
                </div>

                <div class="guide-steps">

                    <div class="guide-step is-active" data-step="1">
                        <h6 class="guide-step-title"><i class="bi bi-flag"></i> Bienvenue</h6>
                        <p>Partageons La Forêt vous permet de <strong>publier gratuitement</strong> vos randonnées, marches et activités en pleine nature. Ce guide vous accompagne en 6 étapes :</p>
                        <ol class="guide-list">
                            <li>Créer un compte</li>
                            <li>Confirmer votre e-mail</li>
                            <li>Compléter votre profil organisateur</li>
                            <li>Créer votre événement</li>
                            <li>Enregistrer en brouillon ou publier</li>
                        </ol>
                        <img src="/assets/img/guide/step1-welcome.png" alt="Page d'accueil du site" class="guide-screenshot" loading="lazy" onerror="this.classList.add('d-none')">
                    </div>

                    <div class="guide-step" data-step="2">
                        <h6 class="guide-step-title"><i class="bi bi-person-plus"></i> Créer un compte</h6>
                        <p>Cliquez sur <strong>Connexion</strong> dans la barre du haut, puis sur <strong>« Créer un compte »</strong> en bas de la fenêtre. Renseignez :</p>
                        <ul class="guide-list">
                            <li>Votre <strong>nom d'utilisateur</strong></li>
                            <li>Votre <strong>adresse e-mail</strong></li>
                            <li>Un <strong>mot de passe</strong> de 8 caractères minimum</li>
                        </ul>
                        <img src="/assets/img/guide/step2-register.png" alt="Modale d'inscription" class="guide-screenshot" loading="lazy" onerror="this.classList.add('d-none')">
                    </div>

                    <div class="guide-step" data-step="3">
                        <h6 class="guide-step-title"><i class="bi bi-envelope-check"></i> Confirmer votre e-mail</h6>
                        <p>Un e-mail de confirmation vous est envoyé immédiatement. <strong>Cliquez sur le lien</strong> qu'il contient pour activer votre compte.</p>
                        <ul class="guide-list">
                            <li>Pensez à vérifier vos <strong>courriers indésirables (spam)</strong></li>
                            <li>Sans cette confirmation, vous ne pourrez pas vous connecter</li>
                        </ul>
                        <img src="/assets/img/guide/step3-email.png" alt="E-mail de confirmation" class="guide-screenshot" loading="lazy" onerror="this.classList.add('d-none')">
                    </div>

                    <div class="guide-step" data-step="4">
                        <h6 class="guide-step-title"><i class="bi bi-person-badge"></i> Profil organisateur</h6>
                        <p>Une fois connecté, ouvrez <strong>Mon Compte → Mon Profil</strong>, onglet <strong>Organisateur</strong>. Ce profil affiche le nom et le logo de votre club ou association sur vos événements.</p>
                        <ul class="guide-list">
                            <li>Optionnel mais <strong>fortement recommandé</strong> pour la visibilité</li>
                            <li>La pastille <span class="org-badge">!</span> dans le menu vous rappelle de le créer</li>
                        </ul>
                        <img src="/assets/img/guide/step4-organizer.png" alt="Onglet organisateur du profil" class="guide-screenshot" loading="lazy" onerror="this.classList.add('d-none')">
                    </div>

                    <div class="guide-step" data-step="5">
                        <h6 class="guide-step-title"><i class="bi bi-calendar-plus"></i> Créer votre événement</h6>
                        <p>Cliquez sur <strong>Créer un Événement</strong> dans la barre du haut. Le formulaire se déroule en 3 étapes :</p>
                        <ul class="guide-list">
                            <li><strong>1. L'événement</strong> : titre, description, catégories, date et horaires d'inscription, point de rendez-vous</li>
                            <li><strong>2. Parcours</strong> : un ou plusieurs parcours — importez un <strong>fichier GPX</strong> pour remplir automatiquement distance et dénivelé</li>
                            <li><strong>3. Photos et aperçu</strong> : photo de couverture + galerie, puis vérifiez le rendu</li>
                        </ul>
                        <img src="/assets/img/guide/step5-create-event.png" alt="Formulaire de création d'événement" class="guide-screenshot" loading="lazy" onerror="this.classList.add('d-none')">
                    </div>

                    <div class="guide-step" data-step="6">
                        <h6 class="guide-step-title"><i class="bi bi-send-check"></i> Brouillon &amp; publication</h6>
                        <ul class="guide-list">
                            <li><strong>Brouillon</strong> : vous pouvez enregistrer à tout moment et reprendre plus tard depuis <strong>Mes Événements</strong></li>
                            <li><strong>Publier</strong> : votre événement est soumis à une <strong>validation</strong> avant d'apparaître sur le site — vous recevez un e-mail à chaque étape</li>
                            <li><strong>Après publication</strong> : vous pouvez <strong>modifier ou annuler</strong> votre événement à tout moment depuis <strong>Mes Événements</strong> — les participants inscrits sont prévenus automatiquement</li>
                        </ul>
                        <p>C'est parti ! Retrouvez ce guide à tout moment via l'icône <i class="bi bi-question-circle"></i> dans la barre du haut.</p>
                        <img src="/assets/img/guide/step6-publish.png" alt="Boutons brouillon et publication" class="guide-screenshot" loading="lazy" onerror="this.classList.add('d-none')">
                    </div>

                </div>
            </div>
            <div class="modal-footer guide-footer">
                <button type="button" class="btn btn-outline-secondary" id="guidePrev" disabled>
                    <i class="bi bi-chevron-left"></i> Précédent
                </button>
                <span class="guide-counter" id="guideCounter">1 / 6</span>
                <button type="button" class="btn btn-auth" id="guideNext">Suivant <i class="bi bi-chevron-right"></i></button>
                <button type="button" class="btn btn-auth btn-finish" id="guideFinish" data-bs-dismiss="modal">Terminer</button>
            </div>
        </div>
    </div>
</div>

<!-- Panneau de proposition : visiteurs non connectés uniquement, affiché par guide.js sur la home -->
<?php if (empty($_SESSION['user_id'])): ?>
<div id="guidePrompt" class="guide-prompt" hidden>
    <button type="button" class="guide-prompt-close" id="guidePromptDismiss" aria-label="Ne plus me proposer">
        <i class="bi bi-x-lg"></i>
    </button>
    <div class="guide-prompt-body">
        <div class="guide-prompt-icon" aria-hidden="true"><i class="bi bi-compass"></i></div>
        <div class="guide-prompt-text">
            <strong>Nouveau ici ?</strong>
            <p>Suivez le guide pour publier votre premier événement pas à pas.</p>
        </div>
    </div>
    <div class="guide-prompt-actions">
        <button type="button" class="btn btn-auth btn-sm" id="guidePromptOpen">Voir le guide</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="guidePromptLater">Me rappeler plus tard</button>
    </div>
</div>
<?php endif; ?>
