/**
 * Lógica del lado del cliente: tema claro/oscuro, confirmaciones y validaciones
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Gestión de Tema Claro / Oscuro
    const themeToggleBtn = document.getElementById('themeToggle');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const savedTheme = localStorage.getItem('app-theme') || (prefersDark ? 'dark' : 'light');

    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('app-theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }

    function updateThemeIcon(theme) {
        if (!themeToggleBtn) return;
        themeToggleBtn.textContent = theme === 'dark' ? '☀️' : '🌙';
        themeToggleBtn.setAttribute('title', theme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    }

    // 2. Confirmación de acciones críticas (data-confirm)
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', (e) => {
            const msg = element.getAttribute('data-confirm') || '¿Estás seguro de realizar esta acción?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // 3. Ocultar alertas automáticamente después de 6 segundos
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        });
    }, 6000);
});
