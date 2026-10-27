document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-panel-url]').forEach((panel) => {
        const navigate = () => {
            window.location.href = panel.dataset.panelUrl;
        };

        panel.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, select, textarea')) {
                return;
            }
            navigate();
        });

        panel.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                navigate();
            }
        });
    });
});
