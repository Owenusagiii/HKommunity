document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('admin-report-confirm-dialog');
    const confirmButton = dialog?.querySelector('[data-admin-report-confirm-submit]');
    const cancelButton = dialog?.querySelector('[data-admin-report-confirm-cancel]');
    let pendingForm = null;
    let confirmedForm = null;
    let returnFocusTo = null;

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(confirmButton instanceof HTMLButtonElement)
        || !(cancelButton instanceof HTMLButtonElement)
    ) {
        console.error('Unable to initialize Admin report confirmation: dialog controls were not found.');
        return;
    }

    document.querySelectorAll('form.admin-report-update-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (confirmedForm === form) {
                confirmedForm = null;
                return;
            }

            event.preventDefault();
            pendingForm = form;
            returnFocusTo = event.submitter instanceof HTMLElement ? event.submitter : null;
            dialog.showModal();
        });
    });

    confirmButton.addEventListener('click', () => {
        if (!(pendingForm instanceof HTMLFormElement)) {
            console.error('Unable to confirm Admin report update: no pending form was found.');
            return;
        }

        const form = pendingForm;
        pendingForm = null;
        confirmedForm = form;
        dialog.close();
        form.requestSubmit();
    });

    const cancelConfirmation = () => {
        pendingForm = null;
        dialog.close();
    };

    cancelButton.addEventListener('click', cancelConfirmation);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            cancelConfirmation();
        }
    });
    dialog.addEventListener('cancel', () => {
        pendingForm = null;
    });
    dialog.addEventListener('close', () => {
        returnFocusTo?.focus();
        returnFocusTo = null;
    });
});
