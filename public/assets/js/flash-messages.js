document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-dismiss]').forEach((message) => {
        let dismissTimer = 0;

        const scheduleDismiss = () => {
            window.clearTimeout(dismissTimer);
            dismissTimer = window.setTimeout(dismiss, 5000);
        };

        const dismiss = () => {
            if (message.classList.contains('flash-message-dismissing')) {
                return;
            }
            message.classList.add('flash-message-dismissing');
            message.addEventListener('transitionend', () => {
                message.remove();
            }, { once: true });
            window.setTimeout(() => message.remove(), 400);
        };

        message.querySelector('.flash-message-close')?.addEventListener('click', dismiss);
        message.addEventListener('mouseenter', () => window.clearTimeout(dismissTimer));
        message.addEventListener('mouseleave', scheduleDismiss);
        scheduleDismiss();
    });
});
