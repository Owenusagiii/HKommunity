document.addEventListener('DOMContentLoaded', () => {
    const closeButtonSelector = '.scholar-announcement-dialog-close';

    const updateScrollLock = () => {
        const isOpen = Boolean(document.querySelector('.scholar-announcement-dialog[open]'));
        document.documentElement.classList.toggle('scholar-announcement-dialog-open', isOpen);
        document.body.classList.toggle('scholar-announcement-dialog-open', isOpen);
    };

    const openDialog = (card) => {
        const dialogId = card.dataset.announcementDialog;
        const dialog = dialogId ? document.getElementById(dialogId) : null;

        if (!(dialog instanceof HTMLDialogElement) || dialog.open) {
            return;
        }

        dialog.returnFocusTo = card;
        dialog.showModal();
        updateScrollLock();
    };

    document.querySelectorAll('.scholar-announcement-trigger').forEach((card) => {
        card.addEventListener('click', () => openDialog(card));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDialog(card);
            }
        });
    });

    document.querySelectorAll('.scholar-announcement-dialog').forEach((dialog) => {
        dialog.querySelector(closeButtonSelector)?.addEventListener('click', () => dialog.close());

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        dialog.addEventListener('close', () => {
            dialog.returnFocusTo?.focus();
            delete dialog.returnFocusTo;
            updateScrollLock();
        });
    });
});
