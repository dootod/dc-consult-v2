/**
 * DC Consult — Page d'accueil (home.js)
 * - Carte Leaflet / OpenStreetMap
 * - Accordéon FAQ
 * - Animations au scroll (IntersectionObserver)
 */

'use strict';

// ── Carte Leaflet ──────────────────────────────────────────────────────────
function initHomeMap() {
    const mapEl = document.getElementById('homeMap');
    if (!mapEl || typeof L === 'undefined') return;

    const LAT = 48.6256;
    const LNG = 2.4479;

    const map = L.map('homeMap', {
        center: [LAT, LNG],
        zoom: 16,
        zoomControl: true,
        scrollWheelZoom: false,
        dragging: !L.Browser.mobile,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    // Marqueur personnalisé couleur DC Consult
    const markerHtml = `
        <div style="
            position:relative;
            width:40px;height:40px;
        ">
            <div style="
                position:absolute;
                inset:0;
                background:rgba(0,85,164,0.18);
                border-radius:50%;
                animation:homeMapPing 2s ease-in-out infinite;
            "></div>
            <div style="
                position:absolute;
                top:50%;left:50%;
                transform:translate(-50%,-60%) rotate(-45deg);
                width:28px;height:28px;
                background:var(--color-primary,#0055A4);
                border:3px solid #fff;
                border-radius:50% 50% 50% 0;
                box-shadow:0 4px 14px rgba(0,85,164,0.5);
            "></div>
        </div>`;

    const style = document.createElement('style');
    style.textContent = `@keyframes homeMapPing {
        0%,100%{transform:scale(1);opacity:.6}
        50%{transform:scale(1.8);opacity:0}
    }`;
    document.head.appendChild(style);

    const icon = L.divIcon({
        className: '',
        html: markerHtml,
        iconSize:    [40, 40],
        iconAnchor:  [20, 40],
        popupAnchor: [0, -44],
    });

    const marker = L.marker([LAT, LNG], { icon }).addTo(map);

    marker.bindPopup(`
        <div style="font-family:'Montserrat',sans-serif;font-size:13px;line-height:1.6;min-width:180px;">
            <strong style="color:#0055A4;display:block;margin-bottom:4px;font-size:14px;">DC Consult</strong>
            <span style="color:#555;">10 Allée des Champs Élysées<br>91080 Évry-Courcouronnes</span>
            <br>
            <a href="https://maps.google.com/?q=DC+Consult+10+Allée+des+Champs+Élysées+91080+Évry-Courcouronnes"
               target="_blank" rel="noopener noreferrer"
               style="color:#0055A4;font-weight:600;font-size:12px;text-decoration:none;display:inline-block;margin-top:6px;">
                Ouvrir dans Google Maps →
            </a>
        </div>
    `, { maxWidth: 240 }).openPopup();
}

// ── FAQ Accordéon ──────────────────────────────────────────────────────────
function initFaq() {
    document.querySelectorAll('.js-faq-item').forEach(item => {
        const btn    = item.querySelector('.js-faq-btn');
        const answer = item.querySelector('.home-faq__answer');
        if (!btn || !answer) return;

        btn.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');

            // Fermer tous
            document.querySelectorAll('.js-faq-item.is-open').forEach(openItem => {
                if (openItem !== item) {
                    openItem.classList.remove('is-open');
                    const a = openItem.querySelector('.home-faq__answer');
                    const b = openItem.querySelector('.js-faq-btn');
                    if (a) a.classList.remove('is-open');
                    if (b) b.setAttribute('aria-expanded', 'false');
                }
            });

            // Toggle cet item
            item.classList.toggle('is-open', !isOpen);
            answer.classList.toggle('is-open', !isOpen);
            btn.setAttribute('aria-expanded', (!isOpen).toString());
        });
    });
}

// ── Animations au scroll (IntersectionObserver) ────────────────────────────
function initScrollAnimations() {
    if (!('IntersectionObserver' in window)) return;

    const targets = document.querySelectorAll([
        '.home-service-card',
        '.home-process__step',
        '.home-why__card',
        '.home-faq__item',
        '.home-about__values li',
    ].join(','));

    targets.forEach((el, i) => {
        el.style.opacity  = '0';
        el.style.transform = 'translateY(24px)';
        el.style.transition = `opacity 0.55s ease ${(i % 6) * 0.08}s, transform 0.55s ease ${(i % 6) * 0.08}s`;
    });

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity   = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    targets.forEach(el => observer.observe(el));
}

// ── Smooth scroll pour les ancres internes ─────────────────────────────────
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', e => {
            const id  = link.getAttribute('href').slice(1);
            const target = document.getElementById(id);
            if (!target) return;
            e.preventDefault();
            const top = target.getBoundingClientRect().top + window.scrollY - 80;
            window.scrollTo({ top, behavior: 'smooth' });
        });
    });
}

// ── Boot ───────────────────────────────────────────────────────────────────
function init() {
    initHomeMap();
    initFaq();
    initScrollAnimations();
    initSmoothScroll();
}

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();