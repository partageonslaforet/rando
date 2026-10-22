/**
 * localisation: public/assets/js/home/sponsor-rotate.js
 * Role: Rotation par fondu du logo sponsor affiché en bas du hero (home)
 * Usage: chargé via templates/layouts/scripts.php — agit sur .sponsor-strip-slide
 *        si plusieurs slides existent (un seul sponsor => statique, pas de timer)
 */
document.addEventListener('DOMContentLoaded', () => {
    const slides = document.querySelectorAll('.sponsor-strip-slide');
    if (slides.length < 2) {
        return;
    }

    let index = 0;
    setInterval(() => {
        slides[index].classList.remove('is-active');
        index = (index + 1) % slides.length;
        slides[index].classList.add('is-active');
    }, 8000);
});
