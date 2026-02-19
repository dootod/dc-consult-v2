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
    if (dropZone) {
        // Cherche l'input file dans la drop-zone
        let fileInput = document.getElementById('fileInput');
        if (!fileInput) {
            fileInput = dropZone.querySelector('input[type="file"]');
        }
        
        const dropText = document.getElementById('dropZoneText');
        
        // Fonction pour mettre à jour l'affichage du fichier
        const updateFileDisplay = () => {
            if (fileInput?.files?.[0]) {
                const fileName = fileInput.files[0].name;
                if (dropText) {
                    dropText.textContent = fileName;
                    dropZone.classList.add('has-file');
                }
            }
        };
        
        // Listener sur le changement de fichier
        fileInput?.addEventListener('change', updateFileDisplay);
        
        // Drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('is-dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('is-dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('is-dragover');
            
            if (!fileInput) return;
            
            const files = e.dataTransfer.files;
            if (files?.length > 0) {
                // Assigne les fichiers à l'input
                fileInput.files = files;
                updateFileDisplay();
            }
        });
    }
});

