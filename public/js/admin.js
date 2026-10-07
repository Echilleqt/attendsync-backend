(() => {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const sidebar = document.getElementById('admin-sidebar');

    if (!toggle || !overlay || !sidebar) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 900px)');
    const setMobileOpen = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        overlay.tabIndex = open ? 0 : -1;
    };

    if (mobileQuery.matches) {
        setMobileOpen(false);
    } else {
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Collapse navigation menu');
    }

    toggle.addEventListener('click', () => {
        if (mobileQuery.matches) {
            setMobileOpen(!document.body.classList.contains('sidebar-open'));
            return;
        }

        const collapsed = document.body.classList.toggle('sidebar-collapsed');
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', collapsed ? 'Expand navigation menu' : 'Collapse navigation menu');
    });

    overlay.addEventListener('click', () => setMobileOpen(false));
    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileQuery.matches) {
                setMobileOpen(false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            setMobileOpen(false);
            toggle.focus();
        }
    });

    mobileQuery.addEventListener('change', (event) => {
        if (event.matches) {
            document.body.classList.remove('sidebar-collapsed');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open navigation menu');
        } else {
            setMobileOpen(false);
            toggle.setAttribute('aria-label', document.body.classList.contains('sidebar-collapsed')
                ? 'Expand navigation menu'
                : 'Collapse navigation menu');
            toggle.setAttribute('aria-expanded', String(!document.body.classList.contains('sidebar-collapsed')));
        }
    });
})();
