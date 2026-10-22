/**
 * localisation: public/assets/js/core/guide.js
 * Role: Guide de démarrage — navigation des étapes + proposition à la première visite.
 *   - Lien navbar (#guideModal) : ouverture manuelle, toujours disponible.
 *   - #guidePrompt (home uniquement) : proposé à chaque session tant que
 *     l'utilisateur n'a pas cliqué « × » (plf_guide_dismissed=1).
 *     « Me rappeler plus tard » = masqué pour la session en cours (sessionStorage).
 * Dépendances: Bootstrap 5 (modal), templates/modals/guide.php, guide.css
 */
(function () {
    'use strict';

    var KEY_DISMISSED = 'plf_guide_dismissed'; // localStorage : ne plus jamais proposer
    var KEY_LATER     = 'plf_guide_later';     // sessionStorage : reproposer à la prochaine session
    var PROMPT_DELAY  = 1500;                  // ms avant affichage du panneau

    function isHome() {
        var p = window.location.pathname;
        return p === '/' || p === '/index.php';
    }

    // --- Navigation interne de la modale -----------------------------------
    function initSteps() {
        var modal = document.getElementById('guideModal');
        if (!modal) return;

        var steps    = modal.querySelectorAll('.guide-step');
        var prev     = document.getElementById('guidePrev');
        var next     = document.getElementById('guideNext');
        var counter  = document.getElementById('guideCounter');
        var progress = document.getElementById('guideProgressBar');
        var current  = 0;
        var total    = steps.length;
        if (!total) return;

        function render() {
            steps.forEach(function (s, i) {
                s.classList.toggle('is-active', i === current);
            });
            if (counter)  counter.textContent = (current + 1) + ' / ' + total;
            if (progress) progress.style.width = Math.round(((current + 1) / total) * 100) + '%';
            if (prev)     prev.disabled = (current === 0);
            if (next)     next.classList.toggle('d-none', current === total - 1);
        }

        if (prev) {
            prev.addEventListener('click', function () {
                if (current > 0) { current--; render(); }
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                if (current < total - 1) { current++; render(); }
            });
        }

        // Réinitialise à l'étape 1 à chaque ouverture
        modal.addEventListener('show.bs.modal', function () {
            current = 0;
            render();
        });

        render();
    }

    // --- Panneau de proposition (première visite, home uniquement) ----------
    function initPrompt() {
        if (!isHome()) return;

        var prompt = document.getElementById('guidePrompt');
        var modal  = document.getElementById('guideModal');
        if (!prompt || !modal) return;

        // Déjà refusé définitivement ou déjà reporté cette session → rien
        try {
            if (localStorage.getItem(KEY_DISMISSED) === '1') return;
            if (sessionStorage.getItem(KEY_LATER) === '1') return;
        } catch (e) { /* storage indisponible (mode privé) : on propose quand même */ }

        function hide() {
            prompt.classList.remove('is-visible');
            prompt.hidden = true;
        }

        window.setTimeout(function () {
            // Ne pas proposer si le guide (ou toute modale) est déjà ouvert
            if (document.querySelector('.modal.show')) return;
            prompt.hidden = false;
            prompt.classList.add('is-visible');
        }, PROMPT_DELAY);

        // Masquer le panneau dès qu'une modale s'ouvre (backdrop la recouvre sinon)
        document.addEventListener('show.bs.modal', hide);

        var openBtn    = document.getElementById('guidePromptOpen');
        var laterBtn   = document.getElementById('guidePromptLater');
        var dismissBtn = document.getElementById('guidePromptDismiss');

        if (openBtn) {
            openBtn.addEventListener('click', function () {
                hide();
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        }

        if (laterBtn) {
            laterBtn.addEventListener('click', function () {
                try { sessionStorage.setItem(KEY_LATER, '1'); } catch (e) {}
                hide();
            });
        }

        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                try { localStorage.setItem(KEY_DISMISSED, '1'); } catch (e) {}
                hide();
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSteps();
        initPrompt();
    });
})();
