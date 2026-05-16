(function () {
    const storageKey = '__HIGHDMIN_CONFIG__';
    const html = document.documentElement;
    const defaults = {
        theme: 'light',
        layout: { mode: 'fluid' },
        topbar: { color: 'light' },
        menu: { color: 'light' },
        sidenav: { size: 'sm-hover-active' },
    };
    const clone = (value) => JSON.parse(JSON.stringify(value));
    const fromHtmlAttributes = () => {
        const config = clone(defaults);

        config.theme = html.getAttribute('data-bs-theme') || defaults.theme;
        config.layout.mode = html.getAttribute('data-layout-mode') || defaults.layout.mode;
        config.topbar.color = html.getAttribute('data-topbar-color') || defaults.topbar.color;
        config.sidenav.size = html.getAttribute('data-sidenav-size') || defaults.sidenav.size;
        config.menu.color = html.getAttribute('data-menu-color') || defaults.menu.color;

        return config;
    };

    let config = fromHtmlAttributes();
    const hasServerSettings = html.dataset.themeSettingsSource === 'server';

    window.defaultConfig = clone(config);

    if (!hasServerSettings) {
        const storedConfig = sessionStorage.getItem(storageKey);

        if (storedConfig) {
            try {
                config = Object.assign(clone(config), JSON.parse(storedConfig));
            } catch (error) {
                sessionStorage.removeItem(storageKey);
            }
        }
    }

    window.config = config;
    sessionStorage.setItem(storageKey, JSON.stringify(config));

    if (window.innerWidth <= 1140) {
        html.setAttribute('data-sidenav-size', 'full');
        html.setAttribute('data-layout-mode', 'default');
    } else {
        html.setAttribute('data-layout-mode', config.layout.mode);
        html.setAttribute('data-sidenav-size', config.sidenav.size);
    }

    html.setAttribute('data-bs-theme', config.theme);
    html.setAttribute('data-menu-color', config.menu.color);
    html.setAttribute('data-topbar-color', config.topbar.color);

    if (document.getElementById('app-style').href.includes('rtl.min.css')) {
        html.dir = 'rtl';
    }
})();