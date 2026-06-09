const STORAGE_KEY = 'osale-theme';
const DARK = 'dark';
const LIGHT = 'light';

function getTheme() {
    return localStorage.getItem(STORAGE_KEY) || DARK;
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    const btn = document.getElementById('btn-theme-toggle');
    if (!btn) return;
    btn.innerHTML = theme === DARK
        ? '<i class="bi bi-sun"></i>'
        : '<i class="bi bi-moon"></i>';
}

function toggleTheme() {
    const current = getTheme();
    const next = current === DARK ? LIGHT : DARK;
    localStorage.setItem(STORAGE_KEY, next);
    applyTheme(next);
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(getTheme());
    document.getElementById('btn-theme-toggle')?.addEventListener('click', toggleTheme);
});