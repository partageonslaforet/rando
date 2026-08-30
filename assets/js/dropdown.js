// Initialisation des dropdowns
document.addEventListener('DOMContentLoaded', function() {
    // Laisser Bootstrap gérer automatiquement les dropdowns
    // via les attributs data-bs-toggle="dropdown"
    
    // Ajouter des logs pour le débogage
    document.querySelectorAll('.dropdown-toggle').forEach(function(dropdownToggle) {
        dropdownToggle.addEventListener('show.bs.dropdown', function () {
            console.log('Dropdown en cours d\'ouverture');
        });
        
        dropdownToggle.addEventListener('shown.bs.dropdown', function () {
            console.log('Dropdown ouvert');
        });
    });
});