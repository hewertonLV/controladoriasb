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
    const fromThemeSettings = (settings) => {
        const config = clone(defaults);

        config.theme = settings['data-bs-theme'] || defaults.theme;
        config.layout.mode = settings['data-layout-mode'] || defaults.layout.mode;
        config.topbar.color = settings['data-topbar-color'] || defaults.topbar.color;
        config.sidenav.size = settings['data-sidenav-size'] || defaults.sidenav.size;
        config.menu.color = settings['data-menu-color'] || defaults.menu.color;

        return config;
    };
    const fromHtmlAttributes = () => {
        const config = clone(defaults);

        config.theme = html.getAttribute('data-bs-theme') || defaults.theme;
        config.layout.mode = html.getAttribute('data-layout-mode') || defaults.layout.mode;
        config.topbar.color = html.getAttribute('data-topbar-color') || defaults.topbar.color;
        config.sidenav.size = html.getAttribute('data-sidenav-size') || defaults.sidenav.size;
        config.menu.color = html.getAttribute('data-menu-color') || defaults.menu.color;

        return config;
    };

    let config = window.themeSettingsFromServer
        ? fromThemeSettings(window.themeSettingsFromServer)
        : fromHtmlAttributes();
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

    if (document.getElementById('app-style').href.includes('rtl.min.css')) {
        html.dir = 'rtl';
    }
})();