document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('app-sidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    const reopen = document.querySelector('.sidebar-reopen');
    const overlay = document.querySelector('[data-sidebar-overlay]');

    if (!sidebar || !toggle || !reopen) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 800px)');
    const storageKey = 'HKommunity-sidebar-collapsed';
    const iconMap = {
        Dashboard: '\uf015',
        Users: '\uf0c0',
        Scholars: '\uf19d',
        Coordinators: '\uf508',
        Registration: '\uf044',
        'Registration Import': '\uf56f',
        'Academic Years': '\uf073',
        Semesters: '\uf017',
        'Renewal Periods': '\uf1da',
        Requirements: '\uf0ae',
        Renewals: '\uf058',
        'Duty Records': '\uf0b1',
        'Duty Hours': '\uf017',
        'My Duty Hours': '\uf017',
        Announcements: '\uf0a1',
        'Quick Links': '\uf0c1',
        'My Requirements': '\uf0ae',
        'My Duty Records': '\uf017',
        'My Renewal': '\uf058',
        'Log out': '\uf2f5',
    };

    const isMobile = () => mobileQuery.matches;

    sidebar.querySelectorAll('a').forEach((link) => {
        const words = link.textContent.trim().split(/\s+/).filter(Boolean);
        const label = link.textContent.trim();
        link.dataset.short = words.slice(0, 2).map((word) => word[0]).join('').toUpperCase();
        link.dataset.icon = iconMap[label] || '\uf0c9';
        link.title = label;
    });

    const setState = (collapsed, openMobile = false) => {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        document.body.classList.toggle('sidebar-open', openMobile);
        toggle.setAttribute('aria-expanded', String(!collapsed || openMobile));
        toggle.setAttribute(
            'aria-label',
            openMobile || !collapsed ? 'Collapse navigation' : 'Expand navigation'
        );
        reopen.setAttribute('aria-expanded', String(!collapsed));
        if (overlay) {
            overlay.hidden = !openMobile;
        }
    };

    const savedCollapsed = localStorage.getItem(storageKey) === 'true';
    setState(isMobile() ? true : savedCollapsed);
    document.body.classList.add('sidebar-ready');

    toggle.addEventListener('click', () => {
        if (isMobile()) {
            const opening = !document.body.classList.contains('sidebar-open');
            setState(true, opening);
            return;
        }

        const collapsed = !document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem(storageKey, String(collapsed));
        setState(collapsed);
    });

    reopen.addEventListener('click', () => {
        if (isMobile()) {
            setState(true, true);
            return;
        }

        localStorage.setItem(storageKey, 'false');
        setState(false);
    });

    overlay?.addEventListener('click', () => setState(true));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            setState(true);
            toggle.focus();
        }
    });

    mobileQuery.addEventListener('change', (event) => {
        setState(event.matches ? true : localStorage.getItem(storageKey) === 'true');
    });
});
