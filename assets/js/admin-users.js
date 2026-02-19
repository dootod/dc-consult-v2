// ── Gestion utilisateurs — Recherche + filtre rôle ───────────
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const roleFilter  = document.getElementById('roleFilter');
    const rows        = document.querySelectorAll('#usersTable tbody tr');

    function filterTable() {
        const q    = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const role = roleFilter ? roleFilter.value : '';

        rows.forEach(row => {
            const text    = row.textContent.toLowerCase();
            const rowRole = row.dataset.role;
            const matchQ  = !q    || text.includes(q);
            const matchR  = !role || rowRole === role;

            row.style.display = (matchQ && matchR) ? '' : 'none';
        });
    }

    searchInput?.addEventListener('input', filterTable);
    roleFilter?.addEventListener('change', filterTable);
});