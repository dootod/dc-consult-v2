/**
 * DC Consult — Page Logiciels (logiciels.js)
 *
 * - Compteurs animés dans le hero (IntersectionObserver)
 * - Reveal des sections logiciels au scroll
 * - Reveal des étapes du pipeline avec délai séquentiel
 * - Reveal des cartes avantages
 * - Effet tilt 3D sur les cartes logiciels (mouse tracking)
 * - Pause ticker au focus clavier
 */

'use strict';


/* ── Compteurs animés ─────────────────────────────────────────── */
function initCounters() {
    const counters = document.querySelectorAll('.lg-hero__counter-val');
    if (!counters.length) return;

    const duration = 1600;

    function animateCounter(el) {
        const target  = parseInt(el.dataset.target, 10);
        const isPct   = el.nextElementSibling?.classList.contains('js-pct');
        const start   = performance.now();

        function tick(now) {
            const elapsed  = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased    = 1 - Math.pow(1 - progress, 4);
            const value    = Math.round(eased * target);

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


/* ── Reveal des blocs logiciels au scroll ─────────────────────── */
function initSoftReveal() {
    const items = document.querySelectorAll('.js-lg-soft');
    if (!items.length) return;

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el    = entry.target;
                const delay = parseInt(el.dataset.index ?? 0, 10) * 0.15;
                el.style.transitionDelay = `${delay}s`;
                el.classList.add('is-visible');
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });

    items.forEach(el => observer.observe(el));
}


/* ── Reveal des étapes du pipeline ────────────────────────────── */
function initStepReveal() {
    const steps = document.querySelectorAll('.js-lg-step');
    if (!steps.length) return;

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const step  = entry.target;
                const index = parseInt(step.dataset.step ?? 1, 10) - 1;
                step.style.transitionDelay = `${index * 0.18}s`;
                step.classList.add('is-visible');
                observer.unobserve(step);
            }
        });
    }, { threshold: 0.2, rootMargin: '0px 0px -40px 0px' });

    steps.forEach(el => observer.observe(el));
}


/* ── Reveal des cartes avantages ──────────────────────────────── */
function initBenefitReveal() {
    const cards = document.querySelectorAll('.js-lg-benefit');
    if (!cards.length) return;

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


/* ── Effet Tilt 3D au survol des cartes logiciels ─────────────── */
function initTilt() {
    const cards = document.querySelectorAll('.js-tilt');
    if (!cards.length) return;

    // Ne pas activer sur mobile / tablette
    if (window.matchMedia('(max-width: 991px)').matches) return;

    cards.forEach(card => {
        const face = card.querySelector('.lg-soft__card-face');
        if (!face) return;

        card.addEventListener('mousemove', (e) => {
            const rect   = card.getBoundingClientRect();
            const x      = e.clientX - rect.left;
            const y      = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = ((y - centerY) / centerY) * -8;
            const rotateY = ((x - centerX) / centerX) *  8;

            face.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;
        });

        card.addEventListener('mouseleave', () => {
            face.style.transform = '';
            face.style.transition = 'transform 0.4s ease';
            setTimeout(() => { face.style.transition = ''; }, 400);
        });

        card.addEventListener('mouseenter', () => {
            face.style.transition = 'none';
        });
    });
}


/* ── Pause ticker au focus clavier ────────────────────────────── */
function initTickerA11y() {
    const track = document.querySelector('.lg-ticker__track');
    if (!track) return;

    track.addEventListener('focusin',  () => track.style.animationPlayState = 'paused');
    track.addEventListener('focusout', () => track.style.animationPlayState = '');
}


/* ── Boot ─────────────────────────────────────────────────────── */
function init() {
    initCounters();
    initSoftReveal();
    initStepReveal();
    initBenefitReveal();
    initTilt();
    initTickerA11y();
}

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();
