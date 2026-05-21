document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('#appSidebar');

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
