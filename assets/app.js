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

    // ── Gestion du menu burger + collapse navbar ──
    const navbarCollapse = document.getElementById('navbarContent') || document.getElementById('navbarEspaceContent');
    const navbarToggler = document.getElementById('navbarToggler');

    if (navbarCollapse && navbarToggler) {
        const bsCollapseObj = new bootstrap.Collapse(navbarCollapse, { toggle: false });
        const navLinks = navbarCollapse.querySelectorAll('a');
        
        // Update aria-expanded de l'animation burger quand le collapse change
        navbarCollapse.addEventListener('show.bs.collapse', () => {
            navbarToggler.setAttribute('aria-expanded', 'true');
        });
        
        navbarCollapse.addEventListener('hide.bs.collapse', () => {
            navbarToggler.setAttribute('aria-expanded', 'false');
        });
        
        // Clic sur le burger menu pour fermer si ouvert
        navbarToggler.addEventListener('click', () => {
            // Bootstrap gère déjà le toggle via data-bs-toggle, mais on peut ajouter logique
            // La synchronisation d'aria-expanded se fera via les événements ci-dessus
        });
        
        // Ferme quand on clique sur un lien
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                bsCollapseObj.hide();
            });
        });
    }

    // ── Gestion du drawer sidebar mobile ──
    const sidebar = document.querySelector('.dc-sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarToggler = document.getElementById('sidebarToggler');
    
    if (sidebar && sidebarOverlay && sidebarToggler) {
        const sidebarLinks = sidebar.querySelectorAll('a');
        
        // Fonction pour fermer la sidebar
        const closeSidebar = () => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        };
        
        // Ouvrir/fermer quand on clique le bouton
        sidebarToggler.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        });
        
        // Fermer via l'overlay
        sidebarOverlay.addEventListener('click', closeSidebar);
        
        // Fermer quand on clique sur un lien de la sidebar
        sidebarLinks.forEach(link => {
            link.addEventListener('click', closeSidebar);
        });
    }
});
