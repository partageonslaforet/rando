// Gestion de la recherche d'organisateur
document.addEventListener('DOMContentLoaded', function() {
    const organizerInput = document.getElementById('organizerSearch');
    const organizersList = document.getElementById('organizersList');
    const selectedOrganizerId = document.getElementById('selectedOrganizerId');
    const useProfileInfoCheckbox = document.getElementById('useProfileInfo');

    if (organizerInput) {
        organizerInput.addEventListener('input', function() {
            // Vérifier si la valeur correspond à un organisateur existant
            const options = organizersList.getElementsByTagName('option');
            let found = false;
            
            for (const option of options) {
                if (option.value === this.value) {
                    found = true;
                    // Récupérer les informations de l'organisateur
                    const organizer = {
                        id: option.dataset.id,
                        name: option.value,
                        description: option.dataset.description,
                        distance: option.dataset.distance,
                        elevation_gain: option.dataset.elevation
                    };
                    selectedOrganizerId.value = organizer.id;
                    fetchOrganizerDetails(organizer.id);
                    break;
                }
            }
            
            if (!found) {
                // Nouvel organisateur
                selectedOrganizerId.value = '';
                resetOrganizerFields();
            }
        });
    }

    if (useProfileInfoCheckbox) {
        useProfileInfoCheckbox.addEventListener('change', function() {
            if (this.checked) {
                loadOrganizerProfile();
            } else {
                resetOrganizerFields();
            }
        });
    }
});

async function fetchOrganizerDetails(organizerId) {
    try {
        const response = await fetch(`/api/organization-profil/get_profile.php?id=${organizerId}`);
        if (!response.ok) throw new Error('Erreur réseau');
        
        const organizer = await response.json();
        if (organizer) {
            fillOrganizerFields(organizer);
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des détails:', error);
    }
}

function fillOrganizerFields(organizer) {
    // Remplir tous les champs du formulaire
    const fields = {
        'organizerName': organizer.name,
        'organizerDescription': organizer.description,
        'organizerDistance': organizer.distance,
        'organizerElevation': organizer.elevation_gain,
        'organizerGpxFile': organizer.gpx_file,
        'organizerPrice': organizer.price
    };
    
    for (const [fieldName, value] of Object.entries(fields)) {
        const element = document.querySelector(`[name="${fieldName}"]`);
        if (element) {
            element.value = value || '';
            element.disabled = true;
        }
    }

    // Afficher les informations supplémentaires
    if (organizer.distance || organizer.elevation_gain) {
        const additionalInfo = document.createElement('div');
        additionalInfo.className = 'alert alert-info mt-2';
        additionalInfo.innerHTML = `
            ${organizer.distance ? `<div>Distance: ${organizer.distance}km</div>` : ''}
            ${organizer.elevation_gain ? `<div>Dénivelé: ${organizer.elevation_gain}m</div>` : ''}
            ${organizer.gpx_file ? `<div>Fichier GPX: ${organizer.gpx_file}</div>` : ''}
            ${organizer.price ? `<div>Prix: ${organizer.price}€</div>` : ''}
        `;
        const organizerFields = document.getElementById('organizerFields');
        const existingInfo = organizerFields.querySelector('.alert-info');
        if (existingInfo) {
            existingInfo.remove();
        }
        organizerFields.insertBefore(additionalInfo, organizerFields.firstChild);
    }
}

function resetOrganizerFields() {
    // Activer tous les champs pour permettre la saisie
    const fields = document.querySelectorAll('#organizerFields input, #organizerFields textarea');
    fields.forEach(field => {
        field.disabled = false;
    });

    // Supprimer l'alerte d'informations supplémentaires
    const organizerFields = document.getElementById('organizerFields');
    const existingInfo = organizerFields.querySelector('.alert-info');
    if (existingInfo) {
        existingInfo.remove();
    }
}

// Gestion du profil organisateur
async function loadOrganizerProfile() {
    try {
        const response = await fetch('/api/organization-profil/get_profile.php', {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            },
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        
        try {
            const data = JSON.parse(text);
            
            if (data.success && data.profile) {
                const fields = {
                    'organizerName': data.profile.name,
                    'organizerAddress': data.profile.address,
                    'organizerDescription': data.profile.description,
                    'organizerWebsite': data.profile.website,
                    'organizerPhone': data.profile.phone,
                    'organizerEmail': data.profile.email
                };
                
                // Remplir les champs avec gestion d'erreur pour chaque champ
                for (const [fieldName, value] of Object.entries(fields)) {
                    try {
                        const element = document.querySelector(`input[name="${fieldName}"], textarea[name="${fieldName}"]`);
                        if (element) {
                            element.value = value || '';
                        } else {
                            console.warn(`⚠️ Élément non trouvé pour ${fieldName}`);
                        }
                    } catch (fieldError) {
                        console.error(`❌ Erreur lors du remplissage du champ ${fieldName}:`, fieldError);
                    }
                }
                
            } else {
                throw new Error(data.message || 'Erreur lors du chargement du profil');
            }
        } catch (parseError) {
            console.error('❌ Erreur de parsing JSON:', parseError);
            throw new Error('Erreur lors du parsing de la réponse');
        }
    } catch (error) {
        console.error('❌ Erreur:', error);
        alert('Erreur lors du chargement du profil : ' + error.message);
    }
}

// Fonction pour ajouter un contact supplémentaire
function addContact() {
    const container = document.getElementById('additionalContactsContainer');
    const contactIndex = container.children.length;
    
    const contactTemplate = `
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Contact supplémentaire ${contactIndex + 1}</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom</label>
                        <input type="text" class="form-control" name="additionalContacts[${contactIndex}][name]">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="additionalContacts[${contactIndex}][email]">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" name="additionalContacts[${contactIndex}][phone]">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rôle</label>
                        <input type="text" class="form-control" name="additionalContacts[${contactIndex}][role]">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', contactTemplate);
}

// Export des fonctions
window.loadOrganizerProfile = loadOrganizerProfile;
window.addContact = addContact;
