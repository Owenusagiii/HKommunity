document.addEventListener('DOMContentLoaded', () => {
    const updateScholarDialogScrollLock = () => {
        const isOpen = Boolean(document.querySelector('.coordinator-record-dialog[open]'));
        document.documentElement.classList.toggle('scholar-report-dialog-open', isOpen);
        document.body.classList.toggle('scholar-report-dialog-open', isOpen);
    };

    document.querySelectorAll('.coordinator-popout-row').forEach((row) => {
        const openDialog = () => {
            const dialogId = row.getAttribute('data-popout-dialog');
            const dialog = dialogId ? document.getElementById(dialogId) : null;

            if (!(dialog instanceof HTMLDialogElement)) {
                console.error('Unable to open coordinator record: dialog was not found.');
                return;
            }

            if (dialog.open) {
                return;
            }

            dialog.returnFocusTo = row;
            dialog.showModal();
            if (dialog.classList.contains('coordinator-duty-review-drawer')) {
                requestAnimationFrame(() => dialog.classList.add('is-open'));
            }
            updateScholarDialogScrollLock();
        };

        row.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('button, input, select, textarea, a, form, label')) {
                return;
            }

            openDialog();
        });

        row.addEventListener('keydown', (event) => {
            if (event.target instanceof Element && event.target !== row && event.target.closest('button, input, select, textarea, a, form, label')) {
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDialog();
            }
        });
    });

    document.querySelectorAll('[data-duty-files-expand]').forEach((row) => {
        const closeOtherExpandedRows = () => {
            const tbody = row.closest('tbody');
            tbody?.querySelectorAll('tr[aria-expanded="true"][aria-controls]').forEach((expandedRow) => {
                if (expandedRow === row) {
                    return;
                }

                const otherDetailsId = expandedRow.getAttribute('aria-controls');
                const otherDetails = otherDetailsId ? document.getElementById(otherDetailsId) : null;
                expandedRow.setAttribute('aria-expanded', 'false');
                if (otherDetails instanceof HTMLTableRowElement) {
                    otherDetails.hidden = true;
                }
            });
        };

        const toggleDetails = () => {
            const detailsId = row.getAttribute('aria-controls');
            const details = detailsId ? document.getElementById(detailsId) : null;

            if (!(details instanceof HTMLTableRowElement)) {
                console.error('Unable to expand duty record files: details row was not found.');
                return;
            }

            const isExpanded = row.getAttribute('aria-expanded') === 'true';
            if (!isExpanded) {
                closeOtherExpandedRows();
            }
            row.setAttribute('aria-expanded', String(!isExpanded));
            details.hidden = isExpanded;
        };

        row.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('button, input, select, textarea, a, form, label')) {
                return;
            }

            toggleDetails();
        });

        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleDetails();
            }
        });
    });

    document.querySelectorAll('.coordinator-record-dialog').forEach((dialog) => {
        let closeTimer = 0;

        const closeDialog = () => {
            if (!dialog.open || closeTimer) {
                return;
            }

            if (dialog.classList.contains('coordinator-duty-review-drawer')) {
                dialog.classList.remove('is-open');
                closeTimer = window.setTimeout(() => {
                    closeTimer = 0;
                    dialog.close();
                }, 280);
                return;
            }

            dialog.close();
        };

        dialog.querySelector('.scholar-report-dialog-close, .coordinator-report-dialog-close')?.addEventListener('click', closeDialog);

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeDialog();
            }
        });

        dialog.addEventListener('cancel', (event) => {
            if (dialog.classList.contains('coordinator-duty-review-drawer')) {
                event.preventDefault();
                closeDialog();
            }
        });

        dialog.addEventListener('close', () => {
            if (closeTimer) {
                window.clearTimeout(closeTimer);
                closeTimer = 0;
            }
            dialog.classList.remove('is-open');
            dialog.returnFocusTo?.focus();
            delete dialog.returnFocusTo;
            updateScholarDialogScrollLock();
        });
    });

    const pageUrl = new URL(window.location.href);
    const submissionId = pageUrl.searchParams.get('open-submission');
    if (submissionId && /^[1-9]\d*$/.test(submissionId)) {
        const dialog = document.getElementById(`duty-submission-dialog-${submissionId}`);
        if (dialog instanceof HTMLDialogElement && dialog.classList.contains('coordinator-duty-review-drawer')) {
            const flashContainer = document.querySelector('.flash-message-container');
            if (flashContainer) {
                dialog.appendChild(flashContainer);
            }

            dialog.returnFocusTo = Array.from(
                document.querySelectorAll('.coordinator-popout-row[data-popout-dialog]')
            ).find((row) => row.getAttribute('data-popout-dialog') === dialog.id);
            dialog.showModal();
            requestAnimationFrame(() => dialog.classList.add('is-open'));
            updateScholarDialogScrollLock();

            pageUrl.searchParams.delete('open-submission');
            window.history.replaceState(
                {},
                '',
                `${pageUrl.pathname}${pageUrl.search}${pageUrl.hash}`
            );
        }
    }

    document.querySelectorAll('.coordinator-scholar-duty-date-filter').forEach((filter) => {
        const input = filter.querySelector('[data-scholar-duty-date-filter]');
        const dialog = filter.closest('.coordinator-record-dialog');
        const rows = dialog
            ? Array.from(dialog.querySelectorAll('.coordinator-scholar-duty-table tbody tr[data-recorded-date]'))
            : [];
        const noMatchesRow = dialog?.querySelector('.coordinator-scholar-duty-no-matches');
        const periodFilter = dialog?.querySelector('[data-scholar-duty-period-filter]');

        if (
            !(input instanceof HTMLSelectElement)
            || !noMatchesRow
            || rows.length === 0
        ) {
            return;
        }

        const dateRangeFor = (filterValue) => {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (filterValue === 'latest') {
                const start = new Date(today);
                start.setDate(start.getDate() - 6);
                return { start, end: today };
            }

            if (filterValue === 'last-week') {
                const currentWeekStart = new Date(today);
                const daysSinceMonday = (today.getDay() + 6) % 7;
                currentWeekStart.setDate(currentWeekStart.getDate() - daysSinceMonday);
                const end = new Date(currentWeekStart);
                end.setDate(end.getDate() - 1);
                const start = new Date(end);
                start.setDate(start.getDate() - 6);
                return { start, end };
            }

            if (filterValue === 'last-month') {
                const start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const end = new Date(today.getFullYear(), today.getMonth(), 0);
                return { start, end };
            }

            return null;
        };

        const parseLocalDate = (dateString) => {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateString || '');
            if (!match) {
                return null;
            }

            const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
            return Number.isNaN(date.getTime()) ? null : date;
        };

        const filterByRecordedDate = () => {
            const range = dateRangeFor(input.value);
            const selectedPeriod = periodFilter instanceof HTMLSelectElement
                ? periodFilter.value
                : 'all';
            let visibleCount = 0;

            rows.forEach((row) => {
                const recordedDate = parseLocalDate(row.dataset.recordedDate || '');
                const matchesDate = !range || (
                    recordedDate !== null
                    && recordedDate >= range.start
                    && recordedDate <= range.end
                );
                const matchesPeriod = selectedPeriod === 'all' || row.dataset.periodId === selectedPeriod;
                const matches = matchesDate && matchesPeriod;
                row.hidden = !matches;
                if (!matches) {
                    const detailsId = row.getAttribute('aria-controls');
                    const details = detailsId ? document.getElementById(detailsId) : null;
                    if (details instanceof HTMLTableRowElement) {
                        row.setAttribute('aria-expanded', 'false');
                        details.hidden = true;
                    }
                }
                if (matches) {
                    visibleCount++;
                }
            });

            noMatchesRow.hidden = visibleCount > 0;
        };

        input.addEventListener('change', filterByRecordedDate);
        periodFilter?.addEventListener('change', filterByRecordedDate);
        filterByRecordedDate();
    });

    const rejectionDialog = document.getElementById('duty-rejection-confirm-dialog');
    const rejectionForm = document.getElementById('duty-rejection-confirm-form');
    const rejectionSubmissionId = document.getElementById('duty-rejection-submission-id');
    const rejectionReason = document.getElementById('duty-rejection-reason');
    const rejectionCancel = rejectionDialog?.querySelector('[data-duty-reject-cancel]');
    let rejectionTrigger = null;

    if (
        rejectionDialog instanceof HTMLDialogElement
        && rejectionForm instanceof HTMLFormElement
        && rejectionSubmissionId instanceof HTMLInputElement
        && rejectionReason instanceof HTMLTextAreaElement
        && rejectionCancel instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('[data-duty-reject-trigger]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const submissionId = trigger.getAttribute('data-submission-id');
                if (!submissionId) {
                    console.error('Unable to open duty rejection confirmation: submission ID was not found.');
                    return;
                }

                rejectionTrigger = trigger;
                rejectionSubmissionId.value = submissionId;
                rejectionReason.value = '';
                rejectionDialog.showModal();
                rejectionReason.focus();
            });
        });

        rejectionCancel.addEventListener('click', () => {
            rejectionDialog.close();
        });

        rejectionDialog.addEventListener('click', (event) => {
            if (event.target === rejectionDialog) {
                rejectionDialog.close();
            }
        });

        rejectionDialog.addEventListener('close', () => {
            rejectionTrigger?.focus();
            rejectionTrigger = null;
        });
    } else if (document.querySelector('[data-duty-reject-trigger]')) {
        console.error('Unable to initialize duty rejection confirmation: dialog controls were not found.');
    }

    const approvalDialog = document.getElementById('duty-approval-confirm-dialog');
    const approvalConfirm = approvalDialog?.querySelector('[data-duty-approval-confirm]');
    const approvalCancel = approvalDialog?.querySelector('[data-duty-approve-cancel]');
    let pendingApprovalForm = null;
    let approvalTrigger = null;

    if (
        approvalDialog instanceof HTMLDialogElement
        && approvalConfirm instanceof HTMLButtonElement
        && approvalCancel instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('[data-confirm-duty-approval]').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                pendingApprovalForm = form;
                approvalTrigger = event.submitter instanceof HTMLElement
                    ? event.submitter
                    : form.querySelector('button[type="submit"]');
                approvalDialog.showModal();
                approvalCancel.focus();
            });
        });

        approvalConfirm.addEventListener('click', () => {
            if (!(pendingApprovalForm instanceof HTMLFormElement)) {
                console.error('Unable to confirm duty approval: pending form was not found.');
                return;
            }

            pendingApprovalForm.submit();
        });

        approvalCancel.addEventListener('click', () => {
            approvalDialog.close();
        });

        approvalDialog.addEventListener('click', (event) => {
            if (event.target === approvalDialog) {
                approvalDialog.close();
            }
        });

        approvalDialog.addEventListener('close', () => {
            approvalTrigger?.focus();
            pendingApprovalForm = null;
            approvalTrigger = null;
        });
    } else if (document.querySelector('[data-confirm-duty-approval]')) {
        console.error('Unable to initialize duty approval confirmation: dialog controls were not found.');
    }

    const submissionUpdateDialog = document.getElementById('duty-submission-update-confirm-dialog');
    const submissionUpdateConfirm = submissionUpdateDialog?.querySelector('[data-duty-submission-update-confirm]');
    const submissionUpdateCancel = submissionUpdateDialog?.querySelector('[data-duty-submission-update-cancel]');
    let pendingSubmissionUpdateForm = null;
    let submissionUpdateTrigger = null;

    if (
        submissionUpdateDialog instanceof HTMLDialogElement
        && submissionUpdateConfirm instanceof HTMLButtonElement
        && submissionUpdateCancel instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('[data-confirm-duty-submission-update]').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                pendingSubmissionUpdateForm = form;
                submissionUpdateTrigger = event.submitter instanceof HTMLElement
                    ? event.submitter
                    : document.querySelector(`button[form="${form.id}"]`);
                submissionUpdateDialog.showModal();
                submissionUpdateCancel.focus();
            });
        });

        submissionUpdateConfirm.addEventListener('click', () => {
            if (!(pendingSubmissionUpdateForm instanceof HTMLFormElement)) {
                console.error('Unable to confirm duty submission update: pending form was not found.');
                return;
            }

            pendingSubmissionUpdateForm.submit();
        });

        submissionUpdateCancel.addEventListener('click', () => {
            submissionUpdateDialog.close();
        });

        submissionUpdateDialog.addEventListener('click', (event) => {
            if (event.target === submissionUpdateDialog) {
                submissionUpdateDialog.close();
            }
        });

        submissionUpdateDialog.addEventListener('close', () => {
            submissionUpdateTrigger?.focus();
            pendingSubmissionUpdateForm = null;
            submissionUpdateTrigger = null;
        });
    } else if (document.querySelector('[data-confirm-duty-submission-update]')) {
        console.error('Unable to initialize duty submission update confirmation: dialog controls were not found.');
    }
});
