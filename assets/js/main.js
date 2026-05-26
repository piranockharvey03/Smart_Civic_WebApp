document.addEventListener('DOMContentLoaded', () => {
    const themeStorageKey = 'smart-civic-theme';
    const root = document.documentElement;
    const themeToggle = document.querySelector('[data-theme-toggle]');
    const sidebar = document.querySelector('#appSidebar');
    let storedTheme = null;

    try {
        storedTheme = localStorage.getItem(themeStorageKey);
    } catch (error) {
        storedTheme = null;
    }

    let themeSource = storedTheme ? 'stored' : 'default';

    const applyTheme = (theme, persist = false) => {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';

        root.setAttribute('data-bs-theme', nextTheme);
        root.style.colorScheme = nextTheme;

        if (persist) {
            try {
                localStorage.setItem(themeStorageKey, nextTheme);
            } catch (error) {
                // Ignore storage failures and keep the current session theme only.
            }
        }

        if (!themeToggle) {
            return;
        }

        const icon = themeToggle.querySelector('[data-theme-icon]');
        const label = themeToggle.querySelector('[data-theme-label]');

        themeToggle.setAttribute('aria-pressed', String(nextTheme === 'dark'));

        if (icon) {
            icon.className = nextTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars';
        }

        if (label) {
            label.textContent = nextTheme === 'dark' ? 'Light mode' : 'Dark mode';
        }
    };

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
            themeSource = 'stored';
            applyTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
        });

        applyTheme(root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light', false);
    }

    if (!sidebar || !window.bootstrap) {
        return;
    }

    // Bootstrap's offcanvas-lg handles desktop visibility automatically.
    bootstrap.Offcanvas.getOrCreateInstance(sidebar);

    // Initialize any flash toasts rendered server-side
    try {
        const toastNodes = Array.from(document.querySelectorAll('#flashToasts .toast'));

        toastNodes.forEach((el) => {
            const t = new bootstrap.Toast(el, { delay: 5000 });
            t.show();
        });
    } catch (err) {
        // silently ignore if bootstrap is not available
    }
});
