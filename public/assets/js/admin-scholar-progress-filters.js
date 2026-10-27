document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('.admin-scholar-progress-table');
    const yearFilter = document.querySelector('[data-scholar-progress-year-filter]');
    const statusFilter = document.querySelector('[data-scholar-progress-status-filter]');
    const idFilter = document.querySelector('[data-scholar-progress-id-filter]');

    if (
        !(table instanceof HTMLTableElement)
        || !(yearFilter instanceof HTMLSelectElement)
        || !(statusFilter instanceof HTMLSelectElement)
        || !(idFilter instanceof HTMLInputElement)
    ) {
        console.error('Unable to initialize Scholar Progress filters: table or filter controls were not found.');
        return;
    }

    const rows = Array.from(table.querySelectorAll('tr[data-scholar-progress-row]'));
    const emptyRow = table.querySelector('tr[data-scholar-progress-empty]');
    const countElement = document.querySelector('[data-scholar-progress-count]');

    if (
        !(emptyRow instanceof HTMLTableRowElement)
        || !(countElement instanceof HTMLElement)
    ) {
        console.error('Unable to initialize Scholar Progress filters: empty-state or count elements were not found.');
        return;
    }

    const filterRows = () => {
        const selectedYear = yearFilter.value;
        const selectedStatus = statusFilter.value;
        const searchId = idFilter.value.trim().toLocaleLowerCase();
        let total = 0;

        rows.forEach((row) => {
            const matchesYear = !selectedYear || row.dataset.yearLevel === selectedYear;
            const matchesStatus = !selectedStatus || row.dataset.progressStatus === selectedStatus;
            const matchesId = !searchId || (row.dataset.studentId ?? '').toLocaleLowerCase().includes(searchId);
            row.hidden = !matchesYear || !matchesStatus || !matchesId;

            if (!row.hidden) {
                total++;
            }
        });

        emptyRow.hidden = total > 0;
        countElement.textContent = String(total);
    };

    yearFilter.addEventListener('change', filterRows);
    statusFilter.addEventListener('change', filterRows);
    idFilter.addEventListener('input', filterRows);
    filterRows();
});
