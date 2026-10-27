document.addEventListener('DOMContentLoaded', () => {
    const updateScrollLock = () => {
        const isOpen = Boolean(document.querySelector('.admin-management-detail-dialog[open]'));
        document.documentElement.classList.toggle('scholar-report-dialog-open', isOpen);
        document.body.classList.toggle('scholar-report-dialog-open', isOpen);
    };

    document.querySelectorAll('[data-management-dialog]').forEach((trigger) => {
        const openDialog = () => {
            const dialogId = trigger.getAttribute('data-management-dialog');
            const dialog = dialogId ? document.getElementById(dialogId) : null;

            if (!(dialog instanceof HTMLDialogElement)) {
                console.error('Unable to open Management detail: dialog was not found.');
                return;
            }

            if (dialog.open) {
                return;
            }

            dialog.returnFocusTo = trigger;
            dialog.showModal();
            requestAnimationFrame(() => dialog.classList.add('is-open'));
            updateScrollLock();
        };

        trigger.addEventListener('click', openDialog);
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDialog();
            }
        });
    });

    document.querySelectorAll('[data-management-expand-row]').forEach((row) => {
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
                console.error('Unable to expand Management requirement: details row was not found.');
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
            if (event.target !== row || (event.key !== 'Enter' && event.key !== ' ')) {
                return;
            }

            event.preventDefault();
            toggleDetails();
        });
    });

    document.querySelectorAll('.admin-management-detail-dialog').forEach((dialog) => {
        let closeTimer = 0;

        dialog.querySelectorAll('[data-management-duty-row]').forEach((row) => {
            const detailsId = row.getAttribute('aria-controls');
            const details = detailsId ? document.getElementById(detailsId) : null;
            if (!(details instanceof HTMLTableRowElement)) {
                console.error('Unable to initialize Management duty row: file details were not found.');
                return;
            }

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

            const toggleFiles = () => {
                const isExpanded = row.getAttribute('aria-expanded') === 'true';
                if (!isExpanded) {
                    closeOtherExpandedRows();
                }
                row.setAttribute('aria-expanded', String(!isExpanded));
                details.hidden = isExpanded;
            };

            row.addEventListener('click', (event) => {
                if (event.target instanceof Element && event.target.closest('a, button, input, select, textarea, label')) {
                    return;
                }

                toggleFiles();
            });

            row.addEventListener('keydown', (event) => {
                if (event.target !== row || (event.key !== 'Enter' && event.key !== ' ')) {
                    return;
                }

                event.preventDefault();
                toggleFiles();
            });
        });

        const dateFilter = dialog.querySelector('[data-management-duty-date-filter]');
        if (dateFilter instanceof HTMLSelectElement) {
            const dutyRows = Array.from(dialog.querySelectorAll('tr[data-recorded-date]'));
            const emptyRow = dialog.querySelector('[data-management-duty-empty]');
            const countElement = dialog.querySelector('[data-management-duty-count]');
            const countLabel = dialog.querySelector('[data-management-duty-count-label]');

            if (
                !(emptyRow instanceof HTMLTableRowElement)
                || !(countElement instanceof HTMLElement)
                || !(countLabel instanceof HTMLElement)
            ) {
                console.error('Unable to initialize Management duty date filter: count or empty-state elements were not found.');
            } else {
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

                const filterDutyRows = () => {
                    const range = dateRangeFor(dateFilter.value);
                    let visibleCount = 0;

                    dutyRows.forEach((row) => {
                        const recordedDate = parseLocalDate(row.dataset.recordedDate || '');
                        const matchesDate = !range || (
                            recordedDate !== null
                            && recordedDate >= range.start
                            && recordedDate <= range.end
                        );
                        row.hidden = !matchesDate;
                        const detailsId = row.getAttribute('aria-controls');
                        const details = detailsId ? document.getElementById(detailsId) : null;
                        if (details instanceof HTMLTableRowElement && !matchesDate) {
                            row.setAttribute('aria-expanded', 'false');
                            details.hidden = true;
                        }
                        visibleCount += Number(matchesDate);
                    });

                    emptyRow.hidden = visibleCount > 0;
                    countElement.textContent = String(visibleCount);
                    countLabel.textContent = visibleCount === 1 ? 'record' : 'records';
                };

                dateFilter.addEventListener('change', filterDutyRows);
                filterDutyRows();
            }
        }

        const closeDialog = () => {
            if (!dialog.open || closeTimer) {
                return;
            }

            dialog.classList.remove('is-open');
            closeTimer = window.setTimeout(() => {
                closeTimer = 0;
                dialog.close();
            }, 280);
        };

        dialog.querySelector('.scholar-report-dialog-close')?.addEventListener('click', closeDialog);

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeDialog();
            }
        });

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDialog();
        });

        dialog.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
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
            updateScrollLock();
        });
    });
});
