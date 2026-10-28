document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('duty-record-search');
    const hoursSort = document.getElementById('duty-record-hours-sort');
    const rows = Array.from(document.querySelectorAll('.coordinator-duty-table-scroll tr.coordinator-popout-row[data-student-id]'));
    const noMatchesRow = document.querySelector('.duty-search-empty');

    if (!search || !hoursSort || rows.length === 0 || !noMatchesRow) {
        return;
    }

    const originalOrder = new Map(rows.map((row, index) => [row, index]));
    const tableBody = rows[0].parentElement;

    const sortRows = () => {
        const sortOrder = hoursSort.value;
        const sortedRows = [...rows].sort((first, second) => {
            if (sortOrder === 'default') {
                return first.dataset.lastName.localeCompare(second.dataset.lastName, undefined, { sensitivity: 'base' })
                    || originalOrder.get(first) - originalOrder.get(second);
            }

            const hourDifference = Number(first.dataset.renderedHours) - Number(second.dataset.renderedHours);
            return (sortOrder === 'highest' ? -hourDifference : hourDifference)
                || originalOrder.get(first) - originalOrder.get(second);
        });

        sortedRows.forEach((row) => tableBody.insertBefore(row, noMatchesRow));
    };

    const filterRows = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        rows.forEach((row) => {
            const matches = row.dataset.studentId.toLocaleLowerCase().includes(query);
            row.hidden = !matches;

            if (matches) {
                visibleCount++;
            }
        });
        noMatchesRow.hidden = visibleCount > 0;
    };

    hoursSort.addEventListener('change', sortRows);
    search.addEventListener('input', filterRows);
    filterRows();
});
