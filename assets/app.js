import './stimulus_bootstrap.js';

/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import './styles/auth.css';
import './styles/projets-admin.css';
import './styles/projets.css';
import './styles/contact.css';
import './js/projets.js';
import './js/admin-documents.js';
import './js/admin-users.js';
import './js/auth.js';
import './js/projets-admin.js';

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

    // ── Gestion du menu burger + collapse navbar ──────────────────
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

        // Ferme le menu uniquement pour les liens "internes" (ancres)
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                const href = link.getAttribute('href') || '';
                if (href.startsWith('#')) {
                    const bsCollapse = bootstrap?.Collapse?.getOrCreateInstance
                        ? bootstrap.Collapse.getOrCreateInstance(navbarCollapse)
                        : bootstrap?.Collapse?.getInstance?.(navbarCollapse);
                    bsCollapse?.hide?.();
                }
            });
        });
    }

    // ── Gestion du sidebar drawer mobile (pages espace/admin) - Ne pas supprimer ────
    const sidebarToggler = document.getElementById('sidebarToggler');
    const sidebar        = document.querySelector('.dc-sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (sidebarToggler && sidebar) {
        const openSidebar = () => {
            sidebar.classList.add('show');
            if (sidebarOverlay) sidebarOverlay.classList.add('show');
            sidebarToggler.setAttribute('aria-expanded', 'true');
        };

        const closeSidebar = () => {
            sidebar.classList.remove('show');
            if (sidebarOverlay) sidebarOverlay.classList.remove('show');
            sidebarToggler.setAttribute('aria-expanded', 'false');
        };

        sidebarToggler.addEventListener('click', () => {
            const isOpen = sidebar.classList.contains('show');
            isOpen ? closeSidebar() : openSidebar();
        });

        // Fermer via l'overlay (clic en dehors de la sidebar)
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }

        // Fermer la sidebar quand on clique sur un lien de navigation
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