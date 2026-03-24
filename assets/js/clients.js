/**
 * DC Consult — Page Clients (clients.js)
 *
 * - Compteurs animés dans le hero (IntersectionObserver)
 * - Filtrage par catégorie avec animation
 * - Reveal des cartes au scroll
 */

'use strict';

/* ── Compteurs animés ─────────────────────────────────────────── */
function initCounters() {
    const counters = document.querySelectorAll('.cl-hero__counter-val');
    if (!counters.length) return;

    const duration = 1600; // ms

    function animateCounter(el) {
        const target  = parseInt(el.dataset.target, 10);
        const isPct   = el.nextElementSibling?.classList.contains('js-pct');
        const start   = performance.now();

        function tick(now) {
            const elapsed  = now - start;
            const progress = Math.min(elapsed / duration, 1);
            // Ease-out quart
            const eased = 1 - Math.pow(1 - progress, 4);
            const value  = Math.round(eased * target);

            el.textContent = isPct ? value + '%' : value;

            if (progress < 1) requestAnimationFrame(tick);
            else el.textContent = isPct ? target + '%' : target;
        }

        requestAnimationFrame(tick);
    }

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(el => observer.observe(el));
}


/* ── Reveal des cartes au scroll ──────────────────────────────── */
function initCardReveal() {
    const cards = document.querySelectorAll('.js-cl-card');
    if (!cards.length || !('IntersectionObserver' in window)) {
        // Fallback : tout afficher
        cards.forEach(c => c.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const card  = entry.target;
                const delay = parseInt(card.dataset.index ?? 0, 10) % 3;
                card.style.transitionDelay = `${delay * 0.08}s`;
                card.classList.add('is-visible');
                observer.unobserve(card);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    cards.forEach(card => observer.observe(card));
}


/* ── Filtrage par catégorie ───────────────────────────────────── */
function initFilters() {
    const filterBtns = document.querySelectorAll('.cl-filter');
    const cards      = document.querySelectorAll('.js-cl-card');
    const emptyState = document.querySelector('.js-cl-empty');
    const grid       = document.querySelector('.js-cl-grid');

    if (!filterBtns.length || !cards.length) return;

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.filter;

            // Mise à jour des boutons actifs
            filterBtns.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');

            let visibleCount = 0;

            cards.forEach((card, i) => {
                const cat   = card.dataset.cat;
                const match = filter === 'all' || cat === filter;

                if (match) {
                    card.classList.remove('is-hidden');
                    // Re-déclencher l'animation d'entrée
                    card.style.opacity   = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'none';

                    // Délai en fonction de la position dans les visibles
                    const visibleIndex = visibleCount;
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            card.style.transition = `opacity 0.4s ease ${visibleIndex * 0.06}s, transform 0.4s ease ${visibleIndex * 0.06}s`;
                            card.style.opacity   = '1';
                            card.style.transform = 'translateY(0)';
                        });
                    });

                    visibleCount++;
                } else {
                    card.classList.add('is-hidden');
                    card.style.opacity   = '';
                    card.style.transform = '';
                    card.style.transition = '';
                }
            });

            // Afficher/masquer empty state
            if (emptyState) {
                emptyState.hidden = visibleCount > 0;
            }

            // Recalcule les colonnes si besoin (pas nécessaire ici, CSS gère)
        });
    });
}


/* ── Pause ticker au focus clavier ───────────────────────────── */
function initTickerA11y() {
    const track = document.querySelector('.cl-ticker__track');
    if (!track) return;

    track.addEventListener('focusin',  () => track.style.animationPlayState = 'paused');
    track.addEventListener('focusout', () => track.style.animationPlayState = '');
}


/* ── Boot ─────────────────────────────────────────────────────── */
function init() {
    initCounters();
    initCardReveal();
    initFilters();
    initTickerA11y();
}

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();