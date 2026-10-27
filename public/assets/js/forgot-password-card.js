document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('forgot-password-dialog');
    const trigger = document.querySelector('[data-forgot-password]');
    const closeButton = dialog?.querySelector('[data-forgot-password-close]');

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(trigger instanceof HTMLButtonElement)
        || !(closeButton instanceof HTMLButtonElement)
    ) {
        console.error('Unable to initialize password help card: dialog or buttons were not found.');
        return;
    }

    trigger.addEventListener('click', () => dialog.showModal());
    closeButton.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
    dialog.addEventListener('close', () => trigger.focus());
});
