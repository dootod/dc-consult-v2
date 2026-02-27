/**
 * DC Consult — Gestion Projets Admin
 *
 * Stratégie upload :
 *  - L'input[type=file] est placé PHYSIQUEMENT DANS le <label> (label wrapping).
 *  - Le clic sur le label déclenche nativement l'input, sans JS et sans attribut for.
 *  - Ce fichier gère uniquement les previews et la synchronisation de l'état.
 *
 * Sélecteurs : classes prefixées js- pour découpler CSS et comportement.
 */

const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/** Lit un fichier et renvoie une data-URL */
function readAsDataURL(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = () => reject(new Error('Lecture impossible'));
        reader.readAsDataURL(file);
    });
}

/** Construit un FileList à partir d'un tableau de File via DataTransfer */
function buildFileList(files) {
    const dt = new DataTransfer();
    files.forEach((f) => dt.items.add(f));
    return dt.files;
}

// ═══════════════════════════════════════════════════════════════
// MODULE COVER
// ═══════════════════════════════════════════════════════════════
function initCoverUpload() {
    const zone     = document.querySelector('.js-cover-zone');
    const input    = document.querySelector('.js-cover-input');
    const preview  = document.querySelector('.js-cover-preview');
    const img      = document.querySelector('.js-cover-img');
    const clearBtn = document.querySelector('.js-cover-clear');
    const icon     = document.querySelector('.js-cover-icon');
    const text     = document.querySelector('.js-cover-text');

    // Si aucun de ces éléments n'existe, on n'est pas sur une page concernée
    if (!input || !zone) return;

    // ── Changement de fichier via le sélecteur natif ──
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (file && MIME_TYPES.includes(file.type)) {
            showCoverPreview(file);
        } else if (input.files?.length > 0) {
            // Fichier sélectionné mais mauvais type
            resetCoverPreview();
        }
    });

    // ── Drag & drop ──
    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('is-dragover');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
    zone.addEventListener('dragend',   () => zone.classList.remove('is-dragover'));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('is-dragover');

        const file = [...(e.dataTransfer?.files ?? [])]
            .find((f) => MIME_TYPES.includes(f.type));

        if (!file) return;

        // Injecter le fichier dans l'input via DataTransfer
        input.files = buildFileList([file]);
        showCoverPreview(file);
    });

    // ── Bouton "retirer" ──
    clearBtn?.addEventListener('click', (e) => {
        // Empêche le clic de remonter vers le label (ce qui ré-ouvrirait le sélecteur)
        e.preventDefault();
        e.stopPropagation();
        input.value = '';
        resetCoverPreview();
    });

    async function showCoverPreview(file) {
        try {
            const url = await readAsDataURL(file);
            if (img)     img.src = url;
            if (preview) { preview.style.display = ''; preview.removeAttribute('aria-hidden'); }
            if (zone)    zone.classList.add('has-file');
            if (text)    text.textContent = '✓ ' + file.name;
            if (icon)    { icon.className = 'fa-solid fa-circle-check pj-dropzone__icon js-cover-icon'; icon.style.color = '#16a34a'; }
        } catch {
            // Silencieux — l'utilisateur verra juste le nom sans preview
        }
    }

    function resetCoverPreview() {
        if (img)     img.src = '';
        if (preview) { preview.style.display = 'none'; preview.setAttribute('aria-hidden', 'true'); }
        if (zone)    zone.classList.remove('has-file');
        if (text)    text.textContent = 'Cliquez ou glissez votre image ici';
        if (icon)    { icon.className = 'fa-solid fa-cloud-arrow-up pj-dropzone__icon js-cover-icon'; icon.style.color = ''; }
    }
}

// ═══════════════════════════════════════════════════════════════
// MODULE CAROUSEL
// ═══════════════════════════════════════════════════════════════
function initCarouselUpload() {
    const zone     = document.querySelector('.js-carousel-zone');
    const input    = document.querySelector('.js-carousel-input');
    const textEl   = document.querySelector('.js-carousel-text');
    const orderCard = document.querySelector('.js-order-card');
    const orderGrid = document.querySelector('.js-order-grid');

    if (!input || !zone) return;

    // Fichiers sélectionnés côté JS (on gère notre propre liste pour cumuler)
    let selectedFiles = [];

    // ── Changement via le sélecteur natif ──
    input.addEventListener('change', () => {
        if (!input.files?.length) return;
        addFiles([...input.files]);
        // Reset l'input pour permettre de re-sélectionner les mêmes fichiers
        input.value = '';
    });

    // ── Drag & drop ──
    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('is-dragover');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
    zone.addEventListener('dragend',   () => zone.classList.remove('is-dragover'));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('is-dragover');
        const files = [...(e.dataTransfer?.files ?? [])];
        if (files.length) addFiles(files);
    });

    function addFiles(newFiles) {
        const existingNames = new Set(selectedFiles.map((f) => f.name));
        const toAdd = newFiles.filter(
            (f) => MIME_TYPES.includes(f.type) && !existingNames.has(f.name)
        );
        if (!toAdd.length) return;

        selectedFiles = [...selectedFiles, ...toAdd];
        syncInput();
        updateZoneLabel();
        renderOrderGrid();
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        syncInput();
        updateZoneLabel();
        renderOrderGrid();
    }

    function syncInput() {
        input.files = buildFileList(selectedFiles);
    }

    function updateZoneLabel() {
        const n = selectedFiles.length;
        if (textEl) {
            textEl.textContent = n > 0
                ? `${n} image${n > 1 ? 's' : ''} sélectionnée${n > 1 ? 's' : ''}`
                : 'Cliquez ou glissez vos images ici';
        }
        zone.classList.toggle('has-file', n > 0);
    }

    async function renderOrderGrid() {
        if (!orderCard || !orderGrid) return;

        if (!selectedFiles.length) {
            orderCard.style.display = 'none';
            return;
        }

        orderCard.style.display = '';

        // Lire toutes les images en parallèle
        const urls = await Promise.all(selectedFiles.map(readAsDataURL));

        orderGrid.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'pj-order-item';

            const imgEl = document.createElement('img');
            imgEl.src = urls[index];
            imgEl.alt = file.name;
            item.appendChild(imgEl);

            const num = document.createElement('span');
            num.className = 'pj-order-num';
            num.textContent = index + 1;
            item.appendChild(num);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'pj-order-remove';
            removeBtn.title = 'Retirer cette image';
            removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            removeBtn.addEventListener('click', () => removeFile(index));
            item.appendChild(removeBtn);

            const nameEl = document.createElement('span');
            nameEl.className = 'pj-order-name';
            nameEl.textContent = file.name;
            item.appendChild(nameEl);

            orderGrid.appendChild(item);
        });
    }
}

// ═══════════════════════════════════════════════════════════════
// MODULE SUPPRESSION (page edit uniquement)
// ═══════════════════════════════════════════════════════════════
function initDeleteCheckboxes() {
    document.querySelectorAll('.js-delete-checkbox').forEach((cb) => {
        cb.addEventListener('change', () => {
            cb.closest('.js-delete-thumb')
              ?.classList.toggle('is-marked-delete', cb.checked);
        });
    });
}

// ═══════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════
function init() {
    initCoverUpload();
    initCarouselUpload();
    initDeleteCheckboxes();
}

// Compatible avec AssetMapper (module ES) et DOMContentLoaded classique
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}