// Gestion de la recherche d'organisateur
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Initialisation du gestionnaire d\'organisateurs ===');
    
    const elements = {
        dropdownSelect: document.querySelector('.dropdown-select'),
        searchInput: document.getElementById('organizerSearch'),
        optionsContainer: document.getElementById('organizerOptions'),
        selectedOrganizerId: document.getElementById('selectedOrganizerId'),
        useProfileInfoCheckbox: document.getElementById('useProfileInfo')
    };

    // Log des éléments trouvés
    console.log('Éléments trouvés:', {
        dropdownSelect: !!elements.dropdownSelect,
        searchInput: !!elements.searchInput,
        optionsContainer: !!elements.optionsContainer,
        selectedOrganizerId: !!elements.selectedOrganizerId,
        useProfileInfoCheckbox: !!elements.useProfileInfoCheckbox
    });

    // Gérer l'ouverture/fermeture du dropdown
    elements.searchInput.addEventListener('focus', () => {
        console.log('Focus sur le champ de recherche');
        elements.dropdownSelect.classList.add('active');
    });

    // Fermer le dropdown quand on clique en dehors
    document.addEventListener('click', (e) => {
        if (!elements.dropdownSelect.contains(e.target)) {
            console.log('Clic en dehors du dropdown - fermeture');
            elements.dropdownSelect.classList.remove('active');
        }
    });

    // Gérer la recherche
    elements.searchInput.addEventListener('input', (e) => {
        const searchValue = e.target.value.toLowerCase();
        console.log('Recherche:', searchValue);
        
        const options = elements.optionsContainer.querySelectorAll('.organizer-option');
        console.log('Nombre d\'options trouvées:', options.length);
        
        options.forEach(option => {
            const text = option.textContent.toLowerCase();
            option.style.display = text.includes(searchValue) ? 'flex' : 'none';
        });

        // Toujours afficher l'option de création
        const createOption = elements.optionsContainer.querySelector('.create-option');
        if (createOption) {
            createOption.style.display = 'flex';
        }

        elements.dropdownSelect.classList.add('active');
    });

    // Gérer la sélection d'une option
    elements.optionsContainer.addEventListener('click', (e) => {
        console.log('=== Clic sur une option ===');
        const option = e.target.closest('.dropdown-item');
        console.log('Option trouvée:', option);
        
        if (!option) {
            console.log('Aucune option trouvée');
            return;
        }

        // Log de l'option cliquée
        console.log('Dataset de l\'option:', {
            id: option.dataset.id,
            address: option.dataset.address,
            description: option.dataset.description,
            website: option.dataset.website,
            phone: option.dataset.phone,
            email: option.dataset.email
        });

        // Retirer la sélection précédente
        elements.optionsContainer.querySelectorAll('.dropdown-item').forEach(opt => {
            opt.classList.remove('selected');
        });

        if (option.classList.contains('create-option')) {
            console.log('Création d\'un nouvel organisateur');
            elements.selectedOrganizerId.value = '';
            elements.searchInput.value = '';
            elements.searchInput.placeholder = 'Entrez le nom du nouvel organisateur';
            elements.searchInput.focus();
            enableAllFields();
            clearAllFields();
        } else {
            console.log('Sélection d\'un organisateur existant');
            option.classList.add('selected');
            elements.selectedOrganizerId.value = option.dataset.id;
            elements.searchInput.value = option.querySelector('strong').textContent;
            
            console.log('Checkbox état:', elements.useProfileInfoCheckbox?.checked);
            if (elements.useProfileInfoCheckbox?.checked) {
                console.log('Remplissage des champs avec les données:', {
                    address: option.dataset.address,
                    description: option.dataset.description,
                    website: option.dataset.website,
                    phone: option.dataset.phone,
                    email: option.dataset.email
                });
                
                fillOrganizerFields({
                    address: option.dataset.address,
                    description: option.dataset.description,
                    website: option.dataset.website,
                    phone: option.dataset.phone,
                    email: option.dataset.email
                });
                disableFields();
            }
        }

        elements.dropdownSelect.classList.remove('active');
    });

    // Gérer le changement de la checkbox
    if (elements.useProfileInfoCheckbox) {
        elements.useProfileInfoCheckbox.addEventListener('change', function(e) {
            console.log('=== Changement de la checkbox ===');
            console.log('Nouvelle valeur:', e.target.checked);
            
            const selectedOption = elements.optionsContainer.querySelector('.dropdown-item.selected');
            console.log('Option sélectionnée trouvée:', !!selectedOption);

            if (e.target.checked && selectedOption && !selectedOption.classList.contains('create-option')) {
                console.log('Remplissage des champs depuis la checkbox');
                fillOrganizerFields({
                    address: selectedOption.dataset.address,
                    description: selectedOption.dataset.description,
                    website: selectedOption.dataset.website,
                    phone: selectedOption.dataset.phone,
                    email: selectedOption.dataset.email
                });
                disableFields();
            } else {
                console.log('Réinitialisation des champs');
                enableAllFields();
                if (!selectedOption || selectedOption.classList.contains('create-option')) {
                    clearAllFields();
                }
            }
        });
    }

    // Fonctions utilitaires
    function fillOrganizerFields(data) {
        console.log('=== Remplissage des champs ===');
        console.log('Données reçues:', data);
        
        const fields = {
            'organizer-address': data.address || '',
            'organizer-description': data.description || '',
            'organizer-website': data.website || '',
            'organizer-phone': data.phone || '',
            'organizer-email': data.email || ''
        };

        Object.entries(fields).forEach(([id, value]) => {
            const field = document.getElementById(id);
            console.log(`Champ ${id}:`, { existe: !!field, valeur: value });
            if (field) {
                field.value = value;
            }
        });
    }

    function clearAllFields() {
        console.log('Nettoyage de tous les champs');
        const fields = [
            'organizer-address',
            'organizer-description',
            'organizer-website',
            'organizer-phone',
            'organizer-email'
        ];

        fields.forEach(id => {
            const field = document.getElementById(id);
            if (field) {
                field.value = '';
            }
        });
    }

    function disableFields() {
        console.log('Désactivation des champs');
        const fields = [
            'organizer-address',
            'organizer-description',
            'organizer-website',
            'organizer-phone',
            'organizer-email'
        ];

        fields.forEach(id => {
            const field = document.getElementById(id);
            if (field) {
                field.disabled = true;
                field.classList.add('disabled-field');
            }
        });
    }

    function enableAllFields() {
        console.log('Activation de tous les champs');
        const fields = [
            'organizer-address',
            'organizer-description',
            'organizer-website',
            'organizer-phone',
            'organizer-email'
        ];

        fields.forEach(id => {
            const field = document.getElementById(id);
            if (field) {
                field.disabled = false;
                field.classList.remove('disabled-field');
            }
        });
    }
});

// Export des fonctions
window.loadOrganizerProfile = function() {
    console.log('Chargement du profil organisateur');
    // Code pour charger le profil organisateur
};
