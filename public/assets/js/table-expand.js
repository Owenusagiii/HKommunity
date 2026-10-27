document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.data-row, .coordinator-row, .registration-row').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('button, input, select, textarea, a, form')) {
                return;
            }

            const details = row.nextElementSibling;
            if (!details) {
                return;
            }

            const shouldExpand = !details.classList.contains('expanded');
            const tbody = row.closest('tbody');
            tbody?.querySelectorAll('.details-row.expanded, .coordinator-details-row.expanded, .registration-details-row.expanded')
                .forEach((item) => item.classList.remove('expanded'));

            tbody?.querySelectorAll('.data-row[aria-expanded="true"], .coordinator-row[aria-expanded="true"], .registration-row[aria-expanded="true"]')
                .forEach((item) => item.setAttribute('aria-expanded', 'false'));

            if (shouldExpand) {
                details.classList.add('expanded');
                row.setAttribute('aria-expanded', 'true');
            }
        });

        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                row.click();
            }
        });
    });
});
