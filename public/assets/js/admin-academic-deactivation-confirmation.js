document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('admin-academic-deactivation-confirm-dialog');
    const title = dialog?.querySelector('#admin-academic-deactivation-confirm-title');
    const description = dialog?.querySelector('#admin-academic-deactivation-confirm-description');
    const confirmButton = dialog?.querySelector('[data-academic-deactivation-confirm]');
    const cancelButton = dialog?.querySelector('[data-academic-deactivation-cancel]');

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(title instanceof HTMLElement)
        || !(description instanceof HTMLElement)
        || !(confirmButton instanceof HTMLButtonElement)
        || !(cancelButton instanceof HTMLButtonElement)
    ) {
        console.error('Unable to initialize academic deactivation confirmation: dialog controls were not found.');
        return;
    }

    let pendingForm = null;
    let confirmedForm = null;
    let returnFocusTo = null;

    document.querySelectorAll('form[data-confirm-academic-deactivation]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (confirmedForm === form) {
                confirmedForm = null;
                return;
            }

            event.preventDefault();
            const periodType = form.dataset.confirmAcademicDeactivation;
            if (periodType !== 'year' && periodType !== 'semester') {
                console.error('Unable to confirm academic deactivation: period type was not recognized.');
                return;
            }

            const submitter = event.submitter;
            pendingForm = form;
            returnFocusTo = submitter instanceof HTMLElement ? submitter : null;
            title.textContent = periodType === 'year'
                ? 'Deactivate academic year?'
                : 'Deactivate semester?';
            description.textContent = periodType === 'year'
                ? 'Deactivating this academic year will also deactivate its semesters. Continue?'
                : 'Are you sure you want to deactivate this semester?';
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
            console.error('Unable to confirm academic deactivation: no pending form submission was found.');
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
