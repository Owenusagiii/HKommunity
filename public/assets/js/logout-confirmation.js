document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('logout-confirm-dialog');
    const cancel = dialog?.querySelector('[data-logout-cancel]');

    if (!(dialog instanceof HTMLDialogElement) || !(cancel instanceof HTMLButtonElement)) {
        console.error('Unable to initialize logout confirmation: dialog or cancel button was not found.');
        return;
    }

    let trigger = null;

    document.querySelectorAll('[data-confirm-logout]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            trigger = link;
            dialog.showModal();
            cancel.focus();
        });
    });

    cancel.addEventListener('click', () => {
        dialog.close();
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener('close', () => {
        trigger?.focus();
        trigger = null;
    });
});
