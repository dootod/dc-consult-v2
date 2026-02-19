import './stimulus_bootstrap.js';

/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// ── Effet shadow sur la navbar au scroll ──────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.getElementById('mainNavbar');

    if (!navbar) return;

    const onScroll = () => {
        navbar.classList.toggle('is-scrolled', window.scrollY > 10);
    };

    window.addEventListener('scroll', onScroll, { passive: true });

    // Appel initial au cas où la page est déjà scrollée
    onScroll();
});