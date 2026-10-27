document.addEventListener('DOMContentLoaded', () => {
    const drawerCloseTimers = new WeakMap();
    const isAnimatedDrawer = (dialog) => (
        dialog.classList.contains('admin-user-detail-dialog')
        || dialog.classList.contains('admin-registration-detail-dialog')
        || dialog.classList.contains('admin-import-detail-dialog')
        || dialog.classList.contains('admin-record-drawer')
    );

    const showAdminDialog = (dialog) => {
        const closeTimer = drawerCloseTimers.get(dialog);
        if (closeTimer) {
            window.clearTimeout(closeTimer);
            drawerCloseTimers.delete(dialog);
        }

        dialog.showModal();
        if (isAnimatedDrawer(dialog)) {
            requestAnimationFrame(() => dialog.classList.add('is-open'));
        }
    };

    const closeDialog = (dialog) => {
        if (!isAnimatedDrawer(dialog)) {
            dialog.close();
            return;
        }

        dialog.classList.remove('is-open');
        const closeTimer = window.setTimeout(() => {
            drawerCloseTimers.delete(dialog);
            if (dialog.open) {
                dialog.close();
            }
        }, 280);
        drawerCloseTimers.set(dialog, closeTimer);
    };

    document.querySelectorAll('[data-admin-open-dialog]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const dialogId = trigger.getAttribute('data-admin-open-dialog');
            const dialog = dialogId ? document.getElementById(dialogId) : null;

            if (!(dialog instanceof HTMLDialogElement)) {
                console.error('Unable to open Admin dialog: dialog was not found.');
                return;
            }

            showAdminDialog(dialog);
        });
    });

    document.querySelectorAll('.admin-popout-row').forEach((row) => {
        const openRowDialog = () => {
            const dialogId = row.getAttribute('data-admin-dialog');
            const dialog = dialogId ? document.getElementById(dialogId) : null;

            if (!(dialog instanceof HTMLDialogElement)) {
                console.error('Unable to open Admin record: dialog was not found.');
                return;
            }

            showAdminDialog(dialog);
        };

        row.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('button, input, select, textarea, a, form, label')) {
                return;
            }

            openRowDialog();
        });

        row.addEventListener('keydown', (event) => {
            if (event.target instanceof Element && event.target !== row && event.target.closest('button, input, select, textarea, a, form, label')) {
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openRowDialog();
            }
        });
    });

    document.querySelectorAll('.admin-record-dialog').forEach((dialog) => {
        if (dialog.dataset.autoOpen === 'true') {
            showAdminDialog(dialog);
            dialog.querySelector('[aria-invalid="true"]')?.focus();
        }

        dialog.querySelectorAll('.coordinator-report-dialog-close, .scholar-requirement-dialog-close, [data-admin-dialog-close]').forEach((closeButton) => {
            closeButton.addEventListener('click', () => {
                closeDialog(dialog);
            });
        });

        dialog.addEventListener('cancel', (event) => {
            if (isAnimatedDrawer(dialog)) {
                event.preventDefault();
                closeDialog(dialog);
            }
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeDialog(dialog);
            }
        });

        dialog.addEventListener('close', () => {
            dialog.classList.remove('is-open');
            const closeTimer = drawerCloseTimers.get(dialog);
            if (closeTimer) {
                window.clearTimeout(closeTimer);
                drawerCloseTimers.delete(dialog);
            }
        });
    });
});
