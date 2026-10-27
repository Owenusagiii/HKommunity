document.addEventListener('DOMContentLoaded', () => {
    const statusConfirmDialog = document.getElementById('renewal-status-confirm-dialog');
    const statusConfirmDescription = statusConfirmDialog?.querySelector('#renewal-status-confirm-description');
    const statusConfirmButton = statusConfirmDialog?.querySelector('[data-renewal-status-confirm]');
    const statusCancelButton = statusConfirmDialog?.querySelector('[data-renewal-status-cancel]');
    let pendingStatusForm = null;

    if (
        statusConfirmDialog instanceof HTMLDialogElement
        && statusConfirmDescription instanceof HTMLElement
        && statusConfirmButton instanceof HTMLButtonElement
        && statusCancelButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('form[data-confirm-renewal-status]').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', (event) => {
                if (pendingStatusForm === form) {
                    pendingStatusForm = null;
                    return;
                }

                event.preventDefault();
                const statusSelect = form.querySelector('select[name="status"]');
                if (!(statusSelect instanceof HTMLSelectElement)) {
                    console.error('Unable to confirm renewal status: status selector was not found.');
                    return;
                }

                pendingStatusForm = form;
                const status = statusSelect.selectedOptions[0]?.textContent?.trim() || statusSelect.value;
                statusConfirmDescription.textContent = `Are you sure you want to update this scholar's status as "${status}"?`;
                statusConfirmDialog.showModal();
            });
        });

        statusConfirmButton.addEventListener('click', () => {
            if (!pendingStatusForm) {
                console.error('Unable to confirm renewal status: no pending form was found.');
                return;
            }

            const form = pendingStatusForm;
            statusConfirmDialog.close();
            form.requestSubmit();
        });

        statusCancelButton.addEventListener('click', () => {
            pendingStatusForm = null;
            statusConfirmDialog.close();
        });

        statusConfirmDialog.addEventListener('click', (event) => {
            if (event.target === statusConfirmDialog) {
                pendingStatusForm = null;
                statusConfirmDialog.close();
            }
        });

        statusConfirmDialog.addEventListener('cancel', () => {
            pendingStatusForm = null;
        });

        statusConfirmDialog.addEventListener('close', () => {
            statusConfirmButton.focus();
        });
    } else if (document.querySelector('form[data-confirm-renewal-status]')) {
        console.error('Unable to initialize renewal status confirmation: dialog controls were not found.');
    }

    const search = document.getElementById('student_id');
    const statusFilter = document.getElementById('renewal_status_filter');
    const rows = Array.from(document.querySelectorAll('#renewals-table tr.coordinator-popout-row[data-student-id]'));
    const noMatchesRow = document.querySelector('.renewals-search-empty');

    if (
        search instanceof HTMLInputElement
        && statusFilter instanceof HTMLSelectElement
        && noMatchesRow instanceof HTMLTableRowElement
    ) {
        const filterRows = () => {
            const query = search.value.trim().toLocaleLowerCase();
            const selectedStatus = statusFilter.value;
            let visibleCount = 0;

            rows.forEach((row) => {
                const matchesStudentId = row.dataset.studentId.toLocaleLowerCase().includes(query);
                const matchesStatus = !selectedStatus || row.dataset.renewalStatus === selectedStatus;
                const matches = matchesStudentId && matchesStatus;
                row.hidden = !matches;

                if (matches) {
                    visibleCount++;
                }
            });
            noMatchesRow.hidden = visibleCount > 0;
        };

        search.addEventListener('input', filterRows);
        statusFilter.addEventListener('change', filterRows);
        filterRows();
    } else if (rows.length > 0) {
        console.error('Unable to initialize renewal filters: search, status filter, or empty-state row was not found.');
    }

    document.querySelectorAll('.coordinator-renewal-drawer').forEach((dialog) => {
        const academicYearFilter = dialog.querySelector('[data-renewal-academic-year-filter]');
        const recordRows = Array.from(dialog.querySelectorAll('[data-renewal-record-row]'));
        const countLabel = dialog.querySelector('[data-renewal-record-count]');

        if (!(academicYearFilter instanceof HTMLSelectElement)) {
            console.error('Unable to initialize renewal academic-year filter: filter was not found.');
            return;
        }

        const filterRecords = () => {
            const academicYear = academicYearFilter.value;
            let visibleRecordCount = 0;

            recordRows.forEach((recordRow) => {
                recordRow.hidden = academicYear !== 'all' && recordRow.dataset.academicYear !== academicYear;
                if (!recordRow.hidden) {
                    visibleRecordCount++;
                }
            });

            if (countLabel) {
                countLabel.textContent = `${visibleRecordCount} record${visibleRecordCount === 1 ? '' : 's'}`;
            }
        };

        academicYearFilter.addEventListener('change', filterRecords);
        filterRecords();
    });

    const promotionSearch = document.querySelector('[data-promotion-search]');
    const promotionEligibilityFilter = document.querySelector('[data-promotion-eligibility-filter]');
    const promotionRows = Array.from(document.querySelectorAll('#promotion-table tbody tr[data-promotion-row]'));
    const promotionEmptyRow = document.querySelector('.promotion-search-empty');
    const promotionCheckboxes = Array.from(document.querySelectorAll('[data-promotion-checkbox]'));
    const promotionSelectAll = document.querySelector('[data-promotion-select-all]');
    const promotionSubmit = document.querySelector('[data-promotion-submit]');
    const promotionForm = document.getElementById('promotion-bulk-form');
    const promotionConfirmDialog = document.getElementById('promotion-confirm-dialog');
    const promotionConfirmDescription = promotionConfirmDialog?.querySelector('#promotion-confirm-description');
    const promotionConfirmButton = promotionConfirmDialog?.querySelector('[data-promotion-confirm]');
    const promotionCancelButton = promotionConfirmDialog?.querySelector('[data-promotion-cancel]');
    const promotionTable = promotionSelectAll?.closest('#promotion-table');
    const promotionSelectionHeading = promotionTable?.querySelector('.coordinator-promotion-actions-heading');
    const promotionScholarHeading = promotionTable?.querySelector('.coordinator-promotion-scholar-heading');
    let pendingPromotionSubmitter = null;
    let confirmedPromotionSubmitter = null;

    const updatePromotionSelection = () => {
        const selectedCount = promotionCheckboxes.filter((checkbox) => checkbox.checked).length;
        const visibleCheckboxes = promotionCheckboxes.filter((checkbox) => checkbox.closest('tr')?.hidden === false);
        const visibleSelectedCount = visibleCheckboxes.filter((checkbox) => checkbox.checked).length;
        const allVisibleSelected = visibleCheckboxes.length > 0 && visibleSelectedCount === visibleCheckboxes.length;

        if (promotionSelectAll instanceof HTMLInputElement) {
            promotionSelectAll.checked = allVisibleSelected;
            promotionSelectAll.indeterminate = visibleSelectedCount > 0 && !allVisibleSelected;
            promotionSelectAll.disabled = visibleCheckboxes.length === 0;
        }

        if (promotionSubmit instanceof HTMLButtonElement) {
            promotionSubmit.disabled = selectedCount === 0;
        }
    };

    if (promotionSearch instanceof HTMLInputElement && promotionEmptyRow instanceof HTMLTableRowElement) {
        const filterPromotionRows = () => {
            const query = promotionSearch.value.trim().toLocaleLowerCase();
            const eligibility = promotionEligibilityFilter instanceof HTMLSelectElement
                ? promotionEligibilityFilter.value
                : 'all';
            let visibleCount = 0;

            promotionRows.forEach((row) => {
                const matchesStudentId = (row.dataset.studentId || '').toLocaleLowerCase().includes(query);
                const matchesEligibility = eligibility === 'all' || row.dataset.eligibility === eligibility;
                row.hidden = !matchesStudentId || !matchesEligibility;

                if (!row.hidden) {
                    visibleCount++;
                }
            });

            promotionEmptyRow.hidden = visibleCount > 0;
            updatePromotionSelection();
        };

        promotionSearch.addEventListener('input', filterPromotionRows);
        promotionEligibilityFilter?.addEventListener('change', filterPromotionRows);
        filterPromotionRows();
    }

    if (promotionSelectAll instanceof HTMLInputElement) {
        const setSelectionMode = (active) => {
            if (
                !(promotionTable instanceof HTMLTableElement)
                || !(promotionSelectionHeading instanceof HTMLTableCellElement)
                || !(promotionScholarHeading instanceof HTMLTableCellElement)
            ) {
                console.error('Unable to update promotion selection layout: table headings are missing.');
                return;
            }

            promotionTable.classList.toggle('has-promotion-selection', active);
            (active ? promotionSelectionHeading : promotionScholarHeading).prepend(promotionSelectAll);
        };

        setSelectionMode(false);
        promotionSelectAll.addEventListener('change', () => {
            setSelectionMode(promotionSelectAll.checked);

            if (promotionSelectAll.checked) {
                const visibleCheckboxes = promotionCheckboxes.filter((checkbox) => checkbox.closest('tr')?.hidden === false);
                visibleCheckboxes.forEach((checkbox) => {
                    checkbox.checked = true;
                });
            } else {
                promotionCheckboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });
            }

            updatePromotionSelection();
        });
        promotionCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updatePromotionSelection));
        updatePromotionSelection();
    }

    if (
        promotionForm instanceof HTMLFormElement
        && promotionConfirmDialog instanceof HTMLDialogElement
        && promotionConfirmDescription instanceof HTMLElement
        && promotionConfirmButton instanceof HTMLButtonElement
        && promotionCancelButton instanceof HTMLButtonElement
        && promotionSubmit instanceof HTMLButtonElement
    ) {
        promotionForm.addEventListener('submit', (event) => {
            const submitter = event.submitter;
            if (!(submitter instanceof HTMLButtonElement) || submitter !== promotionSubmit) {
                return;
            }

            if (confirmedPromotionSubmitter === submitter) {
                confirmedPromotionSubmitter = null;
                return;
            }

            const selectedCount = promotionCheckboxes.filter((checkbox) => checkbox.checked).length;
            if (selectedCount === 0) {
                event.preventDefault();
                return;
            }

            event.preventDefault();
            pendingPromotionSubmitter = submitter;
            promotionConfirmDescription.textContent = `${selectedCount} eligible scholar${selectedCount === 1 ? '' : 's'} will be started in the active renewal cycle and notified.`;
            promotionConfirmButton.textContent = `Promote ${selectedCount} scholar${selectedCount === 1 ? '' : 's'}`;
            promotionConfirmDialog.showModal();
            promotionCancelButton.focus();
        });

        promotionConfirmButton.addEventListener('click', () => {
            if (!(pendingPromotionSubmitter instanceof HTMLButtonElement)) {
                console.error('Unable to confirm scholar promotion: no pending form submission was found.');
                return;
            }

            const submitter = pendingPromotionSubmitter;
            pendingPromotionSubmitter = null;
            confirmedPromotionSubmitter = submitter;
            promotionConfirmDialog.close();
            promotionForm.requestSubmit(submitter);
        });

        const cancelPromotion = () => {
            pendingPromotionSubmitter = null;
            promotionConfirmDialog.close();
        };

        promotionCancelButton.addEventListener('click', cancelPromotion);
        promotionConfirmDialog.addEventListener('click', (event) => {
            if (event.target === promotionConfirmDialog) {
                cancelPromotion();
            }
        });
        promotionConfirmDialog.addEventListener('cancel', () => {
            pendingPromotionSubmitter = null;
        });
        promotionConfirmDialog.addEventListener('close', () => {
            promotionSubmit.focus();
        });
    } else if (promotionForm instanceof HTMLFormElement) {
        console.error('Unable to initialize scholar promotion confirmation: dialog controls were not found.');
    }
});
