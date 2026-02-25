import './stimulus_bootstrap.js';

/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import './styles/auth.css';
import './js/admin-documents.js';
import './js/admin-users.js';
import './js/auth.js';

const initAppUi = () => {
    // ── Effet shadow sur la navbar au scroll ──────────────────────
    const navbar = document.getElementById('mainNavbar');

    if (!navbar) return;

    const onScroll = () => {
        navbar.classList.toggle('is-scrolled', window.scrollY > 10);
    };

    window.addEventListener('scroll', onScroll, { passive: true });

    // Appel initial au cas où la page est déjà scrollée
    onScroll();

    // ── Gestion du menu burger + collapse navbar ──
    const navbarCollapse = document.getElementById('navbarContent') || document.getElementById('navbarEspaceContent');
    const navbarToggler = document.getElementById('navbarToggler');

    if (navbarCollapse && navbarToggler) {
        const navLinks = navbarCollapse.querySelectorAll('a');

        // Update aria-expanded pour l'animation burger CSS
        navbarCollapse.addEventListener('show.bs.collapse', () => {
            navbarToggler.setAttribute('aria-expanded', 'true');
        });

        navbarCollapse.addEventListener('hide.bs.collapse', () => {
            navbarToggler.setAttribute('aria-expanded', 'false');
        });

        // Ferme le menu uniquement pour les liens "internes" (ancres),
        // afin d'éviter l'effet de rétractation juste avant un changement de page
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                const href = link.getAttribute('href') || '';

                // Si le lien pointe vers une ancre de la page actuelle, on ferme le menu
                if (href.startsWith('#')) {
                    const bsCollapse = bootstrap?.Collapse?.getOrCreateInstance
                        ? bootstrap.Collapse.getOrCreateInstance(navbarCollapse)
                        : bootstrap?.Collapse?.getInstance?.(navbarCollapse);
                    bsCollapse?.hide?.();
                }
            });
        });
    }

    // ── Sidebar mobile toggle ──────────────────────────────────────
    const sidebarToggler = document.getElementById('sidebarToggler');
    const sidebar = document.querySelector('.dc-sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggler && sidebar && overlay) {
        const openSidebar = () => {
            sidebar.classList.add('show');
            overlay.classList.add('show');
            sidebarToggler.setAttribute('aria-label', 'Fermer la navigation');
        };

        const closeSidebar = () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            sidebarToggler.setAttribute('aria-label', 'Ouvrir la navigation');
        };

        sidebarToggler.addEventListener('click', () => {
            sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
        });

        // Fermer en cliquant sur l'overlay
        overlay.addEventListener('click', closeSidebar);

        // Fermer en cliquant sur un lien de la sidebar
        sidebar.querySelectorAll('.dc-sidebar__link').forEach(link => {
            link.addEventListener('click', closeSidebar);
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAppUi);
} else {
    initAppUi();
}