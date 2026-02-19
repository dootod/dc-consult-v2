// ── Gestion documents — Recherche + drop zone ────────────────
const initAdminDocuments = () => {

    // ── Recherche dans le tableau ──
    const searchInput = document.getElementById('searchInput');
    const rows        = document.querySelectorAll('#docsTable tbody tr');

    searchInput?.addEventListener('input', () => {
        const q = searchInput.value.toLowerCase().trim();
        rows.forEach(row => {
            row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
        });
    });

    // ── Drop zone fichier ── (utilise event delegation pour être robuste)
    const dropZone  = document.getElementById('dropZone');
    if (dropZone) {
        const dropText = document.getElementById('dropZoneText');
        
        // Fonction pour mettre à jour l'affichage du fichier
        const updateFileDisplay = () => {
            const fileInput = dropZone.querySelector('input[type="file"]');
            if (fileInput?.files?.length > 0) {
                const fileName = fileInput.files[0].name;
                if (dropText) {
                    dropText.textContent = fileName;
                    dropZone.classList.add('has-file');
                }
            }
        };
        
        // Event delegation: Écoute les changements sur tout input file dans dropZone
        dropZone.addEventListener('change', (e) => {
            const target = e.target instanceof Element
                ? e.target.closest('input[type="file"]')
                : null;
            if (target) {
                updateFileDisplay();
            }
        }, true); // Capture phase pour être sûr de capter l'événement
        
        // Click sur drop-zone pour ouvrir le sélecteur de fichier
        dropZone.addEventListener('click', (e) => {
            const fileInput = dropZone.querySelector('input[type="file"]');
            if (e.target !== fileInput && !e.target.closest('input')) {
                fileInput?.click();
            }
        });
        
        // Drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('is-dragover');
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('is-dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('is-dragover');
            
            const fileInput = dropZone.querySelector('input[type="file"]');
            if (!fileInput) return;
            
            const files = e.dataTransfer.files;
            if (files?.length > 0) {
                fileInput.files = files;
                updateFileDisplay();
            }
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminDocuments);
} else {
    initAdminDocuments();
}
