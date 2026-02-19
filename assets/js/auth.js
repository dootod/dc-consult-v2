// ── Auth — Toggle afficher / masquer mot de passe ────────────
const initAuth = () => {
    document.querySelectorAll('.auth-toggle-pw').forEach(btn => {
        const wrap = btn.closest('.auth-input-wrap');
        if (!wrap) return;

        const input = wrap.querySelector('input[type="password"], input[type="text"]');
        const icon  = btn.querySelector('i');
        if (!input || !icon) return;

        btn.addEventListener('click', () => {
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.className = isHidden ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            btn.setAttribute('aria-label', isHidden ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuth);
} else {
    initAuth();
}