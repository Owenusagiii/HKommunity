document.addEventListener('DOMContentLoaded', () => {
    const statusFilter = document.getElementById('admin-report-status-filter');
    const reportCount = document.getElementById('admin-report-count');
    const table = document.getElementById('admin-reports-table');

    if (
        !(statusFilter instanceof HTMLSelectElement)
        || !(reportCount instanceof HTMLElement)
        || !(table instanceof HTMLTableElement)
    ) {
        console.error('Unable to initialize Admin report status filter: filter controls were not found.');
        return;
    }

    const reportRows = Array.from(table.querySelectorAll('tr[data-admin-report-row]'));
    const emptyRow = table.querySelector('.admin-reports-filter-empty');

    if (!(emptyRow instanceof HTMLTableRowElement)) {
        console.error('Unable to initialize Admin report status filter: empty-state row was not found.');
        return;
    }

    const filterReports = () => {
        let visibleCount = 0;

        reportRows.forEach((row) => {
            row.hidden = statusFilter.value !== '' && row.dataset.reportStatus !== statusFilter.value;
            if (!row.hidden) {
                visibleCount++;
            }
        });

        emptyRow.hidden = visibleCount > 0;
        reportCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'report' : 'reports'}`;
    };

    statusFilter.addEventListener('change', filterReports);
    filterReports();
});
