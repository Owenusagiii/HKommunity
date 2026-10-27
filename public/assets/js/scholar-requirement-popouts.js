document.addEventListener('DOMContentLoaded', () => {
    const confirmationDialog = document.getElementById('scholar-requirement-confirm-dialog');
    const confirmationTitle = confirmationDialog?.querySelector('#scholar-requirement-confirm-title');
    const confirmationDescription = confirmationDialog?.querySelector('#scholar-requirement-confirm-description');
    const confirmationButton = confirmationDialog?.querySelector('[data-requirement-submit-confirm]');
    const cancelButton = confirmationDialog?.querySelector('[data-requirement-submit-cancel]');
    let pendingForm = null;
    let confirmedForm = null;
    let returnFocusTo = null;

    if (
        confirmationDialog instanceof HTMLDialogElement
        && confirmationTitle instanceof HTMLElement
        && confirmationDescription instanceof HTMLElement
        && confirmationButton instanceof HTMLButtonElement
        && cancelButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('form[data-confirm-requirement-submit]').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', (event) => {
                if (confirmedForm === form) {
                    confirmedForm = null;
                    return;
                }

                event.preventDefault();
                pendingForm = form;
                returnFocusTo = event.submitter instanceof HTMLElement ? event.submitter : null;

                const isResubmission = form.querySelector('button[type="submit"]')?.textContent?.trim() === 'Resubmit link';
                confirmationTitle.textContent = isResubmission ? 'Resubmit requirement?' : 'Submit requirement?';
                confirmationDescription.textContent = isResubmission
                    ? 'Are you sure you want to resubmit this Google Drive link for review?'
                    : 'Are you sure you want to submit this Google Drive link for review?';
                confirmationButton.textContent = isResubmission ? 'Resubmit link' : 'Submit link';
                confirmationDialog.showModal();
            });
        });

        confirmationButton.addEventListener('click', () => {
            if (!pendingForm) {
                console.error('Unable to confirm requirement submission: no pending form was found.');
                return;
            }

            const form = pendingForm;
            pendingForm = null;
            confirmedForm = form;
            confirmationDialog.close();
            form.requestSubmit();
        });

        cancelButton.addEventListener('click', () => {
            pendingForm = null;
            confirmationDialog.close();
        });

        confirmationDialog.addEventListener('click', (event) => {
            if (event.target === confirmationDialog) {
                pendingForm = null;
                confirmationDialog.close();
            }
        });

        confirmationDialog.addEventListener('cancel', () => {
            pendingForm = null;
        });

        confirmationDialog.addEventListener('close', () => {
            returnFocusTo?.focus();
            returnFocusTo = null;
        });
    } else if (document.querySelector('form[data-confirm-requirement-submit]')) {
        console.error('Unable to initialize requirement submission confirmation: dialog controls were not found.');
    }

    const openDialog = (row) => {
        const dialogId = row.dataset.requirementDialog;
        const dialog = dialogId ? document.getElementById(dialogId) : null;

        if (!(dialog instanceof HTMLDialogElement) || dialog.open) {
            return;
        }

        dialog.returnFocusTo = row;
        dialog.showModal();
        requestAnimationFrame(() => dialog.classList.add('is-open'));
        document.documentElement.classList.add('scholar-requirement-dialog-open');
        document.body.classList.add('scholar-requirement-dialog-open');
    };

    document.querySelectorAll('.scholar-requirement-row').forEach((row) => {
        row.addEventListener('click', () => openDialog(row));
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDialog(row);
            }
        });
    });

    document.querySelectorAll('.scholar-requirement-dialog').forEach((dialog) => {
        dialog.addEventListener('transitionend', (event) => {
            if (event.target === dialog && event.propertyName === 'transform' && !dialog.open) {
                dialog.classList.remove('is-open');
            }
        });

        dialog.querySelector('.scholar-requirement-dialog-close')?.addEventListener('click', () => {
            dialog.close();
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        dialog.addEventListener('close', () => {
            dialog.classList.remove('is-open');
            dialog.returnFocusTo?.focus();
            delete dialog.returnFocusTo;
            if (!document.querySelector('.scholar-requirement-dialog[open]')) {
                document.documentElement.classList.remove('scholar-requirement-dialog-open');
                document.body.classList.remove('scholar-requirement-dialog-open');
            }
        });
    });
});
