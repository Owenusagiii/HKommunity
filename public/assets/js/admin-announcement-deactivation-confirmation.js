document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('admin-announcement-deactivation-confirm-dialog');
    const confirmButton = dialog?.querySelector('[data-announcement-deactivation-confirm]');
    const cancelButton = dialog?.querySelector('[data-announcement-deactivation-cancel]');

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(confirmButton instanceof HTMLButtonElement)
        || !(cancelButton instanceof HTMLButtonElement)
    ) {
        console.error('Unable to initialize announcement deactivation confirmation: dialog controls were not found.');
        return;
    }

    let pendingForm = null;
    let pendingSubmitter = null;
    let confirmedSubmitter = null;
    let returnFocusTo = null;

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter;
            if (!(submitter instanceof HTMLButtonElement) || !submitter.hasAttribute('data-confirm-announcement-deactivation')) {
                return;
            }

            if (confirmedSubmitter === submitter) {
                confirmedSubmitter = null;
                return;
            }

            event.preventDefault();
            pendingForm = form;
            pendingSubmitter = submitter;
            returnFocusTo = submitter;
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
            console.error('Unable to confirm announcement deactivation: no pending form submission was found.');
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
