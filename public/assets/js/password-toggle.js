document.addEventListener('DOMContentLoaded', () => {
    const eyeOpenIcon = `
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"></path>
            <circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8" fill="none"></circle>
        </svg>
    `;

    const eyeSlashIcon = `
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
            <path d="M10.6 10.6A3 3 0 0 0 13.4 13.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none"></path>
            <path d="M9.1 5.5A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a17.8 17.8 0 0 1-4.3 5.7M6.7 6.8A17.8 17.8 0 0 0 2 12s3.5 7 10 7a11.7 11.7 0 0 0 5.4-1.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"></path>
        </svg>
    `;

    document.querySelectorAll('.password-toggle').forEach((button) => {
        const targetId = button.dataset.target;
        const input = targetId ? document.getElementById(targetId) : null;

        if (!input || !button) {
            return;
        }

        const updateButtonState = () => {
            const isPasswordHidden = input.type === 'password';
            button.innerHTML = isPasswordHidden ? eyeOpenIcon : eyeSlashIcon;
            button.setAttribute('aria-label', isPasswordHidden ? 'Show password' : 'Hide password');
            button.setAttribute('aria-pressed', (!isPasswordHidden).toString());
        };

        updateButtonState();

        button.addEventListener('click', () => {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            updateButtonState();
            input.focus();
        });
    });
});
