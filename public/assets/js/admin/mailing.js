/**
 * localisation: public/assets/js/admin/mailing.js
 * Role: Module publipostage admin — import CSV (analyse + import),
 *       gestion des contacts (toggle/désinscrire/supprimer) et campagne d'envoi.
 * Pattern identique à sponsors.js — fetch JSON, rechargement via #section.
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    function backToContacts() {
        const url = new URL(window.location.href);
        url.hash = 'section-mailing-contacts';
        window.location.href = url.toString();
        window.location.reload();
    }

    function backToCampaign() {
        const url = new URL(window.location.href);
        url.hash = 'section-mailing-campaign';
        window.location.href = url.toString();
        window.location.reload();
    }

    const CAMPAIGNS = {};
    (window.MAILING_CAMPAIGNS || []).forEach(function(c) { CAMPAIGNS[c.id] = c; });

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    // Nombre d'abonnés réellement cochés — toutes pages confondues (toutes les lignes sont dans le DOM)
    function activeCount() {
        return document.querySelectorAll('#mailingContactsBody .toggle-mailing:checked').length;
    }

    const FLAG_LABELS = {
        ok: '<span class="badge bg-success">Nouveau</span>',
        duplicate: '<span class="badge bg-warning text-dark">Doublon fichier</span>',
        no_email: '<span class="badge bg-secondary">Sans email</span>',
        invalid: '<span class="badge bg-danger">Email invalide</span>'
    };

    // ==================== Import CSV ====================

    const analyzeBtn = document.getElementById('mailingAnalyzeBtn');
    const fileInput = document.getElementById('mailingCsvFile');
    const previewBox = document.getElementById('mailingPreview');

    if (analyzeBtn && fileInput) {
        analyzeBtn.addEventListener('click', async function() {
            if (!fileInput.files || !fileInput.files[0]) {
                alert('Choisissez un fichier CSV');
                return;
            }
            const fd = new FormData();
            fd.append('csv', fileInput.files[0]);
            analyzeBtn.disabled = true;
            analyzeBtn.textContent = 'Analyse…';

            try {
                const response = await fetch('/api/admin/mailing/import.php?mode=preview', { method: 'POST', body: fd });
                const data = await response.json();

                if (!data.success) {
                    alert(data.message || 'Analyse impossible');
                    return;
                }

                const s = data.data.stats;
                document.getElementById('mailingPreviewStats').textContent =
                    `${s.emails} emails uniques — ${s.new} nouveaux, ${s.existing} déjà en base` +
                    (s.no_email ? ` — ${s.no_email} ligne(s) sans email` : '') +
                    (s.invalid ? ` — ${s.invalid} email(s) invalide(s)` : '') +
                    (s.file_dupes ? ` — ${s.file_dupes} doublon(s) dans le fichier` : '');

                const tbody = previewBox.querySelector('tbody');
                tbody.innerHTML = data.data.rows.map(function(r) {
                    const checkable = (r.flag === 'ok' || r.flag === 'duplicate') && r.email;
                    return '<tr>' +
                        '<td class="text-center">' + (checkable ? '<input type="checkbox" class="form-check-input import-check" data-email="' + esc(r.email) + '" checked>' : '') + '</td>' +
                        '<td>' + esc(r.line) + '</td>' +
                        '<td>' + esc(r.club) + '</td>' +
                        '<td>' + esc(r.contact) + '</td>' +
                        '<td>' + esc(r.lieu) + '</td>' +
                        '<td>' + esc(r.type) + '</td>' +
                        '<td>' + esc(r.email) + '</td>' +
                        '<td>' + (FLAG_LABELS[r.flag] || esc(r.flag)) + '</td>' +
                    '</tr>';
                }).join('');

                const checkAll = document.getElementById('importCheckAll');
                if (checkAll) checkAll.checked = true;
                tbody.querySelectorAll('.import-check').forEach(function(cb) {
                    cb.addEventListener('change', updateImportCount);
                });
                updateImportCount();
                previewBox.classList.remove('d-none');
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur de communication avec le serveur');
            } finally {
                analyzeBtn.disabled = false;
                analyzeBtn.innerHTML = '<i class="bi bi-search"></i> Analyser';
            }
        });
    }

    // Emails uniques actuellement cochés dans la preview
    function selectedImportEmails() {
        const emails = new Set();
        if (previewBox) {
            previewBox.querySelectorAll('.import-check:checked').forEach(function(cb) {
                emails.add(cb.dataset.email);
            });
        }
        return [...emails];
    }

    function updateImportCount() {
        const importBtn = document.getElementById('mailingImportBtn');
        if (importBtn) {
            importBtn.textContent = `Importer ${selectedImportEmails().length} contact(s)`;
        }
    }

    const checkAll = document.getElementById('importCheckAll');
    if (checkAll && previewBox) {
        checkAll.addEventListener('change', function() {
            previewBox.querySelectorAll('.import-check').forEach(cb => { cb.checked = checkAll.checked; });
            updateImportCount();
        });
    }

    const importBtn = document.getElementById('mailingImportBtn');
    if (importBtn) {
        importBtn.addEventListener('click', async function() {
            const emails = selectedImportEmails();
            if (!emails.length) {
                alert('Aucune ligne sélectionnée');
                return;
            }
            if (!confirm(`Importer ${emails.length} contact(s) sélectionné(s) ?`)) return;
            importBtn.disabled = true;
            try {
                const response = await fetch('/api/admin/mailing/import.php?mode=commit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ emails: emails })
                });
                const data = await response.json();
                if (data.success) {
                    backToContacts();
                } else {
                    alert(data.message || 'Import impossible');
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur de communication avec le serveur');
            } finally {
                importBtn.disabled = false;
            }
        });
    }

    // ==================== Contacts : toggle + suppression ====================

    document.querySelectorAll('.toggle-mailing').forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const active = this.checked;
            try {
                const response = await fetch('/api/admin/mailing/toggle.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: this.dataset.id, active: active })
                });
                const data = await response.json();
                if (!data.success) {
                    this.checked = !active;
                    alert(data.message || 'Une erreur est survenue');
                } else {
                    window.MAILING_ACTIVE_COUNT = activeCount();
                }
            } catch (error) {
                console.error('Erreur:', error);
                this.checked = !active;
                alert('Une erreur est survenue');
            }
        });
    });

    document.querySelectorAll('.delete-mailing').forEach(button => {
        button.addEventListener('click', async function() {
            if (!confirm('Supprimer ce contact ?')) return;
            try {
                const response = await fetch('/api/admin/mailing/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: this.dataset.id })
                });
                const data = await response.json();
                if (data.success) {
                    backToContacts();
                } else {
                    alert(data.message || 'Une erreur est survenue');
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            }
        });
    });

    // ==================== Contacts : pagination ====================

    const contactsBody = document.getElementById('mailingContactsBody');
    const contactsPager = document.getElementById('mailingContactsPager');
    if (contactsBody && contactsPager) {
        const PAGE_SIZE = 25;
        let currentPage = 1;
        // Lignes de données (exclut la ligne "Aucun contact" à colspan)
        const dataRows = Array.from(contactsBody.querySelectorAll('tr')).filter(function(r) {
            return !r.querySelector('td[colspan]');
        });

        function pageCount() {
            return Math.max(1, Math.ceil(dataRows.length / PAGE_SIZE));
        }

        function renderPager() {
            const pages = pageCount();
            if (pages <= 1) {
                contactsPager.innerHTML = '';
                return;
            }
            const from = (currentPage - 1) * PAGE_SIZE + 1;
            const to = Math.min(currentPage * PAGE_SIZE, dataRows.length);

            const pg = function(label, page, cls, disabled) {
                return '<button type="button" class="pg' + (cls ? ' ' + cls : '') + '" data-page="' + page + '"' +
                    (disabled ? ' disabled' : '') + '>' + label + '</button>';
            };
            const dots = '<span class="pg-dots">…</span>';

            let btns = pg('&lsaquo;', currentPage - 1, '', currentPage <= 1);
            // Numéros : tout si ≤ 10 pages, sinon fenêtre autour de la page courante
            let start = 1, end = pages;
            if (pages > 10) {
                start = Math.max(1, Math.min(currentPage - 2, pages - 4));
                end = Math.min(pages, start + 4);
                btns += pg('1', 1, currentPage === 1 ? 'active' : '');
                if (start > 2) btns += dots;
            }
            for (let p = start; p <= end; p++) {
                if (pages > 10 && (p === 1 || p === pages)) continue;
                btns += pg(String(p), p, p === currentPage ? 'active' : '');
            }
            if (pages > 10) {
                if (end < pages - 1) btns += dots;
                btns += pg(String(pages), pages, currentPage === pages ? 'active' : '');
            }
            btns += pg('&rsaquo;', currentPage + 1, '', currentPage >= pages);

            contactsPager.innerHTML =
                '<div class="mailing-pager">' +
                    '<span class="mailing-pager-info">' + from + '–' + to + ' / ' + dataRows.length + ' contacts</span>' +
                    '<span class="mailing-pager-pages">' + btns + '</span>' +
                '</div>';
            contactsPager.querySelectorAll('[data-page]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    showPage(parseInt(this.dataset.page, 10));
                });
            });
        }

        function showPage(page) {
            const pages = pageCount();
            currentPage = Math.max(1, Math.min(page, pages));
            const from = (currentPage - 1) * PAGE_SIZE;
            const to = from + PAGE_SIZE;
            // Relit l'ordre courant du DOM → cohérent après un tri des colonnes
            let idx = 0;
            Array.from(contactsBody.querySelectorAll('tr')).forEach(function(r) {
                if (r.querySelector('td[colspan]')) return;
                r.classList.toggle('d-none', idx < from || idx >= to);
                idx++;
            });
            renderPager();
        }

        // Ré-applique la visibilité après un tri (sponsor-stats.js réordonne les <tr>)
        contactsBody.closest('table').querySelectorAll('th[data-sort]').forEach(function(th) {
            th.addEventListener('click', function() { showPage(currentPage); });
        });

        // ---- Tout cocher / décocher la page affichée (colonne Abonné) ----
        const checkAllAbonne = document.getElementById('checkAllAbonne');
        function visibleToggles() {
            return Array.from(contactsBody.querySelectorAll('tr')).filter(function(r) {
                return !r.classList.contains('d-none') && !r.querySelector('td[colspan]');
            }).map(function(r) { return r.querySelector('.toggle-mailing'); }).filter(Boolean);
        }
        function syncCheckAll() {
            if (!checkAllAbonne) return;
            const t = visibleToggles();
            const on = t.filter(function(cb) { return cb.checked; }).length;
            checkAllAbonne.checked = t.length > 0 && on === t.length;
            checkAllAbonne.indeterminate = on > 0 && on < t.length;
        }
        if (checkAllAbonne) {
            checkAllAbonne.addEventListener('click', function(e) { e.stopPropagation(); }); // ne pas trier
            checkAllAbonne.addEventListener('change', async function() {
                const t = visibleToggles();
                const active = this.checked;
                const ids = t.map(function(cb) { return parseInt(cb.dataset.id, 10); }).filter(Boolean);
                if (!ids.length) return;
                checkAllAbonne.disabled = true;
                try {
                    const response = await fetch('/api/admin/mailing/toggle.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: ids, active: active })
                    });
                    const data = await response.json();
                    if (data.success) {
                        t.forEach(function(cb) { cb.checked = active; });
                        window.MAILING_ACTIVE_COUNT = activeCount();
                    } else {
                        checkAllAbonne.checked = !active;
                        console.error('[mailing] échec batch toggle:', data.message);
                        alert(data.message || 'Une erreur est survenue');
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    checkAllAbonne.checked = !active;
                    alert('Erreur de communication avec le serveur');
                } finally {
                    checkAllAbonne.disabled = false;
                    syncCheckAll();
                }
            });
        }
        // Sync du header quand un toggle individuel change OU quand la page change
        contactsBody.addEventListener('change', function(e) {
            if (e.target.classList.contains('toggle-mailing')) syncCheckAll();
        });
        contactsPager.addEventListener('click', function() { setTimeout(syncCheckAll, 0); });
        contactsBody.closest('table').querySelectorAll('th[data-sort]').forEach(function(th) {
            th.addEventListener('click', function() { setTimeout(syncCheckAll, 0); });
        });
        setTimeout(syncCheckAll, 0);

        showPage(1);
    }

    // ==================== Prévisualisation email ====================

    // Contact témoin : radio coché dans la liste (0 = données factices)
    let previewContactId = 0;
    document.querySelectorAll('.preview-contact').forEach(function(rb) {
        rb.addEventListener('change', function() {
            previewContactId = parseInt(rb.dataset.id, 10) || 0;
        });
    });

    const previewModal = document.getElementById('mailingPreviewModal');
    const previewFrame = document.getElementById('mailingPreviewFrame');
    const previewInfo  = document.getElementById('mailingPreviewInfo');

    /** Ouvre la modale d'aperçu avec l'email rendu (subject/body bruts, contact_id = témoin). */
    async function openMailPreview(subject, body, contactId) {
        if (!previewModal) return;
        if (!subject || !body) {
            alert('Objet et message requis pour l\'aperçu');
            return;
        }
        previewFrame.srcdoc = '';
        previewInfo.textContent = 'chargement…';
        previewModal.classList.remove('d-none');
        try {
            const response = await fetch('/api/admin/mailing/preview.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subject: subject, body: body, contact_id: contactId || 0 })
            });
            const data = await response.json();
            if (data.success) {
                previewFrame.srcdoc = data.data.html;
                previewInfo.textContent = '— Objet : ' + data.data.subject;
            } else {
                previewModal.classList.add('d-none');
                alert(data.message || 'Aperçu impossible');
            }
        } catch (error) {
            console.error('Erreur:', error);
            previewModal.classList.add('d-none');
            alert('Erreur de communication avec le serveur');
        }
    }

    if (previewModal) {
        const closePreview = function() { previewModal.classList.add('d-none'); };
        document.getElementById('mailingPreviewClose').addEventListener('click', closePreview);
        previewModal.addEventListener('click', function(e) {
            if (e.target === previewModal) closePreview();
        });
    }

    // ==================== Modale : campagne (création + édition) ====================

    let openCampaignModal = function() {};
    const campaignModal = document.getElementById('campaignModal');
    if (campaignModal) {
        const modalId = document.getElementById('campaignModalId');
        const modalTitle = document.getElementById('campaignModalTitle');
        const modalSubject = document.getElementById('campaignModalSubject');
        const modalBody = document.getElementById('campaignModalBody');
        const modalTestTo = document.getElementById('campaignModalTestTo');
        const modalResult = document.getElementById('campaignModalResult');
        const modalSave = document.getElementById('campaignModalSave');
        const modalTest = document.getElementById('campaignModalTestBtn');

        function closeCampaignModal() {
            campaignModal.classList.add('d-none');
            modalResult.classList.add('d-none');
        }

        // c = null → nouvelle campagne ; objet campagne → édition pré-remplie
        openCampaignModal = function(c) {
            modalId.value = c ? c.id : '0';
            modalTitle.textContent = c ? 'Modifier la campagne' : 'Nouvelle campagne';
            modalSubject.value = c ? c.subject : '';
            modalBody.value = c ? c.body : '';
            modalResult.classList.add('d-none');
            campaignModal.classList.remove('d-none');
            modalSubject.focus();
        };

        const newBtn = document.getElementById('campaignNewBtn');
        if (newBtn) {
            newBtn.addEventListener('click', function() { openCampaignModal(null); });
        }

        document.getElementById('campaignModalClose').addEventListener('click', closeCampaignModal);
        document.getElementById('campaignModalCancel').addEventListener('click', closeCampaignModal);
        campaignModal.addEventListener('click', function(e) {
            if (e.target === campaignModal) closeCampaignModal();
        });

        modalSave.addEventListener('click', async function() {
            const subject = modalSubject.value.trim();
            const body = modalBody.value.trim();
            if (!subject || !body) {
                modalResult.textContent = 'Objet et message requis';
                modalResult.classList.remove('d-none', 'alert-success');
                modalResult.classList.add('alert-danger');
                return;
            }
            modalSave.disabled = true;
            try {
                const response = await fetch('/api/admin/mailing/campaigns.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'save', id: parseInt(modalId.value, 10), subject, body })
                });
                const data = await response.json();
                if (data.success) {
                    backToCampaign();
                } else {
                    modalResult.textContent = data.message || 'Une erreur est survenue';
                    modalResult.classList.remove('d-none', 'alert-success');
                    modalResult.classList.add('alert-danger');
                }
            } catch (error) {
                console.error('Erreur:', error);
                modalResult.textContent = 'Erreur de communication avec le serveur';
                modalResult.classList.remove('d-none', 'alert-success');
                modalResult.classList.add('alert-danger');
            } finally {
                modalSave.disabled = false;
            }
        });

        document.getElementById('campaignModalPreview').addEventListener('click', function() {
            openMailPreview(modalSubject.value.trim(), modalBody.value.trim(), previewContactId);
        });

        modalTest.addEventListener('click', async function() {
            const subject = modalSubject.value.trim();
            const body = modalBody.value.trim();
            const to = modalTestTo.value.trim();
            if (!subject || !body || !to) {
                modalResult.textContent = 'Objet, message et adresse de test requis';
                modalResult.classList.remove('d-none', 'alert-success');
                modalResult.classList.add('alert-danger');
                return;
            }
            modalTest.disabled = true;
            try {
                const response = await fetch('/api/admin/mailing/send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subject, body, test_to: to })
                });
                const data = await response.json();
                modalResult.textContent = data.message;
                modalResult.classList.remove('d-none', 'alert-success', 'alert-danger');
                modalResult.classList.add(data.success ? 'alert-success' : 'alert-danger');
            } catch (error) {
                console.error('Erreur:', error);
                modalResult.textContent = 'Erreur de communication avec le serveur';
                modalResult.classList.remove('d-none', 'alert-success');
                modalResult.classList.add('alert-danger');
            } finally {
                modalTest.disabled = false;
            }
        });
    }

    document.querySelectorAll('.campaign-edit').forEach(button => {
        button.addEventListener('click', function() {
            const c = CAMPAIGNS[this.dataset.id];
            if (c) openCampaignModal(c);
        });
    });

    document.querySelectorAll('.campaign-delete').forEach(button => {
        button.addEventListener('click', async function() {
            if (!confirm('Supprimer cette campagne ?')) return;
            try {
                const response = await fetch('/api/admin/mailing/campaigns.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: parseInt(this.dataset.id, 10) })
                });
                const data = await response.json();
                if (data.success) {
                    backToCampaign();
                } else {
                    alert(data.message || 'Une erreur est survenue');
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur de communication avec le serveur');
            }
        });
    });

    // Envoi direct d'une campagne enregistrée à tous les abonnés
    const resultsBox = document.getElementById('campaignResults');
    const sendModal    = document.getElementById('mailingSendModal');
    const sendSubject  = document.getElementById('mailingSendSubject');
    const sendCount    = document.getElementById('mailingSendCount');
    const sendConfirm  = document.getElementById('mailingSendConfirm');
    const sendCancel   = document.getElementById('mailingSendCancel');
    const sendClose    = document.getElementById('mailingSendClose');

    function closeSendModal() {
        if (sendModal) sendModal.classList.add('d-none');
    }
    if (sendCancel) sendCancel.addEventListener('click', closeSendModal);
    if (sendClose) sendClose.addEventListener('click', closeSendModal);
    if (sendModal) sendModal.addEventListener('click', function(e) { if (e.target === sendModal) closeSendModal(); });

    // Ouvre la modale de confirmation ; renvoie une Promise<bool>
    function askSendConfirm(subject, count) {
        return new Promise(function(resolve) {
            if (!sendModal) { resolve(window.confirm('Envoyer « ' + subject + ' » à ' + count + ' abonné(s) ?')); return; }
            sendSubject.textContent = '« ' + subject + ' »';
            sendCount.textContent = count;
            sendModal.classList.remove('d-none');
            const done = function(ok) { closeSendModal(); resolve(ok); };
            sendConfirm.onclick = function() { done(true); };
            sendCancel.onclick  = function() { done(false); };
            sendClose.onclick   = function() { done(false); };
        });
    }

    document.querySelectorAll('.campaign-send').forEach(button => {
        button.addEventListener('click', async function() {
            const c = CAMPAIGNS[this.dataset.id];
            if (!c) return;
            // Vérité serveur : les destinataires de send.php sont les status=1 en base
            let count = activeCount(); // fallback DOM
            try {
                const res = await fetch('/api/admin/mailing/count.php');
                const d = await res.json();
                if (d && d.success && d.data && typeof d.data.count === 'number') {
                    count = d.data.count;
                }
            } catch (e) { /* garde le fallback DOM */ }
            if (count === 0) {
                alert('Aucun abonné sélectionné — cochez des contacts dans l\'onglet Contacts (colonne « Abonné »).');
                return;
            }
            if (!(await askSendConfirm(c.subject, count))) return;
            button.disabled = true;
            try {
                const response = await fetch('/api/admin/mailing/send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subject: c.subject, body: c.body, campaign_id: parseInt(c.id, 10) || null })
                });
                const data = await response.json();
                if (data && data.data && data.data.results) {
                    resultsBox.innerHTML = '<strong>' + esc(data.message) + '</strong>' +
                        '<ul class="mb-0 mt-2" style="max-height:250px;overflow:auto">' +
                        data.data.results.map(function(r) {
                            return '<li class="' + (r.ok ? 'text-success' : 'text-danger') + '">' + esc(r.email) + '</li>';
                        }).join('') + '</ul>';
                    resultsBox.classList.remove('d-none', 'alert-danger');
                    resultsBox.classList.add('alert-success');
                } else {
                    resultsBox.textContent = data ? data.message : 'Erreur inconnue';
                    resultsBox.classList.remove('d-none', 'alert-success');
                    resultsBox.classList.add('alert-danger');
                }
            } catch (error) {
                console.error('Erreur:', error);
                resultsBox.textContent = 'Erreur de communication avec le serveur';
                resultsBox.classList.remove('d-none', 'alert-success');
                resultsBox.classList.add('alert-danger');
            } finally {
                button.disabled = false;
            }
        });
    });

    // ==================== Modale : email à un contact ====================

    const mailModal = document.getElementById('contactMailModal');
    if (mailModal) {
        const mailId = document.getElementById('contactMailId');
        const mailTo = document.getElementById('contactMailTo');
        const mailCampaign = document.getElementById('contactMailCampaign');
        const mailSubject = document.getElementById('contactMailSubject');
        const mailBody = document.getElementById('contactMailBody');
        const mailResult = document.getElementById('contactMailResult');
        const mailSendBtn = document.getElementById('contactMailSend');

        // Remplit le select des campagnes une seule fois
        Object.keys(CAMPAIGNS).forEach(function(id) {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = CAMPAIGNS[id].subject;
            mailCampaign.appendChild(opt);
        });

        function closeMailModal() {
            mailModal.classList.add('d-none');
            mailResult.classList.add('d-none');
        }

        document.querySelectorAll('.email-mailing').forEach(button => {
            button.addEventListener('click', function() {
                mailId.value = this.dataset.id;
                mailTo.textContent = this.dataset.email;
                mailCampaign.value = '';
                mailSubject.value = '';
                mailBody.value = '';
                mailResult.classList.add('d-none');
                mailModal.classList.remove('d-none');
            });
        });

        document.getElementById('contactMailClose').addEventListener('click', closeMailModal);
        document.getElementById('contactMailCancel').addEventListener('click', closeMailModal);
        mailModal.addEventListener('click', function(e) {
            if (e.target === mailModal) closeMailModal();
        });

        // Pré-remplissage depuis une campagne (modifiable ensuite — option B)
        mailCampaign.addEventListener('change', function() {
            const c = CAMPAIGNS[this.value];
            mailSubject.value = c ? c.subject : '';
            mailBody.value = c ? c.body : '';
        });

        document.getElementById('contactMailPreview').addEventListener('click', function() {
            openMailPreview(mailSubject.value.trim(), mailBody.value.trim(), parseInt(mailId.value, 10) || 0);
        });

        mailSendBtn.addEventListener('click', async function() {
            const subject = mailSubject.value.trim();
            const body = mailBody.value.trim();
            if (!subject || !body) {
                alert('Objet et message requis');
                return;
            }
            mailSendBtn.disabled = true;
            try {
                const response = await fetch('/api/admin/mailing/send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ contact_id: parseInt(mailId.value, 10), subject, body })
                });
                const data = await response.json();
                mailResult.textContent = data.message;
                mailResult.classList.remove('d-none', 'alert-success', 'alert-danger');
                mailResult.classList.add(data.success ? 'alert-success' : 'alert-danger');
            } catch (error) {
                console.error('Erreur:', error);
                mailResult.textContent = 'Erreur de communication avec le serveur';
                mailResult.classList.remove('d-none', 'alert-success');
                mailResult.classList.add('alert-danger');
            } finally {
                mailSendBtn.disabled = false;
            }
        });
    }
});
