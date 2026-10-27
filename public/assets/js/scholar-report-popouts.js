document.addEventListener('DOMContentLoaded', () => {
    const submitDialog = document.getElementById('scholar-report-submit-confirm-dialog');
    const submitCancelButton = submitDialog?.querySelector('[data-scholar-report-submit-cancel]');
    const submitConfirmButton = submitDialog?.querySelector('[data-scholar-report-submit-confirm]');
    let pendingSubmitForm = null;
    let confirmedSubmitForm = null;
    let submitTrigger = null;

    if (
        submitDialog instanceof HTMLDialogElement
        && submitCancelButton instanceof HTMLButtonElement
        && submitConfirmButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('form[data-confirm-scholar-report-submit]').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented) {
                    return;
                }

                if (confirmedSubmitForm === form) {
                    confirmedSubmitForm = null;
                    return;
                }

                event.preventDefault();
                pendingSubmitForm = form;
                submitTrigger = event.submitter instanceof HTMLButtonElement
                    ? event.submitter
                    : form.querySelector('button[type="submit"]');
                submitDialog.showModal();
                submitCancelButton.focus();
            });
        });

        submitCancelButton.addEventListener('click', () => {
            submitDialog.close();
        });

        submitConfirmButton.addEventListener('click', () => {
            if (!(pendingSubmitForm instanceof HTMLFormElement)) {
                console.error('Unable to confirm report submission: pending form was not found.');
                return;
            }

            const form = pendingSubmitForm;
            pendingSubmitForm = null;
            confirmedSubmitForm = form;
            submitDialog.close();
            if (submitTrigger instanceof HTMLButtonElement) {
                form.requestSubmit(submitTrigger);
            } else {
                form.requestSubmit();
            }
        });

        submitDialog.addEventListener('click', (event) => {
            if (event.target === submitDialog) {
                submitDialog.close();
            }
        });

        submitDialog.addEventListener('cancel', () => {
            pendingSubmitForm = null;
        });

        submitDialog.addEventListener('close', () => {
            submitTrigger?.focus();
            pendingSubmitForm = null;
            submitTrigger = null;
        });
    } else if (document.querySelector('form[data-confirm-scholar-report-submit]')) {
        console.error('Unable to initialize report submission confirmation: dialog or buttons were not found.');
    }

    const deleteDialog = document.getElementById('scholar-report-delete-confirm-dialog');
    const deleteCancelButton = deleteDialog?.querySelector('[data-scholar-report-delete-cancel]');
    const deleteConfirmButton = deleteDialog?.querySelector('[data-scholar-report-delete-confirm]');
    let pendingDeleteForm = null;
    let deleteTrigger = null;

    if (
        deleteDialog instanceof HTMLDialogElement
        && deleteCancelButton instanceof HTMLButtonElement
        && deleteConfirmButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('[data-confirm-scholar-report-delete]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                pendingDeleteForm = form;
                deleteTrigger = event.submitter instanceof HTMLElement
                    ? event.submitter
                    : form.querySelector('button[type="submit"]');
                deleteDialog.showModal();
                deleteCancelButton.focus();
            });
        });

        deleteCancelButton.addEventListener('click', () => deleteDialog.close());

        deleteConfirmButton.addEventListener('click', () => {
            if (!(pendingDeleteForm instanceof HTMLFormElement)) {
                console.error('Unable to delete Scholar report: pending form was not found.');
                return;
            }
            pendingDeleteForm.submit();
        });

        deleteDialog.addEventListener('click', (event) => {
            if (event.target === deleteDialog) {
                deleteDialog.close();
            }
        });

        deleteDialog.addEventListener('close', () => {
            deleteTrigger?.focus();
            pendingDeleteForm = null;
            deleteTrigger = null;
        });
    } else {
        console.error('Unable to initialize Scholar report deletion confirmation: dialog or buttons were not found.');
    }

    const updateScrollLock = () => {
        const isOpen = Boolean(document.querySelector('.scholar-report-dialog[open]'));
        document.documentElement.classList.toggle('scholar-report-dialog-open', isOpen);
        document.body.classList.toggle('scholar-report-dialog-open', isOpen);
    };

    const openDialog = (row) => {
        const dialogId = row.dataset.reportDialog;
        const dialog = dialogId ? document.getElementById(dialogId) : null;

        if (!(dialog instanceof HTMLDialogElement) || dialog.open) {
            return;
        }

        dialog.returnFocusTo = row;
        dialog.showModal();
        updateScrollLock();
    };

    document.querySelectorAll('.scholar-report-row').forEach((row) => {
        row.addEventListener('click', () => openDialog(row));
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDialog(row);
            }
        });
    });

    document.querySelectorAll('.scholar-report-dialog').forEach((dialog) => {
        dialog.querySelector('.scholar-report-dialog-close')?.addEventListener('click', () => dialog.close());

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        dialog.addEventListener('close', () => {
            dialog.returnFocusTo?.focus();
            delete dialog.returnFocusTo;
            updateScrollLock();
        });
    });
});
