document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('admin-action-confirm-dialog');
    const title = dialog?.querySelector('#admin-action-confirm-title');
    const description = dialog?.querySelector('#admin-action-confirm-description');
    const confirmButton = dialog?.querySelector('[data-admin-action-confirm]');
    const cancelButton = dialog?.querySelector('[data-admin-action-cancel]');

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(title instanceof HTMLElement)
        || !(description instanceof HTMLElement)
        || !(confirmButton instanceof HTMLButtonElement)
        || !(cancelButton instanceof HTMLButtonElement)
    ) {
        console.error('Unable to initialize Admin action confirmation: dialog controls were not found.');
        return;
    }

    let pendingForm = null;
    let pendingSubmitter = null;
    let confirmedSubmitter = null;
    let returnFocusTo = null;

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter;
            if (!(submitter instanceof HTMLButtonElement) || !submitter.hasAttribute('data-confirm-admin-action')) {
                return;
            }

            if (confirmedSubmitter === submitter) {
                confirmedSubmitter = null;
                return;
            }

            const confirmTitle = submitter.dataset.confirmTitle;
            const confirmDescription = submitter.dataset.confirmDescription;
            const submitLabel = submitter.dataset.confirmSubmitLabel;
            if (!confirmTitle || !confirmDescription || !submitLabel) {
                console.error('Unable to confirm Admin action: confirmation text was not provided.');
                event.preventDefault();
                return;
            }

            event.preventDefault();
            pendingForm = form;
            pendingSubmitter = submitter;
            returnFocusTo = submitter;
            title.textContent = confirmTitle;
            description.textContent = confirmDescription;
            confirmButton.textContent = submitLabel;
            dialog.classList.toggle('admin-action-confirm-danger', submitter.dataset.confirmVariant === 'danger');
            dialog.classList.toggle('admin-action-confirm-success', submitter.dataset.confirmVariant === 'success');
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
            console.error('Unable to confirm Admin action: no pending form submission was found.');
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
