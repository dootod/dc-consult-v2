/**
 * DC Consult — Gestion Projets Admin
 *
 * Upload strategy:
 * - L'<input> est placé DANS le <label> (label wrapping) → sélecteur natif garanti.
 * - Carousel : on ne fait JAMAIS input.value = '' car ça vide les fichiers.
 *   On accumule via DataTransfer et on réécrit input.files à chaque fois.
 *   La liste JS `fileList` est la source de vérité pour les previews.
 */

const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

function readAsDataURL(file) {
    return new Promise((resolve, reject) => {
        const r = new FileReader();
        r.onload  = e => resolve(e.target.result);
        r.onerror = () => reject(new Error('Lecture impossible'));
        r.readAsDataURL(file);
    });
}

// ─────────────────────────────────────────────────────────────
// COVER
// ─────────────────────────────────────────────────────────────
function initCover() {
    const zone    = document.querySelector('.js-cover-zone');
    const input   = document.querySelector('.js-cover-input');
    const preview = document.querySelector('.js-cover-preview');
    const img     = document.querySelector('.js-cover-img');
    const clear   = document.querySelector('.js-cover-clear');
    const icon    = document.querySelector('.js-cover-icon');
    const text    = document.querySelector('.js-cover-text');

    if (!input || !zone) return;

    input.addEventListener('change', () => {
        const f = input.files?.[0];
        if (f && MIME_TYPES.includes(f.type)) show(f);
    });

    // Drag & drop
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('is-dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
    zone.addEventListener('dragend',   () => zone.classList.remove('is-dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('is-dragover');
        const f = [...(e.dataTransfer?.files ?? [])].find(f => MIME_TYPES.includes(f.type));
        if (!f) return;
        const dt = new DataTransfer(); dt.items.add(f); input.files = dt.files;
        show(f);
    });

    clear?.addEventListener('click', e => {
        e.preventDefault(); e.stopPropagation();
        input.value = ''; // OK pour la cover — un seul fichier, pas d'accumulation
        reset();
    });

    async function show(file) {
        try {
            const url = await readAsDataURL(file);
            if (img)     img.src = url;
            if (preview) { preview.style.display = ''; preview.removeAttribute('aria-hidden'); }
            if (zone)    zone.classList.add('has-file');
            if (text)    text.textContent = '✓ ' + file.name;
            if (icon)    { icon.className = 'fa-solid fa-circle-check pj-dropzone__icon js-cover-icon'; icon.style.color = '#16a34a'; }
        } catch { /* silencieux */ }
    }

    function reset() {
        if (img)     img.src = '';
        if (preview) { preview.style.display = 'none'; preview.setAttribute('aria-hidden', 'true'); }
        if (zone)    zone.classList.remove('has-file');
        if (text)    text.textContent = 'Cliquez ou glissez votre image ici';
        if (icon)    { icon.className = 'fa-solid fa-cloud-arrow-up pj-dropzone__icon js-cover-icon'; icon.style.color = ''; }
    }
}

// ─────────────────────────────────────────────────────────────
// CAROUSEL
// ─────────────────────────────────────────────────────────────
function initCarousel() {
    const zone      = document.querySelector('.js-carousel-zone');
    const input     = document.querySelector('.js-carousel-input');
    const textEl    = document.querySelector('.js-carousel-text');
    const orderCard = document.querySelector('.js-order-card');
    const orderGrid = document.querySelector('.js-order-grid');

    if (!input || !zone) return;

    // Source de vérité JS pour les previews et la déduplication
    let fileList = [];

    input.addEventListener('change', () => {
        if (!input.files?.length) return;
        merge([...input.files]);
    });

    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('is-dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
    zone.addEventListener('dragend',   () => zone.classList.remove('is-dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('is-dragover');
        merge([...(e.dataTransfer?.files ?? [])]);
    });

    /**
     * Fusionne les nouveaux fichiers dans fileList (déduplication par nom),
     * puis réécrit input.files avec la liste complète.
     *
     * IMPORTANT : on ne fait JAMAIS input.value = '' ici.
     * On réécrit input.files directement — c'est ce que le navigateur
     * enverra dans le POST multipart au submit.
     */
    function merge(incoming) {
        const known = new Set(fileList.map(f => f.name));
        const toAdd = incoming.filter(f => MIME_TYPES.includes(f.type) && !known.has(f.name));
        if (!toAdd.length) return;

        fileList = [...fileList, ...toAdd];

        // Réécriture de input.files avec la liste complète accumulée
        const dt = new DataTransfer();
        fileList.forEach(f => dt.items.add(f));
        input.files = dt.files;

        updateLabel();
        renderGrid();
    }

    function remove(index) {
        fileList.splice(index, 1);

        const dt = new DataTransfer();
        fileList.forEach(f => dt.items.add(f));
        input.files = dt.files;

        updateLabel();
        renderGrid();
    }

    function updateLabel() {
        const n = fileList.length;
        if (textEl) textEl.textContent = n > 0
            ? `${n} image${n > 1 ? 's' : ''} sélectionnée${n > 1 ? 's' : ''}`
            : 'Cliquez ou glissez vos images ici';
        zone.classList.toggle('has-file', n > 0);
    }

    async function renderGrid() {
        if (!orderCard || !orderGrid) return;
        if (!fileList.length) { orderCard.style.display = 'none'; return; }
        orderCard.style.display = '';

        // Lecture parallèle des previews
        const urls = await Promise.all(fileList.map(readAsDataURL));

        orderGrid.innerHTML = '';
        fileList.forEach((file, i) => {
            const item = document.createElement('div');
            item.className = 'pj-order-item';

            const im = document.createElement('img');
            im.src = urls[i]; im.alt = file.name;
            item.appendChild(im);

            const num = document.createElement('span');
            num.className = 'pj-order-num';
            num.textContent = i + 1;
            item.appendChild(num);

            const btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'pj-order-remove'; btn.title = 'Retirer';
            btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            btn.addEventListener('click', () => remove(i));
            item.appendChild(btn);

            const name = document.createElement('span');
            name.className = 'pj-order-name';
            name.textContent = file.name;
            item.appendChild(name);

            orderGrid.appendChild(item);
        });
    }
}

// ─────────────────────────────────────────────────────────────
// SUPPRESSION checkboxes — page edit
// ─────────────────────────────────────────────────────────────
function initDeleteCheckboxes() {
    document.querySelectorAll('.js-delete-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('.js-delete-thumb')?.classList.toggle('is-marked-delete', cb.checked);
        });
    });
}

// ─────────────────────────────────────────────────────────────
// BOOT
// ─────────────────────────────────────────────────────────────
function init() {
    initCover();
    initCarousel();
    initDeleteCheckboxes();
}

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();