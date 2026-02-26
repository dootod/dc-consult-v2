/**
 * DC Consult — Gestion Projets Admin
 *
 * Architecture :
 *  - Cover   : <label for="coverFileInput"> déclenche l'input nativement (100% fiable)
 *  - Carousel: <div id="carouselDropZone"> appelle input.click() au clic
 *  - Preview : FileReader async → Promise.all → ordre garanti
 */

'use strict';

const MIME_OK = ['image/jpeg', 'image/png', 'image/webp'];

/** Lit un fichier et renvoie sa data-URL */
function toDataURL(file) {
    return new Promise((res, rej) => {
        const r = new FileReader();
        r.onload  = e => res(e.target.result);
        r.onerror = ()  => rej(new Error('Lecture impossible'));
        r.readAsDataURL(file);
    });
}

// ────────────────────────────────────────────────────────────────
// COVER — label natif → input → change → preview
// ────────────────────────────────────────────────────────────────
function initCover() {
    const input    = document.getElementById('coverFileInput');
    const zone     = document.getElementById('coverDropZone');
    const icon     = document.getElementById('coverDropIcon');
    const text     = document.getElementById('coverDropText');
    const preview  = document.getElementById('coverPreview');
    const img      = document.getElementById('coverPreviewImg');
    const removeBtn = document.getElementById('coverPreviewRemove');

    if (!input) return;

    // Le <label for="coverFileInput"> ouvre nativement le sélecteur.
    // On écoute uniquement le 'change' sur l'input.
    input.addEventListener('change', () => {
        const f = input.files?.[0];
        f ? showPreview(f) : hide();
    });

    // Drag & drop sur la zone (qui est un <label>)
    if (zone) {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('is-dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
        zone.addEventListener('dragend',   () => zone.classList.remove('is-dragover'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('is-dragover');
            const f = [...(e.dataTransfer?.files || [])].find(f => MIME_OK.includes(f.type));
            if (!f) return;
            const dt = new DataTransfer();
            dt.items.add(f);
            input.files = dt.files;
            showPreview(f);
        });
    }

    removeBtn?.addEventListener('click', () => { input.value = ''; hide(); });

    async function showPreview(file) {
        try {
            const url = await toDataURL(file);
            if (img)     img.src           = url;
            if (preview) preview.style.display = 'block';
            if (zone)    zone.classList.add('has-file');
            if (text)    text.textContent   = '✓ ' + file.name;
            if (icon)    { icon.className = 'fa-solid fa-circle-check pj-dropzone__icon'; icon.style.color = '#16a34a'; }
        } catch { /* silencieux */ }
    }

    function hide() {
        if (img)     img.src           = '';
        if (preview) preview.style.display = 'none';
        if (zone)    zone.classList.remove('has-file');
        if (text)    text.textContent   = 'Cliquez ou glissez votre image ici';
        if (icon)    { icon.className = 'fa-solid fa-image pj-dropzone__icon'; icon.style.color = ''; }
    }
}

// ────────────────────────────────────────────────────────────────
// CAROUSEL — div cliquable → input.click() → change → grille
// ────────────────────────────────────────────────────────────────
function initCarousel() {
    const zone  = document.getElementById('carouselDropZone');
    const input = document.getElementById('carouselFilesInput');
    const text  = document.getElementById('carouselDropText');
    const grid  = document.getElementById('carouselPreviewGrid');

    if (!input || !zone) return;

    // Clic sur la div → ouvre le sélecteur
    zone.addEventListener('click', () => input.click());

    // Drag & drop
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('is-dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
    zone.addEventListener('dragend',   () => zone.classList.remove('is-dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('is-dragover');
        const nouveaux = [...(e.dataTransfer?.files || [])].filter(f => MIME_OK.includes(f.type));
        if (!nouveaux.length) return;
        const dt = new DataTransfer();
        [...(input.files || []), ...nouveaux].forEach(f => dt.items.add(f));
        input.files = dt.files;
        renderGrid(input.files);
    });

    // Sélection via le sélecteur natif
    input.addEventListener('change', () => renderGrid(input.files));

    async function renderGrid(files) {
        if (!grid) return;

        if (!files?.length) {
            grid.innerHTML = '';
            if (text) text.textContent = 'Cliquez ou glissez vos images ici';
            zone.classList.remove('has-file');
            return;
        }

        const n = files.length;
        if (text) text.textContent = n + ' image' + (n > 1 ? 's' : '') + ' sélectionnée' + (n > 1 ? 's' : '');
        zone.classList.add('has-file');

        // Lire toutes les images en parallèle → order garanti
        const results = await Promise.all(
            [...files].map((f, i) => toDataURL(f).then(url => ({ url, i, name: f.name })))
        );
        results.sort((a, b) => a.i - b.i);

        grid.innerHTML = '';
        results.forEach(({ url, name }) => {
            const item = document.createElement('div');
            item.className = 'pj-preview-item';
            const img = document.createElement('img');
            img.src = url; img.alt = name;
            item.appendChild(img);
            grid.appendChild(item);
        });
    }
}

// ────────────────────────────────────────────────────────────────
// SUPPRESSION (page edit)
// ────────────────────────────────────────────────────────────────
function initDeleteCheckboxes() {
    document.querySelectorAll('.projet-delete-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('.projet-edit-thumb')?.classList.toggle('is-marked-delete', cb.checked);
        });
    });
}

// ────────────────────────────────────────────────────────────────
// BOOT (fonctionne que le script soit defer, module, ou inline)
// ────────────────────────────────────────────────────────────────
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