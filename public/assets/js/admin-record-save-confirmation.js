document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('admin-record-save-confirm-dialog');
    const title = dialog?.querySelector('#admin-record-save-confirm-title');
    const description = dialog?.querySelector('#admin-record-save-confirm-description');
    const confirmButton = dialog?.querySelector('[data-admin-record-save-confirm]');
    const cancelButton = dialog?.querySelector('[data-admin-record-save-cancel]');

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(title instanceof HTMLElement)
        || !(description instanceof HTMLElement)
        || !(confirmButton instanceof HTMLButtonElement)
        || !(cancelButton instanceof HTMLButtonElement)
    ) {
        console.error('Unable to initialize Admin record save confirmation: dialog controls were not found.');
        return;
    }

    let pendingForm = null;
    let pendingSubmitter = null;
    let confirmedSubmitter = null;
    let returnFocusTo = null;

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter;
            if (!(submitter instanceof HTMLButtonElement) || !submitter.hasAttribute('data-confirm-admin-record-save')) {
                return;
            }

            if (confirmedSubmitter === submitter) {
                confirmedSubmitter = null;
                return;
            }

            const recordType = submitter.dataset.confirmAdminRecordSave;
            if (!recordType) {
                console.error('Unable to confirm Admin record save: record type was not provided.');
                event.preventDefault();
                return;
            }

            event.preventDefault();
            pendingForm = form;
            pendingSubmitter = submitter;
            returnFocusTo = submitter;
            title.textContent = `Save ${recordType} changes?`;
            description.textContent = `Are you sure you want to save these ${recordType} changes?`;
            confirmButton.textContent = 'Save Changes';
            dialog.showModal();
            cancelButton.focus();
        });
    });

    const cancelConfirmation = () => {
        pendingForm = null;
        pendingSubmitter = null;
        dialog.close();
    };

    confirmButton.addEventListener('click', () => {
        if (!(pendingForm instanceof HTMLFormElement) || !(pendingSubmitter instanceof HTMLButtonElement)) {
            console.error('Unable to confirm Admin record save: no pending form submission was found.');
            return;
        }

        const form = pendingForm;
        const submitter = pendingSubmitter;
        pendingForm = null;
        pendingSubmitter = null;
        confirmedSubmitter = submitter;
        dialog.close();
        form.requestSubmit(submitter);
    });

    cancelButton.addEventListener('click', cancelConfirmation);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            cancelConfirmation();
        }
    });
    dialog.addEventListener('cancel', () => {
        pendingForm = null;
        pendingSubmitter = null;
    });
    dialog.addEventListener('close', () => {
        returnFocusTo?.focus();
        returnFocusTo = null;
    });
});
