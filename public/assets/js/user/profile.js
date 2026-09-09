/**
 * Fichier: /assets/js/user/profile.js
 * Rôle: Gestion des formulaires profil utilisateur/organisateur (update, email, logo, mot de passe).
 * Utilisation: /pages/user/profile.php.
 * Dépendances: Fetch API (/api/user-profil/*, /api/organization-profil/*), DOM (#profileForm...).
 */
console.log(' Chargement de profile.js');

// Variable globale pour éviter la double soumission
let isSubmitting = false;

document.addEventListener('DOMContentLoaded', function() {
    // Gestion du formulaire de profil
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            
            const formData = {
                name: document.getElementById('name').value.trim(),
                email: document.getElementById('email').value.trim()
            };
            
            if (!formData.name || !formData.email) {
                alert('Le nom d\'utilisateur et l\'email sont requis');
                submitBtn.disabled = false;
                return;
            }
            
            fetch('/api/user-profil/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Une erreur est survenue');
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
                submitBtn.disabled = false;
            });
        });
    }

    // Gestion du formulaire de mot de passe
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            
            const formData = {
                currentPassword: document.getElementById('currentPassword').value,
                newPassword: document.getElementById('newPassword').value
            };
            
            if (!formData.currentPassword || !formData.newPassword) {
                alert('Tous les champs sont requis');
                submitBtn.disabled = false;
                return;
            }
            
            if (formData.newPassword.length < 8) {
                alert('Le nouveau mot de passe doit contenir au moins 8 caractères');
                submitBtn.disabled = false;
                return;
            }
            
            fetch('/api/user-profil/change-password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    this.reset();
                } else {
                    alert(data.message || 'Une erreur est survenue');
                }
                submitBtn.disabled = false;
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
                submitBtn.disabled = false;
            });
        });
    }

    // Gestion du formulaire organisateur
    const organizerForm = document.getElementById('organizerProfileForm');
    if (organizerForm) {
        console.log(' Initialisation du formulaire organisateur');
        
        // Gestion de la prévisualisation du logo
        const logoPreview = document.getElementById('logo_preview');
        const logoPreviewContainer = document.querySelector('.logo-preview-container');
        const removeLogoBtn = document.getElementById('remove_logo');
        const logoInput = document.getElementById('org_logo');
        let logoChanged = false;
        let logoRemoved = false;

        // Fonction pour afficher la prévisualisation
        function showLogoPreview(src) {
            logoPreview.src = src;
            logoPreviewContainer.style.display = 'block';
        }

        // Fonction pour masquer la prévisualisation
        function hideLogoPreview() {
            logoPreviewContainer.style.display = 'none';
            logoPreview.src = '';
        }

        // Fonction de gestion du changement de logo
        function handleLogoChange(e) {
            const file = e.target.files[0];
            if (!file) {
                hideLogoPreview();
                return;
            }

            // Vérifier le type de fichier
            if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                alert('Seuls les fichiers JPG, PNG et GIF sont autorisés');
                e.target.value = '';
                hideLogoPreview();
                return;
            }

            // Vérifier la taille du fichier (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('Le fichier est trop volumineux (max 5MB)');
                e.target.value = '';
                hideLogoPreview();
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                showLogoPreview(e.target.result);
                logoChanged = true;
                logoRemoved = false;
            };
            reader.readAsDataURL(file);
        }

        // Écouter les changements sur l'input du logo
        logoInput.addEventListener('change', handleLogoChange);

        // Gérer la suppression du logo
        removeLogoBtn.addEventListener('click', function() {
            hideLogoPreview();
            logoInput.value = '';
            logoChanged = false;
            logoRemoved = true;
        });

        // Gestion de la suppression des profils
        document.querySelectorAll('.delete-profile').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                const profileId = this.getAttribute('data-profile-id');
                console.log('[Delete] Tentative de suppression du profil:', profileId);

                if (!profileId) {
                    console.error('[Delete] ID du profil manquant');
                    alert('Erreur: Impossible d\'identifier le profil à supprimer');
                    return;
                }

                if (!confirm('Êtes-vous sûr de vouloir supprimer ce profil ?')) {
                    console.log('[Delete] Suppression annulée par l\'utilisateur');
                    return;
                }

                try {
                    console.log('[Delete] Envoi de la requête de suppression');
                    const response = await fetch('/api/organization-profil/delete_profile.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ profile_id: profileId })
                    });

                    console.log('[Delete] Réponse reçue, status:', response.status);
                    const text = await response.text();
                    console.log('[Delete] Réponse texte:', text);

                    let data;
                    try {
                        data = text ? JSON.parse(text) : {};
                        console.log('[Delete] Données parsées:', data);
                    } catch (e) {
                        console.error('[Delete] Erreur de parsing JSON:', e);
                        throw new Error('Erreur lors de la communication avec le serveur');
                    }

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Une erreur est survenue lors de la suppression');
                    }

                    console.log('[Delete] Suppression réussie, redirection vers l\'onglet Profils Organisateur');
                    window.location.href = window.location.pathname + '?tab=organizer';
                } catch (error) {
                    console.error('[Delete] Erreur lors de la suppression:', error);
                    alert(error.message || 'Une erreur est survenue lors de la suppression du profil');
                }
            });
        });

        // Fonction de soumission du formulaire
        async function handleSubmit(event) {
            event.preventDefault();
            event.stopPropagation();
            
            console.log(' [Submit] État isSubmitting:', isSubmitting);
            if (isSubmitting) {
                console.log(' [Submit] Soumission déjà en cours, abandon');
                return false;
            }

            const submitBtn = event.target.querySelector('button[type="submit"]');
            if (!submitBtn) return false;

            try {
                submitBtn.disabled = true;
                isSubmitting = true;

                // Bootstrap validation
                const formEl = event.target;
                formEl.classList.add('was-validated');
                if (!formEl.checkValidity()) {
                    console.log(' [Submit] Validation HTML5 invalide');
                    submitBtn.disabled = false;
                    isSubmitting = false;
                    return false;
                }

                const formData = new FormData(event.target);
                
                // Vérifier que le nom est présent
                const name = formData.get('name');
                if (!name || name.trim() === '') {
                    // Renforcer l'état invalide si nécessaire
                    document.getElementById('org_name')?.classList.add('is-invalid');
                    throw new Error('Le nom de l\'organisation est requis');
                }

                // Si le logo a été supprimé mais pas remplacé
                if (logoRemoved && !logoChanged) {
                    formData.append('remove_logo', '1');
                }
                
                // Si aucun nouveau logo n'a été sélectionné et qu'il n'a pas été supprimé
                if (!logoChanged && !logoRemoved) {
                    formData.delete('logo');
                }

                // Log des données pour debug
                console.log(' [Submit] Données à envoyer:');
                for (let [key, value] of formData.entries()) {
                    console.log(`  ${key}:`, value instanceof File ? `File: ${value.name}` : value);
                }

                const response = await fetch('/api/organization-profil/update_profile.php', {
                    method: 'POST',
                    body: formData
                });

                console.log(' [Submit] Réponse reçue, status:', response.status);
                const data = await response.json();
                console.log(' [Submit] Données reçues:', data);

                if (!data.success) {
                    throw new Error(data.message || 'Une erreur est survenue');
                }

                console.log(' [Submit] Succès, redirection vers l\'onglet Profils Organisateur');
                window.location.href = window.location.pathname + '?tab=organizer';
            } catch (error) {
                console.error(' [Submit] Erreur:', error);
                alert(error.message || 'Une erreur est survenue lors de la soumission du formulaire');
            } finally {
                submitBtn.disabled = false;
                isSubmitting = false;
            }
            
            return false;
        }

        // Gestion du modal
        const organizerModal = document.getElementById('organizerModal');
        if (organizerModal) {
            organizerModal.addEventListener('hidden.bs.modal', function () {
                organizerForm.reset();
                hideLogoPreview();
                isSubmitting = false;
                organizerForm.classList.remove('was-validated');
                organizerForm.querySelectorAll('.is-invalid')?.forEach(el => el.classList.remove('is-invalid'));
            });

            organizerModal.addEventListener('show.bs.modal', function(event) {
                isSubmitting = false;
                organizerForm.reset();
                hideLogoPreview();

                const button = event.relatedTarget;
                const profileId = button?.getAttribute('data-profile-id');
                document.getElementById('profile_id').value = profileId || '';

                // Mettre à jour les titres de la modale selon le contexte
                const titleEl = organizerModal.querySelector('.modal-title');
                if (titleEl) {
                    titleEl.textContent = profileId ? 'Modifier le profil' : 'Nouveau profil';
                }
                const titleHero = organizerModal.querySelector('.modal-title-hero');
                if (titleHero) {
                    titleHero.textContent = profileId ? 'Modifier le profil' : 'Nouveau profil';
                }

                if (profileId) {
                    // Mode édition
                    const card = document.querySelector(`.card[data-profile-id="${profileId}"]`);
                    if (card) {
                        document.getElementById('org_name').value = card.querySelector('.card-title').textContent.trim();
                        document.getElementById('org_description').value = card.querySelector('.card-text')?.textContent.trim() || '';
                        document.getElementById('org_email').value = card.querySelector('.bi-envelope')?.parentNode.textContent.trim() || '';
                        document.getElementById('org_phone').value = card.querySelector('.bi-telephone')?.parentNode.textContent.trim() || '';
                        document.getElementById('org_website').value = card.querySelector('.bi-globe a')?.href || '';
                        document.getElementById('org_address').value = card.querySelector('.bi-geo-alt')?.parentNode.textContent.trim() || '';
                        
                        const existingLogo = card.querySelector('.profile-logo');
                        if (existingLogo && existingLogo.src && !existingLogo.src.includes('default-event.jpg')) {
                            showLogoPreview(existingLogo.src);
                        }
                    }
                }
            });
        }

        // Ajouter le gestionnaire de soumission
        organizerForm.addEventListener('submit', handleSubmit);
    }
});
