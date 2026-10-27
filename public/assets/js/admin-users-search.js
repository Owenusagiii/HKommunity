document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-admin-users-search]').forEach((form) => {
        const search = form.querySelector('input[type="search"]');
        const yearFilter = form.querySelector('select[name$="_year_level"]');
        const tableId = search?.getAttribute('aria-controls');
        const table = tableId ? document.getElementById(tableId) : null;

        if (!(search instanceof HTMLInputElement) || !(table instanceof HTMLTableElement)) {
            console.error('Unable to initialize Admin user search: search input or table was not found.');
            return;
        }

        const rows = Array.from(table.querySelectorAll('tr[data-admin-search-row]'));
        const emptyRow = table.querySelector('.admin-users-search-empty');
        const searchKey = search.name === 'student_id' ? 'searchId' : 'searchName';

        if (!(emptyRow instanceof HTMLTableRowElement)) {
            console.error('Unable to initialize Admin user search: empty-state row was not found.');
            return;
        }

        const filterRows = () => {
            const query = search.value.trim().toLocaleLowerCase();
            const year = yearFilter instanceof HTMLSelectElement ? yearFilter.value : '';
            let visibleCount = 0;

            rows.forEach((row) => {
                const searchValue = (row.dataset[searchKey] || '').toLocaleLowerCase();
                const matchesSearch = searchValue.includes(query);
                const matchesYear = !year || row.dataset.searchYear === year;
                row.hidden = !matchesSearch || !matchesYear;

                if (!row.hidden) {
                    visibleCount++;
                }
            });

            emptyRow.hidden = visibleCount > 0;
        };

        search.addEventListener('input', filterRows);
        yearFilter?.addEventListener('change', filterRows);
        filterRows();
    });
});
