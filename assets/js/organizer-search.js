document.addEventListener('DOMContentLoaded', function() {
    const organizerSearchInput = document.getElementById('organizerSearch');
    const organizerSearchResults = document.getElementById('organizerSearchResults');
    const organizerFields = document.getElementById('organizerFields');
    const selectedOrganizerId = document.getElementById('selectedOrganizerId');

    if (organizerSearchInput) {
        organizerSearchInput.addEventListener('input', debounce(function(e) {
            const searchTerm = e.target.value;
            if (searchTerm.length < 2) {
                organizerSearchResults.innerHTML = '';
                organizerSearchResults.style.display = 'none';
                return;
            }

            fetch(`/api/search-organizers.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    organizerSearchResults.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(organizer => {
                            const div = document.createElement('div');
                            div.className = 'organizer-result';
                            div.textContent = organizer.name;
                            div.addEventListener('click', () => selectOrganizer(organizer));
                            organizerSearchResults.appendChild(div);
                        });
                        organizerSearchResults.style.display = 'block';
                    } else {
                        organizerSearchResults.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error searching organizers:', error);
                });
        }, 300));

        // Cacher les résultats quand on clique en dehors
        document.addEventListener('click', function(e) {
            if (!organizerSearchInput.contains(e.target) && !organizerSearchResults.contains(e.target)) {
                organizerSearchResults.style.display = 'none';
            }
        });
    }

    function selectOrganizer(organizer) {
        organizerSearchInput.value = organizer.name;
        selectedOrganizerId.value = organizer.id;
        organizerSearchResults.style.display = 'none';

        // Remplir les champs avec les informations de l'organisateur
        document.getElementById('organizerName').value = organizer.name;
        document.getElementById('organizerAddress').value = organizer.address || '';
        document.getElementById('organizerDescription').value = organizer.description || '';
        document.getElementById('organizerWebsite').value = organizer.website || '';

        // Désactiver les champs
        toggleOrganizerFields(true);
    }

    function toggleOrganizerFields(disabled) {
        const fields = organizerFields.querySelectorAll('input, textarea');
        fields.forEach(field => {
            field.disabled = disabled;
        });
    }

    // Fonction utilitaire pour debounce
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Gérer le bouton "Nouvel organisateur"
    const newOrganizerBtn = document.getElementById('newOrganizerBtn');
    if (newOrganizerBtn) {
        newOrganizerBtn.addEventListener('click', function() {
            organizerSearchInput.value = '';
            selectedOrganizerId.value = '';
            // Réinitialiser les champs
            document.getElementById('organizerName').value = '';
            document.getElementById('organizerAddress').value = '';
            document.getElementById('organizerDescription').value = '';
            document.getElementById('organizerWebsite').value = '';
            // Activer les champs
            toggleOrganizerFields(false);
        });
    }
});
