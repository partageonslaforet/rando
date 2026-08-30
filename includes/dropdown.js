// Initialisation du dropdown pour les deux headers
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggle = document.getElementById('navbarDropdown');
    if (dropdownToggle) {
        // Vérifier si une instance existe déjà
        let dropdown = bootstrap.Dropdown.getInstance(dropdownToggle);
        if (!dropdown) {
            // Créer une nouvelle instance seulement si nécessaire
            dropdown = new bootstrap.Dropdown(dropdownToggle);
        }
        
        // Ajouter des logs pour le débogage
        dropdownToggle.addEventListener('show.bs.dropdown', function () {
            console.log('Dropdown en cours d\'ouverture');
        });
        
        dropdownToggle.addEventListener('shown.bs.dropdown', function () {
            console.log('Dropdown ouvert');
        });
        
        dropdownToggle.addEventListener('hide.bs.dropdown', function () {
            console.log('Dropdown en cours de fermeture');
        });
        
        dropdownToggle.addEventListener('hidden.bs.dropdown', function () {
            console.log('Dropdown fermé');
        });
    }
});