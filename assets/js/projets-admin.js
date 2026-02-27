/**
 * DC Consult — Gestion Projets Admin
 *
 * Carousel : un <label id="carouselDropLabel" for="carouselFilesInput"> sert
 * de zone cliquable — déclenchement natif du sélecteur, 100% cross-browser.
 * Pas de input.click() programmatique qui peut être bloqué par les navigateurs.
 */

'use strict';

const MIME_OK = ['image/jpeg', 'image/png', 'image/webp'];

function toDataURL(file) {
    return new Promise((res, rej) => {
        const r = new FileReader();
        r.onload  = e => res(e.target.result);
        r.onerror = ()  => rej(new Error('Lecture impossible'));
        r.readAsDataURL(file);
    });
}

// ─────────────────────────────────────────────────────────────
// État partagé
// ─────────────────────────────────────────────────────────────
let coverFile     = null;
let coverDataURL  = null;
let carouselFiles = [];

// ─────────────────────────────────────────────────────────────
// PREVIEW UNIFIÉE — ordre carousel
// ─────────────────────────────────────────────────────────────
async function renderOrderPreview() {
    const wrap = document.getElementById('orderPreviewWrap');
    const grid = document.getElementById('orderPreviewGrid');
    if (!wrap || !grid) return;

    const hasCover    = !!coverDataURL;
    const hasCarousel = carouselFiles.length > 0;

    if (!hasCover && !hasCarousel) {
        wrap.style.display = 'none';
        return;
    }
    wrap.style.display = 'block';

    const items = [];
    if (hasCover) {
        items.push({ url: coverDataURL, name: coverFile?.name ?? 'Cover', isCover: true });
    }

    const urls = await Promise.all(carouselFiles.map(toDataURL));
    carouselFiles.forEach((f, i) => {
        items.push({ url: urls[i], name: f.name, isCover: false, carouselIndex: i });
    });

    grid.innerHTML = '';
    items.forEach((item, pos) => {
        const div = document.createElement('div');
        div.className = 'pj-order-item' + (item.isCover ? ' pj-order-item--cover' : '');

        const img = document.createElement('img');
        img.src = item.url;
        img.alt = item.name;
        div.appendChild(img);

        const num = document.createElement('span');
        num.className = 'pj-order-item__num';
        num.textContent = pos + 1;
        div.appendChild(num);

        if (item.isCover) {
            const lbl = document.createElement('span');
            lbl.className = 'pj-order-item__cover-label';
            lbl.textContent = '★ COVER';
            div.appendChild(lbl);
        } else {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pj-order-item__remove';
            btn.title = 'Retirer';
            btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                carouselFiles.splice(item.carouselIndex, 1);
                syncCarouselInput();
                renderOrderPreview();
            });
            div.appendChild(btn);

            const name = document.createElement('span');
            name.className = 'pj-order-item__name';
            name.textContent = item.name;
            div.appendChild(name);
        }

        grid.appendChild(div);
    });
}

function syncCarouselInput() {
    const input = document.getElementById('carouselFilesInput');
    if (!input) return;
    const dt = new DataTransfer();
    carouselFiles.forEach(f => dt.items.add(f));
    input.files = dt.files;
}

// ─────────────────────────────────────────────────────────────
// COVER — label natif for="coverFileInput"
// ─────────────────────────────────────────────────────────────
function initCover() {
    const input      = document.getElementById('coverFileInput');
    const zone       = document.getElementById('coverDropZone');
    const icon       = document.getElementById('coverDropIcon');
    const text       = document.getElementById('coverDropText');
    const preview    = document.getElementById('coverPreview');
    const previewImg = document.getElementById('coverPreviewImg');
    const removeBtn  = document.getElementById('coverPreviewRemove');

    if (!input) return;

    input.addEventListener('change', () => {
        const f = input.files?.[0];
        f ? applyCover(f) : clearCover();
    });

    if (zone) {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('is-dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
        zone.addEventListener('dragend',   () => zone.classList.remove('is-dragover'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('is-dragover');
            const f = [...(e.dataTransfer?.files ?? [])].find(f => MIME_OK.includes(f.type));
            if (!f) return;
            const dt = new DataTransfer();
            dt.items.add(f);
            input.files = dt.files;
            applyCover(f);
        });
    }

    removeBtn?.addEventListener('click', () => { input.value = ''; clearCover(); });

    async function applyCover(file) {
        try {
            const url = await toDataURL(file);
            coverFile    = file;
            coverDataURL = url;
            if (previewImg) previewImg.src = url;
            if (preview)    preview.style.display = 'block';
            if (zone)       zone.classList.add('has-file');
            if (text)       text.textContent = '✓ ' + file.name;
            if (icon)       { icon.className = 'fa-solid fa-circle-check pj-dropzone__icon'; icon.style.color = '#16a34a'; }
            renderOrderPreview();
        } catch { /* silencieux */ }
    }

    function clearCover() {
        coverFile    = null;
        coverDataURL = null;
        if (previewImg) previewImg.src = '';
        if (preview)    preview.style.display = 'none';
        if (zone)       zone.classList.remove('has-file');
        if (text)       text.textContent = 'Cliquez ou glissez votre image ici';
        if (icon)       { icon.className = 'fa-solid fa-image pj-dropzone__icon'; icon.style.color = ''; }
        renderOrderPreview();
    }
}

// ─────────────────────────────────────────────────────────────
// CAROUSEL — label natif for="carouselFilesInput"
// Le label id="carouselDropLabel" déclenche l'input nativement au clic.
// ─────────────────────────────────────────────────────────────
function initCarousel() {
    const input = document.getElementById('carouselFilesInput');
    const zone  = document.getElementById('carouselDropLabel');
    const text  = document.getElementById('carouselDropText');

    if (!input) return;

    input.addEventListener('change', () => {
        if (!input.files?.length) return;
        const selected = [...input.files].filter(f => MIME_OK.includes(f.type));
        addFiles(selected);
        input.value = ''; // reset pour permettre re-sélection
    });

    if (zone) {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('is-dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
        zone.addEventListener('dragend',   () => zone.classList.remove('is-dragover'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('is-dragover');
            const dropped = [...(e.dataTransfer?.files ?? [])].filter(f => MIME_OK.includes(f.type));
            if (!dropped.length) return;
            addFiles(dropped);
        });
    }

    function addFiles(newFiles) {
        const existingNames = new Set(carouselFiles.map(f => f.name));
        const toAdd = newFiles.filter(f => !existingNames.has(f.name));
        if (!toAdd.length) return;
        carouselFiles = [...carouselFiles, ...toAdd];
        updateZone();
        syncCarouselInput();
        renderOrderPreview();
    }

    function updateZone() {
        const n = carouselFiles.length;
        if (zone) zone.classList.toggle('has-file', n > 0);
        if (text) text.textContent = n > 0
            ? n + ' image' + (n > 1 ? 's' : '') + ' sélectionnée' + (n > 1 ? 's' : '')
            : 'Cliquez ou glissez vos images ici';
    }
}

// ─────────────────────────────────────────────────────────────
// SUPPRESSION checkboxes (page edit)
// ─────────────────────────────────────────────────────────────
function initDeleteCheckboxes() {
    document.querySelectorAll('.projet-delete-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('.projet-edit-thumb')?.classList.toggle('is-marked-delete', cb.checked);
        });
    });
}

// ─────────────────────────────────────────────────────────────
// BOOT
// ─────────────────────────────────────────────────────────────
function boot() {
    initCover();
    initCarousel();
    initDeleteCheckboxes();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}