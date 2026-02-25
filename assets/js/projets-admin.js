/**
 * DC Consult — Gestion Projets Admin
 * Gère :
 *  - Drop zone multi-images avec prévisualisation
 *  - Sélection de la cover dans l'édition
 *  - Marquage visuel des images à supprimer
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Drop zone multi-images ──────────────────────────────────────────────

    const dropZone    = document.getElementById('projetDropZone');
    const fileInput   = document.getElementById('projetImagesInput');
    const dropText    = document.getElementById('projetDropZoneText');
    const previewGrid = document.getElementById('projetPreviewGrid');

    if (dropZone && fileInput) {

        // Clic sur la zone → ouvre le sélecteur de fichiers
        dropZone.addEventListener('click', () => fileInput.click());

        // Drag & drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('is-dragover');
        });

        ['dragleave', 'dragend'].forEach(evt => {
            dropZone.addEventListener(evt, () => dropZone.classList.remove('is-dragover'));
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('is-dragover');

            const dt = e.dataTransfer;
            if (dt?.files?.length) {
                // Transférer les fichiers dans l'input
                const dataTransfer = new DataTransfer();
                Array.from(dt.files).forEach(f => {
                    if (['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) {
                        dataTransfer.items.add(f);
                    }
                });
                fileInput.files = dataTransfer.files;
                updatePreview(fileInput.files);
            }
        });

        // Changement via l'input standard
        fileInput.addEventListener('change', () => {
            updatePreview(fileInput.files);
        });
    }

    /**
     * Génère les thumbnails de prévisualisation en mémoire.
     * La première image reçoit le badge "Cover".
     */
    function updatePreview(files) {
        if (!previewGrid) return;

        previewGrid.innerHTML = '';

        if (files.length === 0) {
            if (dropText) dropText.textContent = 'Glissez vos images ici ou cliquez';
            dropZone?.classList.remove('has-file');
            return;
        }

        const count = files.length;
        if (dropText) {
            dropText.textContent = count + ' image' + (count > 1 ? 's' : '') + ' sélectionnée' + (count > 1 ? 's' : '');
        }
        dropZone?.classList.add('has-file');

        Array.from(files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const item = document.createElement('div');
                item.className = 'projet-preview-item';

                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = file.name;
                item.appendChild(img);

                if (index === 0) {
                    const badge = document.createElement('span');
                    badge.className = 'projet-preview-item__cover-badge';
                    badge.textContent = '★ Cover';
                    item.appendChild(badge);
                }

                previewGrid.appendChild(item);
            };
            reader.readAsDataURL(file);
        });
    }

    // ── Sélection de la cover (page edit) ──────────────────────────────────

    const coverInput = document.getElementById('coverImageIdInput');

    document.querySelectorAll('.projet-edit-thumb__cover-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const imageId = btn.dataset.imageId;

            // Mettre à jour l'input caché
            if (coverInput) coverInput.value = imageId;

            // Mettre à jour l'UI : toutes les étoiles repassent en "vide"
            document.querySelectorAll('.projet-edit-thumb__cover-btn').forEach(b => {
                b.classList.remove('is-cover');
                b.querySelector('i').className = 'fa-regular fa-star';
                b.closest('.projet-edit-thumb')?.classList.remove('is-cover-selected');
            });

            // L'étoile cliquée devient "pleine"
            btn.classList.add('is-cover');
            btn.querySelector('i').className = 'fa-solid fa-star';
            btn.closest('.projet-edit-thumb')?.classList.add('is-cover-selected');
        });
    });

    // Marquer la cover actuelle visuellement au chargement
    if (coverInput && coverInput.value) {
        const currentCoverBtn = document.querySelector(
            `.projet-edit-thumb__cover-btn[data-image-id="${coverInput.value}"]`
        );
        if (currentCoverBtn) {
            currentCoverBtn.closest('.projet-edit-thumb')?.classList.add('is-cover-selected');
        }
    }

    // ── Marquage visuel des images à supprimer ──────────────────────────────

    document.querySelectorAll('.projet-delete-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            const thumb = checkbox.closest('.projet-edit-thumb');
            if (thumb) {
                thumb.classList.toggle('is-marked-delete', checkbox.checked);
            }
        });
    });

});