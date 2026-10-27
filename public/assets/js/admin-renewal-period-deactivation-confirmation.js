document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('admin-renewal-period-deactivation-confirm-dialog');
    const confirmButton = dialog?.querySelector('[data-renewal-period-deactivation-confirm]');
    const cancelButton = dialog?.querySelector('[data-renewal-period-deactivation-cancel]');

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(confirmButton instanceof HTMLButtonElement)
        || !(cancelButton instanceof HTMLButtonElement)
    ) {
        console.error('Unable to initialize renewal period deactivation confirmation: dialog controls were not found.');
        return;
    }

    let pendingForm = null;
    let confirmedForm = null;
    let returnFocusTo = null;

    document.querySelectorAll('form[data-confirm-renewal-period-deactivation]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (confirmedForm === form) {
                confirmedForm = null;
                return;
            }

            event.preventDefault();
            pendingForm = form;
            returnFocusTo = event.submitter instanceof HTMLElement ? event.submitter : null;
            dialog.showModal();
            cancelButton.focus();
        });
    });

    const cancelConfirmation = () => {
        pendingForm = null;
        dialog.close();
    };

    confirmButton.addEventListener('click', () => {
        if (!(pendingForm instanceof HTMLFormElement)) {
            console.error('Unable to confirm renewal period deactivation: no pending form submission was found.');
            return;
        }

        const form = pendingForm;
        pendingForm = null;
        confirmedForm = form;
        dialog.close();
        form.requestSubmit();
    });

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
