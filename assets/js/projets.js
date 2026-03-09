/**
 * DC Consult — Projets : chargement "Voir plus"
 * assets/js/projets.js
 *
 * Charge dynamiquement les projets suivants via XHR et les injecte
 * dans la grille avec une animation fade-in.
 */

(function () {
    'use strict';

    const grid    = document.getElementById('projetsGrid');
    const wrapper = document.getElementById('projetsLoadMoreWrapper');

    if (!grid) return;

    /**
     * Met à jour le compteur affiché et masque le bouton si tout est chargé.
     */
    function updateState(total, loaded) {
        const countEl = document.querySelector('.js-projets-count');
        if (countEl) countEl.textContent = loaded;

        const hintEl = wrapper ? wrapper.querySelector('.projets-load-more__hint') : null;
        const remaining = total - loaded;

        if (hintEl) {
            hintEl.textContent = remaining > 0
                ? remaining + ' projet' + (remaining > 1 ? 's' : '') + ' restant' + (remaining > 1 ? 's' : '')
                : '';
        }

        if (loaded >= total && wrapper) {
            wrapper.remove();
        }
    }

    /**
     * Charge la prochaine page de projets via XHR et injecte le HTML.
     */
    async function loadMore(btn) {
        const offset = parseInt(btn.dataset.offset, 10);
        const url    = '/projets/voir-plus?offset=' + offset;

        // UI : spinner
        btn.disabled = true;
        btn.querySelector('.js-load-more-label').style.display   = 'none';
        btn.querySelector('.js-load-more-spinner').style.display = '';

        try {
            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!resp.ok) throw new Error('Erreur réseau : ' + resp.status);

            const html = await resp.text();

            // Injecter dans un fragment temporaire
            const tmp = document.createElement('div');
            tmp.innerHTML = html;

            // Lire la meta de pagination
            const meta    = tmp.querySelector('.js-projets-pagination-meta');
            const total   = meta ? parseInt(meta.dataset.total, 10)  : 0;
            const loaded  = meta ? parseInt(meta.dataset.loaded, 10) : offset;

            // Insérer les nouvelles cartes avec animation
            tmp.querySelectorAll('.col-12').forEach(function (card) {
                card.style.opacity   = '0';
                card.style.transform = 'translateY(20px)';
                grid.appendChild(card);

                requestAnimationFrame(function () {
                    card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    card.style.opacity    = '1';
                    card.style.transform  = 'translateY(0)';
                });
            });

            // Mettre à jour l'offset et le compteur
            btn.dataset.offset = loaded;
            updateState(total, loaded);

        } catch (err) {
            console.error('[Projets] Erreur chargement :', err);
        } finally {
            btn.disabled = false;
            btn.querySelector('.js-load-more-label').style.display   = '';
            btn.querySelector('.js-load-more-spinner').style.display = 'none';
        }
    }

    // Délégation d'événement sur le bouton "Voir plus"
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-load-more');
        if (btn) loadMore(btn);
    });

})();