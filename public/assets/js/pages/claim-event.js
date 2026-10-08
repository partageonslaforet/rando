/**
 * Fichier: /assets/js/pages/claim-event.js
 * Rôle: ouvre automatiquement la modal #claimResultModal (résultat de la
 *       revendication) si elle est présente dans la page.
 * Fallback: si bootstrap est absent, la modal reste masquée — le contenu de
 *           la carte en arrière-plan affiche déjà le même message.
 */
(function () {
  'use strict';

  const el = document.getElementById('claimResultModal');
  if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;

  const claimModal = bootstrap.Modal.getOrCreateInstance(el);
  claimModal.show();

  // Bouton auth : ferme la modal claim puis ouvre la modal ciblée
  // (data-auth-target = login si le compte existe, register sinon).
  // L'email destinataire du lien est pré-rempli (data-auth-email).
  const authBtn = el.querySelector('[data-claim-auth]');
  if (authBtn) {
    authBtn.addEventListener('click', () => {
      const targetId = authBtn.dataset.authTarget === 'register' ? 'registerModal' : 'loginModal';
      const email    = authBtn.dataset.authEmail || '';
      const target   = document.getElementById(targetId);
      if (!target) return;

      el.addEventListener('hidden.bs.modal', () => {
        bootstrap.Modal.getOrCreateInstance(target).show();
        const emailInput = target.querySelector('input[type="email"]');
        if (emailInput && email) emailInput.value = email;
      }, { once: true });
      claimModal.hide();

      // Après une inscription réussie, recharge la page : le compte existe désormais,
      // le bouton basculera automatiquement sur « Se connecter ».
      // Délai ~2s pour laisser le toast « e-mail de confirmation envoyé » visible.
      if (targetId === 'registerModal') {
        let submitted = false;
        const form = target.querySelector('#registerForm');
        if (form) form.addEventListener('submit', () => { submitted = true; });
        target.addEventListener('hidden.bs.modal', () => {
          const err = target.querySelector('#registerError');
          const failed = err && err.style.display === 'block';
          if (submitted && !failed) setTimeout(() => window.location.reload(), 2000);
          submitted = false;
        });
      }
    });
  }

  // "Changer de compte" : déconnexion via l'API puis reload —
  // le token reste dans l'URL, la modal repasse en état « non connecté ».
  const logoutBtn = el.querySelector('[data-claim-logout]');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      logoutBtn.disabled = true;
      try {
        await fetch('/api/auth/logout.php');
      } catch (e) { /* même en cas d'échec réseau on recharge */ }
      window.location.reload();
    });
  }
})();
