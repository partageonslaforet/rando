document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.querySelector('.navbar');
    const scrollThreshold = 50;

    function updateHeader() {
        if (window.scrollY > scrollThreshold) {
            navbar.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('navbar-scrolled');
        }
    }

    // Initial check
    updateHeader();
    
    // Add scroll event listener
    window.addEventListener('scroll', updateHeader);
});
