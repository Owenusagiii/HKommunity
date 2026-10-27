document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('admin-account-deactivation-confirm-dialog');
    const title = dialog?.querySelector('#admin-account-deactivation-confirm-title');
    const description = dialog?.querySelector('#admin-account-deactivation-confirm-description');
    const confirmButton = dialog?.querySelector('[data-account-deactivation-confirm]');
    const cancelButton = dialog?.querySelector('[data-account-deactivation-cancel]');

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(title instanceof HTMLElement)
        || !(description instanceof HTMLElement)
        || !(confirmButton instanceof HTMLButtonElement)
        || !(cancelButton instanceof HTMLButtonElement)
    ) {
        console.error('Unable to initialize account deactivation confirmation: dialog controls were not found.');
        return;
    }

    let pendingForm = null;
    let pendingSubmitter = null;
    let confirmedSubmitter = null;
    let returnFocusTo = null;

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter;
            if (!(submitter instanceof HTMLButtonElement) || !submitter.hasAttribute('data-confirm-account-deactivation')) {
                return;
            }

            if (confirmedSubmitter === submitter) {
                confirmedSubmitter = null;
                return;
            }

            event.preventDefault();
            const accountType = submitter.dataset.confirmAccountDeactivation;
            if (accountType !== 'Scholar' && accountType !== 'Coordinator') {
                console.error('Unable to confirm account deactivation: account type was not recognized.');
                return;
            }

            pendingForm = form;
            pendingSubmitter = submitter;
            returnFocusTo = submitter;
            title.textContent = `Deactivate ${accountType.toLowerCase()} account?`;
            description.textContent = `Are you sure you want to deactivate this ${accountType.toLowerCase()} account? The user will no longer be able to access their account.`;
            confirmButton.textContent = `Deactivate ${accountType}`;
            dialog.showModal();
        });
    });

    const cancelConfirmation = () => {
        pendingForm = null;
        pendingSubmitter = null;
        dialog.close();
    };

    confirmButton.addEventListener('click', () => {
        if (!(pendingForm instanceof HTMLFormElement) || !(pendingSubmitter instanceof HTMLButtonElement)) {
            console.error('Unable to confirm account deactivation: no pending form submission was found.');
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
