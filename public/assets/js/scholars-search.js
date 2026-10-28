document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('student_id');
    const complianceFilter = document.getElementById('scholar-compliance-filter');
    const rows = Array.from(document.querySelectorAll('.coordinator-scholars-table tr.coordinator-popout-row[data-student-id]'));
    const noMatchesRow = document.querySelector('.scholars-search-empty');

    if (!search || !complianceFilter || rows.length === 0 || !noMatchesRow) {
        return;
    }

    const filterRows = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const filter = complianceFilter.value;
        let visibleCount = 0;

        rows.forEach((row) => {
            const matchesSearch = row.dataset.studentId.toLocaleLowerCase().includes(query);
            const dutyComplete = row.dataset.dutyComplete === 'true';
            const requirementsComplete = row.dataset.requirementsComplete === 'true';
            const isComplete = dutyComplete && requirementsComplete;
            const matchesCompliance = filter === 'default'
                || (filter === 'incomplete' && !isComplete)
                || (filter === 'complete' && isComplete);
            const matches = matchesSearch && matchesCompliance;
            row.hidden = !matches;

            if (matches) {
                visibleCount++;
            }
        });
        noMatchesRow.hidden = visibleCount > 0;
    };

    search.addEventListener('input', filterRows);
    complianceFilter.addEventListener('change', filterRows);
    filterRows();
});
