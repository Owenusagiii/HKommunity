document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-admin-registration-search]');

    if (!(form instanceof HTMLFormElement)) {
        console.error('Unable to initialize registration search: filter form was not found.');
        return;
    }

    const search = form.querySelector('input[type="search"]');
    const statusFilter = form.querySelector('select[name="status"]');
    const tableId = search?.getAttribute('aria-controls');
    const table = tableId ? document.getElementById(tableId) : null;
    const resultCount = document.getElementById('registration-result-count');

    if (!(search instanceof HTMLInputElement)
        || !(statusFilter instanceof HTMLSelectElement)
        || !(table instanceof HTMLTableElement)
        || !(resultCount instanceof HTMLElement)) {
        console.error('Unable to initialize registration search: a filter, table, or result count was not found.');
        return;
    }

    const rows = Array.from(table.querySelectorAll('tr[data-admin-registration-row]'));
    const emptyRow = table.querySelector('.admin-registration-search-empty');
    const selectAll = document.getElementById('registration-select-all');
    const selectAllColumn = table.querySelector('thead .admin-registration-select-column');
    const scholarHeading = table.querySelector('thead .admin-registration-scholar-heading');
    const scholarHeadingText = scholarHeading?.querySelector('span');
    const selectionCount = document.getElementById('registration-selection-count');
    const approveSelectedButton = document.querySelector('.admin-registration-bulk-approve');

    if (!(emptyRow instanceof HTMLTableRowElement)
        || !(selectAll instanceof HTMLInputElement)
        || !(selectAllColumn instanceof HTMLTableCellElement)
        || !(scholarHeading instanceof HTMLTableCellElement)
        || !(scholarHeadingText instanceof HTMLSpanElement)
        || !(selectionCount instanceof HTMLElement)
        || !(approveSelectedButton instanceof HTMLButtonElement)) {
        console.error('Unable to initialize registration search: selection controls or empty-state row were not found.');
        return;
    }

    const getSelectableCheckbox = (row) => row.querySelector('.admin-registration-select');
    const updateSelectionState = () => {
        const visibleSelectable = rows
            .filter((row) => !row.hidden)
            .map(getSelectableCheckbox)
            .filter((checkbox) => checkbox instanceof HTMLInputElement);
        const selectedCount = rows
            .map(getSelectableCheckbox)
            .filter((checkbox) => checkbox instanceof HTMLInputElement && checkbox.checked)
            .length;
        const visibleSelectedCount = visibleSelectable.filter((checkbox) => checkbox.checked).length;

        selectAll.disabled = visibleSelectable.length === 0;
        selectAll.checked = visibleSelectable.length > 0 && visibleSelectedCount === visibleSelectable.length;
        selectAll.indeterminate = visibleSelectedCount > 0 && visibleSelectedCount < visibleSelectable.length;
        if (selectedCount > 0) {
            selectAllColumn.append(selectAll);
            scholarHeading.classList.remove('has-select-all-control');
        } else {
            scholarHeading.insertBefore(selectAll, scholarHeadingText);
            scholarHeading.classList.add('has-select-all-control');
        }
        approveSelectedButton.disabled = selectedCount === 0;
        approveSelectedButton.hidden = selectedCount === 0;
        selectionCount.hidden = selectedCount === 0;
        table.classList.toggle('has-registration-selection', selectedCount > 0);
        selectionCount.textContent = `${selectedCount} selected`;
    };

    const filterRows = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const status = statusFilter.value;
        let visibleCount = 0;

        rows.forEach((row) => {
            const matchesId = (row.dataset.searchId || '').toLocaleLowerCase().includes(query);
            const matchesStatus = !status || row.dataset.searchStatus === status;
            row.hidden = !matchesId || !matchesStatus;

            if (row.hidden) {
                const checkbox = getSelectableCheckbox(row);
                if (checkbox instanceof HTMLInputElement) {
                    checkbox.checked = false;
                }
            }

            if (!row.hidden) {
                visibleCount++;
            }
        });

        emptyRow.hidden = visibleCount > 0;
        resultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'request' : 'requests'}`;
        updateSelectionState();
    };

    selectAll.addEventListener('change', () => {
        rows.forEach((row) => {
            if (row.hidden) {
                return;
            }

            const checkbox = getSelectableCheckbox(row);
            if (checkbox instanceof HTMLInputElement) {
                checkbox.checked = selectAll.checked;
            }
        });
        updateSelectionState();
    });

    rows.forEach((row) => {
        const checkbox = getSelectableCheckbox(row);
        if (checkbox instanceof HTMLInputElement) {
            checkbox.addEventListener('change', updateSelectionState);
        }
    });

    search.addEventListener('input', filterRows);
    statusFilter.addEventListener('change', filterRows);
    filterRows();
});
