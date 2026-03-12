/* assets/js/contact.js
   - Leaflet OpenStreetMap (fonctionne en local + prod, sans clé API)
   - Compteur de caractères
   - Loading state sur submit
   - Auto-dismiss flash success
*/

const initContact = () => {

    // ── Carte Leaflet / OpenStreetMap ──────────────────────────────
    const mapEl = document.getElementById('contactMap');
    if (mapEl && typeof L !== 'undefined') {

        // Coordonnées : 10 Allée des Champs Élysées, 91080 Évry-Courcouronnes
        const lat = 48.6256;
        const lng = 2.4479;

        const map = L.map('contactMap', {
            center: [lat, lng],
            zoom: 16,
            zoomControl: true,
            scrollWheelZoom: false,
        });

        // Tuiles OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);

        // Marqueur personnalisé couleur DC Consult
        const markerIcon = L.divIcon({
            className: '',
            html: `<div style="
                width:36px;height:36px;
                background:#0055A4;
                border:3px solid #fff;
                border-radius:50% 50% 50% 0;
                transform:rotate(-45deg);
                box-shadow:0 4px 12px rgba(0,85,164,0.45);
            "></div>`,
            iconSize:    [36, 36],
            iconAnchor:  [18, 36],
            popupAnchor: [0, -38],
        });

        const marker = L.marker([lat, lng], { icon: markerIcon }).addTo(map);

        marker.bindPopup(`
            <div style="font-family:'Montserrat',sans-serif;font-size:13px;line-height:1.5;min-width:160px;">
                <strong style="color:#0055A4;display:block;margin-bottom:4px;">DC Consult</strong>
                10 Allée des Champs Élysées<br>91080 Évry-Courcouronnes
            </div>
        `, { maxWidth: 220 }).openPopup();
    }

    // ── Compteur de caractères ─────────────────────────────────────
    const textarea    = document.getElementById('contact_message');
    const charCounter = document.getElementById('contactCharCounter');

    if (textarea && charCounter) {
        const MAX = 3000;
        const update = () => {
            const len = textarea.value.length;
            charCounter.textContent = `${len} / ${MAX}`;
            charCounter.classList.remove('is-warning', 'is-danger');
            if (MAX - len < 100)      charCounter.classList.add('is-danger');
            else if (MAX - len < 300) charCounter.classList.add('is-warning');
        };
        textarea.addEventListener('input', update);
        update();
    }

    // ── Loading state submit ───────────────────────────────────────
    const form = document.getElementById('contactForm');
    const btn  = document.getElementById('contactSubmitBtn');
    if (form && btn) {
        form.addEventListener('submit', () => btn.classList.add('is-loading'));
    }

    // ── Auto-dismiss flash success après 6s ───────────────────────
    document.querySelectorAll('.contact-flash[data-auto-dismiss]').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            el.style.opacity    = '0';
            el.style.transform  = 'translateY(-8px)';
            setTimeout(() => el.remove(), 500);
        }, 6000);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initContact);
} else {
    initContact();
}