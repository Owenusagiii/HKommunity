document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('student_id');
    const rows = Array.from(document.querySelectorAll('.coordinator-scholars-table tr.coordinator-popout-row[data-student-id]'));
    const noMatchesRow = document.querySelector('.scholars-search-empty');

    if (!search || rows.length === 0 || !noMatchesRow) {
        return;
    }

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
        noMatchesRow.hidden = visibleCount > 0;
    };

    search.addEventListener('input', filterRows);
    filterRows();
});
