document.addEventListener('DOMContentLoaded', () => {
    const statusFilter = document.getElementById('coordinator-report-status-filter');
    const reportCount = document.getElementById('coordinator-report-count');
    const filterEmptyRow = document.querySelector('.coordinator-report-filter-empty');
    const reportRows = Array.from(document.querySelectorAll('tr[data-report-row]'));

    if (
        statusFilter instanceof HTMLSelectElement
        && reportCount instanceof HTMLElement
        && filterEmptyRow instanceof HTMLTableRowElement
    ) {
        const filterReports = () => {
            const selectedStatus = statusFilter.value;
            let visibleCount = 0;

            reportRows.forEach((row) => {
                row.hidden = selectedStatus !== '' && row.dataset.status !== selectedStatus;
                if (!row.hidden) {
                    visibleCount++;
                }
            });

            filterEmptyRow.hidden = visibleCount > 0;
            reportCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'report' : 'reports'}`;
        };

        statusFilter.addEventListener('change', filterReports);
    } else if (reportRows.length > 0) {
        console.error('Unable to initialize report status filter: filter controls were not found.');
    }

    const confirmDialog = document.getElementById('coordinator-report-confirm-dialog');
    const confirmTitle = confirmDialog?.querySelector('#coordinator-report-confirm-title');
    const confirmDescription = confirmDialog?.querySelector('#coordinator-report-confirm-description');
    const confirmIcon = confirmDialog?.querySelector('.logout-confirm-icon i');
    const confirmButton = confirmDialog?.querySelector('[data-report-confirm-submit]');
    const cancelButton = confirmDialog?.querySelector('[data-report-confirm-cancel]');
    let pendingForm = null;
    let confirmedForm = null;
    let returnFocusTo = null;

    if (
        confirmDialog instanceof HTMLDialogElement
        && confirmTitle instanceof HTMLElement
        && confirmDescription instanceof HTMLElement
        && confirmIcon instanceof HTMLElement
        && confirmButton instanceof HTMLButtonElement
        && cancelButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('form[data-confirm-report-action]').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', (event) => {
                if (confirmedForm === form) {
                    confirmedForm = null;
                    return;
                }

                event.preventDefault();
                const action = form.dataset.confirmReportAction;
                const isEscalation = action === 'escalate';
                if (action !== 'update' && !isEscalation) {
                    console.error('Unable to confirm report action: action type was not recognized.');
                    return;
                }

                pendingForm = form;
                returnFocusTo = event.submitter instanceof HTMLElement ? event.submitter : null;
                confirmTitle.textContent = isEscalation ? 'Escalate report?' : 'Save report update?';
                confirmDescription.textContent = isEscalation
                    ? 'Are you sure you want to escalate this report to an administrator?'
                    : 'Save this response and update the report status?';
                confirmButton.textContent = isEscalation ? 'Escalate report' : 'Save update';
                confirmIcon.className = isEscalation
                    ? 'fa-solid fa-triangle-exclamation'
                    : 'fa-solid fa-circle-question';
                confirmDialog.classList.toggle('coordinator-report-confirm-escalation', isEscalation);
                confirmDialog.showModal();
            });
        });

        confirmButton.addEventListener('click', () => {
            if (!pendingForm) {
                console.error('Unable to confirm report action: no pending form was found.');
                return;
            }

            const form = pendingForm;
            pendingForm = null;
            confirmedForm = form;
            confirmDialog.close();
            form.requestSubmit();
        });

        cancelButton.addEventListener('click', () => {
            pendingForm = null;
            confirmDialog.close();
        });

        confirmDialog.addEventListener('click', (event) => {
            if (event.target === confirmDialog) {
                pendingForm = null;
                confirmDialog.close();
            }
        });

        confirmDialog.addEventListener('cancel', () => {
            pendingForm = null;
        });

        confirmDialog.addEventListener('close', () => {
            returnFocusTo?.focus();
            returnFocusTo = null;
        });
    } else if (document.querySelector('form[data-confirm-report-action]')) {
        console.error('Unable to initialize report confirmation: dialog controls were not found.');
    }

    document.querySelectorAll('.coordinator-report-row').forEach((row) => {
        const openReportDialog = () => {
            const dialogId = row.getAttribute('data-report-dialog');
            const dialog = dialogId ? document.getElementById(dialogId) : null;

            if (!(dialog instanceof HTMLDialogElement)) {
                console.error('Unable to open report details: dialog was not found.');
                return;
            }

            dialog.showModal();
        };

        row.addEventListener('click', openReportDialog);
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openReportDialog();
            }
        });
    });

    document.querySelectorAll('.coordinator-report-dialog').forEach((dialog) => {
        dialog.querySelector('.coordinator-report-dialog-close')?.addEventListener('click', () => {
            dialog.close();
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });
});
