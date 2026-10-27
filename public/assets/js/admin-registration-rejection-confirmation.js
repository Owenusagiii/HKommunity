document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('admin-registration-reject-confirm-dialog');
    const form = dialog?.querySelector('.admin-registration-reject-confirm-form');
    const registrationId = form?.querySelector('input[name="id"]');
    const reason = form?.querySelector('textarea[name="rejection_reason"]');
    const cancelButton = dialog?.querySelector('[data-admin-registration-reject-cancel]');

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(form instanceof HTMLFormElement)
        || !(registrationId instanceof HTMLInputElement)
        || !(reason instanceof HTMLTextAreaElement)
        || !(cancelButton instanceof HTMLButtonElement)
    ) {
        console.error('Unable to initialize registration rejection confirmation: dialog controls were not found.');
        return;
    }

    let returnFocusTo = null;

    document.querySelectorAll('[data-admin-registration-reject]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const id = trigger.getAttribute('data-admin-registration-reject');
            if (!id) {
                console.error('Unable to reject registration: registration ID was not found.');
                return;
            }

            registrationId.value = id;
            reason.value = '';
            returnFocusTo = trigger;
            dialog.showModal();
            reason.focus();
        });
    });

    const closeDialog = () => dialog.close();

    cancelButton.addEventListener('click', closeDialog);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeDialog();
        }
    });
    dialog.addEventListener('close', () => {
        returnFocusTo?.focus();
        returnFocusTo = null;
    });
});
