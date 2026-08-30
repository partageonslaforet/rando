
$(document).ready(function() {
    // Retirer la classe js-loading une fois que tout est chargé
    $('body').removeClass('js-loading');
    console.log('=== DÉBUT INITIALISATION DROPDOWN HEADER ===');
    
    // Navbar transparent
    const navbar = document.querySelector('.navbar');
    console.log('Navbar trouvé:', navbar);
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

    // Initialisation du dropdown avec Bootstrap 5
    const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
    const dropdownList = [...dropdownElementList].map(dropdownToggleEl => {
        return new bootstrap.Dropdown(dropdownToggleEl, {
            autoClose: true
        });
    });

    // Logging des événements dropdown
    $('.dropdown').on('show.bs.dropdown', function () {
        console.log('Menu en cours d\'ouverture');
    }).on('shown.bs.dropdown', function () {
        console.log('Menu ouvert');
    }).on('hide.bs.dropdown', function () {
        console.log('Menu en cours de fermeture');
    }).on('hidden.bs.dropdown', function () {
        console.log('Menu fermé');
    });

    console.log('=== FIN INITIALISATION DROPDOWN HEADER ===');
});