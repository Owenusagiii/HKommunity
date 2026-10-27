const initializeHeaderMenus = () => {
    const menus = document.querySelectorAll('.header-menu');
    const periodDisplay = document.querySelector('.header-period-display');

    if (periodDisplay) {
        const closePeriodDisplay = () => {
            periodDisplay.classList.remove('is-expanded');
            periodDisplay.setAttribute('aria-expanded', 'false');
            periodDisplay.blur();
        };

        periodDisplay.addEventListener('click', (event) => {
            if (!window.matchMedia('(max-width: 520px)').matches) {
                return;
            }

            event.stopPropagation();
            const isExpanded = periodDisplay.classList.toggle('is-expanded');
            periodDisplay.setAttribute('aria-expanded', String(isExpanded));
            if (!isExpanded) {
                periodDisplay.blur();
            }
        });

        document.addEventListener('click', closePeriodDisplay);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePeriodDisplay();
            }
        });
    }

    menus.forEach((menu) => {
        const toggle = menu.querySelector('button');
        const options = menu.querySelector('.header-menu-options');

        if (!toggle || !options) {
            return;
        }

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            menus.forEach((otherMenu) => {
                if (otherMenu !== menu) {
                    otherMenu.querySelector('.header-menu-options')?.setAttribute('hidden', '');
                    otherMenu.querySelector('button')?.setAttribute('aria-expanded', 'false');
                }
            });
            const isOpen = !options.hidden;
            options.hidden = isOpen;
            toggle.setAttribute('aria-expanded', String(!isOpen));
        });

        const close = () => {
            options.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        };

        menu.addEventListener('click', (event) => event.stopPropagation());
        document.addEventListener('click', close);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
                toggle.focus();
            }
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeHeaderMenus, { once: true });
} else {
    initializeHeaderMenus();
}
