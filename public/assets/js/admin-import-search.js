document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-admin-import-search]');
    const search = form?.querySelector('input[type="search"]');
    const statusFilter = form?.querySelector('select');
    const tableId = search?.getAttribute('aria-controls');
    const table = tableId ? document.getElementById(tableId) : null;
    const resultCount = document.getElementById('import-result-count');

    if (!(form instanceof HTMLFormElement)
        || !(search instanceof HTMLInputElement)
        || !(statusFilter instanceof HTMLSelectElement)
        || !(table instanceof HTMLTableElement)
        || !(resultCount instanceof HTMLElement)) {
        console.error('Unable to initialize import history search: a filter, table, or result count was not found.');
        return;
    }

    const rows = Array.from(table.querySelectorAll('tr[data-admin-import-row]'));
    const emptyRow = table.querySelector('.admin-import-search-empty');

    if (!(emptyRow instanceof HTMLTableRowElement)) {
        console.error('Unable to initialize import history search: empty-state row was not found.');
        return;
    }

    const filterRows = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const status = statusFilter.value;
        let visibleCount = 0;

        rows.forEach((row) => {
            const matchesFile = (row.dataset.searchFile || '').toLocaleLowerCase().includes(query);
            const matchesStatus = !status || row.dataset.searchStatus === status;
            row.hidden = !matchesFile || !matchesStatus;

            if (!row.hidden) {
                visibleCount++;
            }
        });

        emptyRow.hidden = visibleCount > 0;
        resultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'import' : 'imports'}`;
    };

    search.addEventListener('input', filterRows);
    statusFilter.addEventListener('change', filterRows);
    filterRows();
});
