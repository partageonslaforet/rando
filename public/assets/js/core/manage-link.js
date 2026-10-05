document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('manageLinkForm');
  const modalEl = document.getElementById('manageSubscribersModal');
  if (!form || !modalEl) return;

  const sendBtn = form.querySelector('button[type="submit"]');
  const spinner = sendBtn ? sendBtn.querySelector('.spinner-border') : null;
  const label = sendBtn ? sendBtn.querySelector('.btn-label') : null;
  const errorBox = document.getElementById('manageLinkError');
  const successBox = document.getElementById('manageLinkSuccess');

  function show(box, msg) { if (box) { box.textContent = msg; box.classList.remove('d-none'); } }
  function hide(...boxes) { boxes.forEach(b => b && b.classList.add('d-none')); }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    hide(errorBox, successBox);
    if (spinner) spinner.classList.remove('d-none');
    if (label) label.textContent = 'Envoi...';
    if (sendBtn) sendBtn.disabled = true;

    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
      const text = await res.text();
      let data = {}; try { data = JSON.parse(text); } catch {}
      if (res.ok) {
        show(successBox, data.message || 'Si un compte existe, un email a été envoyé.');
        form.reset();
      } else {
        show(errorBox, data.message || 'Erreur lors de l\'envoi. Réessayez.');
      }
    } catch (_) {
      show(errorBox, 'Erreur réseau. Réessayez.');
    } finally {
      if (spinner) spinner.classList.add('d-none');
      if (label) label.textContent = 'Recevoir mon lien';
      if (sendBtn) sendBtn.disabled = false;
    }
  });

  modalEl.addEventListener('hidden.bs.modal', () => {
    form.reset();
    hide(errorBox, successBox);
    if (spinner) spinner.classList.add('d-none');
    if (label) label.textContent = 'Recevoir mon lien';
    if (sendBtn) sendBtn.disabled = false;
  });
});
