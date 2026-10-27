document.addEventListener('DOMContentLoaded', () => {
    const reviewConfirmDialog = document.getElementById('coordinator-requirement-confirm-dialog');
    const reviewConfirmButton = reviewConfirmDialog?.querySelector('[data-requirement-review-confirm]');
    const reviewCancelButton = reviewConfirmDialog?.querySelector('[data-requirement-review-cancel]');
    const rejectionDialog = document.getElementById('coordinator-requirement-reject-dialog');
    const rejectionForm = rejectionDialog?.querySelector('[data-requirement-rejection-form]');
    const rejectionReason = rejectionForm?.querySelector('textarea[name="reason"]');
    const rejectionId = rejectionForm?.querySelector('input[name="id"]');
    const rejectionCancelButton = rejectionDialog?.querySelector('[data-requirement-rejection-cancel]');
    let pendingReviewForm = null;
    let confirmedReviewForm = null;
    let reviewSubmitTrigger = null;
    let rejectionTrigger = null;

    if (
        reviewConfirmDialog instanceof HTMLDialogElement
        && reviewConfirmButton instanceof HTMLButtonElement
        && reviewCancelButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('form[data-confirm-requirement-review]').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', (event) => {
                if (confirmedReviewForm === form) {
                    confirmedReviewForm = null;
                    return;
                }

                event.preventDefault();
                pendingReviewForm = form;
                reviewSubmitTrigger = event.submitter instanceof HTMLElement ? event.submitter : null;
                reviewConfirmDialog.showModal();
            });
        });

        reviewConfirmButton.addEventListener('click', () => {
            if (!pendingReviewForm) {
                console.error('Unable to confirm requirement review: no pending review form was found.');
                return;
            }

            const form = pendingReviewForm;
            pendingReviewForm = null;
            confirmedReviewForm = form;
            reviewConfirmDialog.close();
            form.requestSubmit();
        });

        const cancelReview = () => {
            pendingReviewForm = null;
            reviewConfirmDialog.close();
        };

        reviewCancelButton.addEventListener('click', cancelReview);

        reviewConfirmDialog.addEventListener('click', (event) => {
            if (event.target === reviewConfirmDialog) {
                cancelReview();
            }
        });

        reviewConfirmDialog.addEventListener('cancel', () => {
            pendingReviewForm = null;
        });

        reviewConfirmDialog.addEventListener('close', () => {
            reviewSubmitTrigger?.focus();
            reviewSubmitTrigger = null;
        });
    } else if (document.querySelector('form[data-confirm-requirement-review]')) {
        console.error('Unable to initialize requirement review confirmation: dialog controls were not found.');
    }

    if (
        rejectionDialog instanceof HTMLDialogElement
        && rejectionForm instanceof HTMLFormElement
        && rejectionReason instanceof HTMLTextAreaElement
        && rejectionId instanceof HTMLInputElement
        && rejectionCancelButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('[data-requirement-reject]').forEach((button) => {
            button.addEventListener('click', () => {
                const reviewForm = button.closest('form[data-confirm-requirement-review]');
                const reviewId = reviewForm?.querySelector('input[name="id"]');
                if (!(reviewForm instanceof HTMLFormElement) || !(reviewId instanceof HTMLInputElement)) {
                    console.error('Unable to reject requirement: review form details were not found.');
                    return;
                }

                rejectionId.value = reviewId.value;
                rejectionReason.value = '';
                rejectionReason.setCustomValidity('');
                rejectionTrigger = button;
                rejectionDialog.showModal();
                rejectionReason.focus();
            });
        });

        rejectionReason.addEventListener('input', () => {
            if (rejectionReason.value.trim()) {
                rejectionReason.setCustomValidity('');
            }
        });

        rejectionForm.addEventListener('submit', (event) => {
            const reason = rejectionReason.value.trim();
            rejectionReason.setCustomValidity(reason ? '' : 'Enter a reason for rejecting this requirement.');
            if (!reason) {
                event.preventDefault();
                rejectionReason.reportValidity();
                return;
            }

            rejectionReason.value = reason;
        });

        const closeRejectionDialog = () => {
            rejectionDialog.close();
        };

        rejectionCancelButton.addEventListener('click', closeRejectionDialog);

        rejectionDialog.addEventListener('click', (event) => {
            if (event.target === rejectionDialog) {
                closeRejectionDialog();
            }
        });

        rejectionDialog.addEventListener('close', () => {
            rejectionTrigger?.focus();
            rejectionTrigger = null;
        });
    } else if (document.querySelector('[data-requirement-reject]')) {
        console.error('Unable to initialize requirement rejection: reason dialog controls were not found.');
    }

    document.querySelectorAll('[data-requirement-expand]').forEach((row) => {
        const closeOtherExpandedRows = () => {
            const tbody = row.closest('tbody');
            tbody?.querySelectorAll('tr[aria-expanded="true"][aria-controls]').forEach((expandedRow) => {
                if (expandedRow === row) {
                    return;
                }

                const detailsId = expandedRow.getAttribute('aria-controls');
                const details = detailsId ? document.getElementById(detailsId) : null;
                expandedRow.setAttribute('aria-expanded', 'false');
                if (details instanceof HTMLTableRowElement) {
                    details.hidden = true;
                }
            });
        };

        const toggleDetails = () => {
            const detailsId = row.getAttribute('aria-controls');
            const details = detailsId ? document.getElementById(detailsId) : null;

            if (!(details instanceof HTMLTableRowElement)) {
                console.error('Unable to expand assigned requirement: details row was not found.');
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

    document.querySelectorAll('[data-choice-group]').forEach((group) => {
        const toggle = group.querySelector('.requirement-select-all');
        const fieldName = group.dataset.choiceGroup;
        const checkboxes = Array.from(group.querySelectorAll(`input[name="${fieldName}"]:not(:disabled)`));
        const search = group.querySelector('.scholar-id-search');
        const scholarOptions = Array.from(group.querySelectorAll('.requirement-choice[data-student-id]'));

        if (!toggle || !fieldName) {
            return;
        }

        const visibleCheckboxes = () => checkboxes.filter((checkbox) => {
            const option = checkbox.closest('.requirement-choice');
            return option && !option.hidden;
        });

        const updateToggle = () => {
            const visible = visibleCheckboxes();
            const allSelected = visible.length > 0 && visible.every((checkbox) => checkbox.checked);
            toggle.textContent = allSelected ? 'Clear selection' : 'Select all';
            toggle.setAttribute('aria-pressed', String(allSelected));
            toggle.disabled = visible.length === 0;
        };

        toggle.addEventListener('click', () => {
            const visible = visibleCheckboxes();
            const selectAll = !visible.every((checkbox) => checkbox.checked);
            visible.forEach((checkbox) => {
                checkbox.checked = selectAll;
            });
            updateToggle();
        });

        checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateToggle));

        if (search && scholarOptions.length > 0) {
            search.addEventListener('input', () => {
                const query = search.value.trim().toLocaleLowerCase();
                scholarOptions.forEach((option) => {
                    option.hidden = !option.dataset.studentId.toLocaleLowerCase().includes(query);
                });
                updateToggle();
            });
        }

        updateToggle();
    });

    const assignmentSearch = document.getElementById('assigned-requirements-search');
    const assignmentRows = Array.from(document.querySelectorAll('.coordinator-requirements-table tr.coordinator-popout-row[data-student-id]'));
    const noMatchesRow = document.querySelector('.requirement-search-empty');

    if (assignmentSearch && assignmentRows.length > 0 && noMatchesRow) {
        assignmentSearch.addEventListener('input', () => {
            const query = assignmentSearch.value.trim().toLocaleLowerCase();
            let visibleCount = 0;

            assignmentRows.forEach((row) => {
                const matches = row.dataset.studentId.toLocaleLowerCase().includes(query);
                row.hidden = !matches;
                if (matches) {
                    visibleCount++;
                }
            });

            noMatchesRow.hidden = visibleCount > 0;
        });
    }
});
