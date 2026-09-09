/**
 * Fichier: /assets/js/core/header.js
 * Rôle: Comportement visuel du header (transparence/scroll) et dropdowns Bootstrap.
 * Utilisation: exécuté globalement sur toutes les pages avec navbar.
 * Dépendances: Bootstrap Dropdown.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Retirer la classe js-loading une fois que tout est chargé
    document.body.classList.remove('js-loading');

    // Navbar transparent
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        navbar.classList.add('navbar-transparent');

        // Gestion du scroll
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
                navbar.classList.remove('navbar-transparent');
            } else {
                navbar.classList.remove('scrolled');
                navbar.classList.add('navbar-transparent');
            }
        });
    }

    // Initialisation du dropdown avec Bootstrap 5
    const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
    dropdownElementList.forEach(dropdownToggleEl => {
        new bootstrap.Dropdown(dropdownToggleEl, { autoClose: true });
    });
});