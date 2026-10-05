document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(location.search);
  const token = params.get('token');
  const errorBox = document.getElementById('manageError');
  const emailInput = document.getElementById('manageEmail');
  const catsContainer = document.getElementById('manageCategories');
  const form = document.getElementById('manageForm');
  const btnUnsub = document.getElementById('btnUnsubscribe');
  const successBox = document.getElementById('manageSuccess');
  const catCount = document.getElementById('catCount');

  function showError(msg){ if(errorBox){ errorBox.textContent=msg; errorBox.classList.remove('d-none'); } }

  // Afficher un toast après rechargement si saved=1
  (function showPostSaveToast(){
    if (params.get('saved') === '1') {
      const toastEl = document.getElementById('prefsToast');
      if (toastEl && window.bootstrap && typeof bootstrap.Toast === 'function') {
        const t = new bootstrap.Toast(toastEl);
        t.show();
      }
      // Nettoyer l'URL pour éviter de réafficher au prochain reload
      params.delete('saved');
      const clean = location.pathname + '?' + params.toString();
      history.replaceState(null, '', clean.endsWith('?') ? location.pathname : clean);
    }
  })();

  try {
    const res = await fetch(`/api/subscribers/manage-data.php?token=${encodeURIComponent(token)}`);
    const data = await res.json();
    if (window.DEBUG_SUBSCRIBERS) {
      console.debug('[subscribers] manage-data response', { ok: data.success, email: data.email, selected: data.selected, frequency: data.frequency });
    }
    if(!data.success){ showError(data.message || 'Token invalide ou expiré'); return; }

    emailInput.value = data.email || '';

    if(Array.isArray(data.categories)){
      catsContainer.innerHTML = data.categories.map(c => {
        const checked = (data.selected||[]).includes(c.id) ? 'checked' : '';
        return `<div class="form-check">
          <input class="form-check-input" type="checkbox" name="categories[]" value="${c.id}" id="mcat_${c.id}" ${checked}>
          <label class="form-check-label" for="mcat_${c.id}">${c.name}</label>
        </div>`;
      }).join('');
      // Mettre à jour le compteur
      const updateCount = () => {
        const count = catsContainer.querySelectorAll('input[name="categories[]"]:checked').length;
        if (catCount) catCount.textContent = count + (count > 1 ? ' sélectionnées' : ' sélectionnée');
      };
      catsContainer.addEventListener('change', updateCount);
      updateCount();

      // Gestion du "Tout cocher / décocher"
      const selectAll = document.getElementById('catSelectAll');
      const allBoxes = () => catsContainer.querySelectorAll('input[name="categories[]"]');

      const setSelectAllState = () => {
        if (!selectAll) return;
        const total = allBoxes().length;
        const checked = catsContainer.querySelectorAll('input[name="categories[]"]:checked').length;
        selectAll.disabled = total === 0;
        selectAll.checked = total > 0 && checked === total;
        selectAll.indeterminate = checked > 0 && checked < total;
      };

      // Quand on modifie une catégorie, synchroniser l'état du bouton global
      catsContainer.addEventListener('change', () => {
        setSelectAllState();
      });

      if (selectAll) {
        selectAll.addEventListener('change', () => {
          const desired = !!selectAll.checked;
          allBoxes().forEach(cb => { cb.checked = desired; });
          updateCount();
          setSelectAllState();
        });
      }

      // État initial
      setSelectAllState();
    } else {
      catsContainer.innerHTML = '<p class="text-muted">Aucune catégorie disponible</p>';
    }

    const f = data.frequency || 'immediate';
    const freqEl = document.getElementById(`freq_${f}`);
    if (freqEl) freqEl.checked = true;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      if (token) fd.set('token', token);
      if (window.DEBUG_SUBSCRIBERS) {
        const cats = Array.from(catsContainer.querySelectorAll('input[name="categories[]"]:checked')).map(el => el.value);
        const freq = form.querySelector('input[name="notification_frequency"]:checked')?.value;
        console.debug('[subscribers] save preferences payload', { email: emailInput.value, categories: cats, frequency: freq });
      }
      const submitBtn = form.querySelector('button[type="submit"]');
      const spinner = submitBtn ? submitBtn.querySelector('.spinner-border') : null;
      const label = submitBtn ? submitBtn.querySelector('.btn-label') : null;
      try {
        if (submitBtn) submitBtn.disabled = true;
        if (spinner) spinner.classList.remove('d-none');
        if (label) label.textContent = 'Enregistrement...';
        const res = await fetch('/api/subscribers/preferences.php', { method:'POST', body: fd });
        let out = null;
        try { out = await res.json(); } catch(e){ out = { success:false, message:'Réponse invalide' }; }
        if(out.success){
          if (window.DEBUG_SUBSCRIBERS) {
            console.debug('[subscribers] save result', out);
          }
          // Recharger avec saved=1 pour afficher le toast après validation/page rafraîchie
          const newParams = new URLSearchParams(location.search);
          newParams.set('saved', '1');
          if (token) newParams.set('token', token);
          location.replace(location.pathname + '?' + newParams.toString());
        } else { showError(out.message || 'Erreur de mise à jour'); }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
        if (spinner) spinner.classList.add('d-none');
        if (label) label.textContent = 'Enregistrer';
      }
    });

    btnUnsub.addEventListener('click', async () => {
      if (window.DEBUG_SUBSCRIBERS) {
        console.debug('[subscribers] unsubscribe click', { email: emailInput.value });
      }
      const fd = new FormData(form);
      if (token) fd.set('token', token);
      const res = await fetch('/api/subscribers/unsubscribe.php', { method:'POST', body: fd });
      const out = await res.json();
      if (window.DEBUG_SUBSCRIBERS) {
        console.debug('[subscribers] unsubscribe result', out);
      }
      if(out.success){
        if (successBox) {
          successBox.textContent = 'Vous avez été désabonné.';
          successBox.classList.remove('d-none');
          successBox.classList.add('alert-success');
        }
        const toastEl = document.getElementById('unsubToast');
        if (toastEl && window.bootstrap && typeof bootstrap.Toast === 'function') {
          const t = new bootstrap.Toast(toastEl);
          t.show();
        }
        // Désactiver tous les champs et boutons du formulaire
        form.querySelectorAll('input, button, a.btn').forEach(el => {
          if (el.tagName === 'A') {
            el.classList.add('disabled');
            el.setAttribute('aria-disabled', 'true');
            el.addEventListener('click', (e) => e.preventDefault());
          } else {
            el.disabled = true;
          }
        });
      } else { showError(out.message || 'Erreur de désinscription'); }
    });

  } catch (e) { showError('Erreur serveur'); }
});
