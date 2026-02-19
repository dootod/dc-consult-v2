// ── Gestion documents — Recherche + drop zone ────────────────
document.addEventListener('DOMContentLoaded', () => {

    // ── Recherche dans le tableau ──
    const searchInput = document.getElementById('searchInput');
    const rows        = document.querySelectorAll('#docsTable tbody tr');

    searchInput?.addEventListener('input', () => {
        const q = searchInput.value.toLowerCase().trim();
        rows.forEach(row => {
            row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
        });
    });

    // ── Drop zone fichier ──
    const dropZone  = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput') || dropZone?.querySelector('input[type="file"]');
    const dropText  = document.getElementById('dropZoneText');

    const updateFileInput = () => {
        const name = fileInput?.files?.[0]?.name;
        if (name && dropText) {
            dropText.textContent = name;
            dropZone?.classList.add('has-file');
        }
    };

    fileInput?.addEventListener('change', updateFileInput);

    dropZone?.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('is-dragover');
    });

    dropZone?.addEventListener('dragleave', () => {
        dropZone.classList.remove('is-dragover');
    });

    dropZone?.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('is-dragover');
        const file = e.dataTransfer.files[0];
        if (file && fileInput) {
            fileInput.files = e.dataTransfer.files;
            if (dropText) {
                dropText.textContent = file.name;
                dropZone.classList.add('has-file');
            }
        }
    });
});
