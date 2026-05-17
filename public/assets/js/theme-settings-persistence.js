(function () {
    const html = document.documentElement;
    const body = document.body;
    const wrapper = document.querySelector('.wrapper');
    const saveUrl = html.dataset.themeSettingsUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const persistedSettings = [
        'data-bs-theme',
        'data-layout-mode',
        'data-topbar-color',
        'data-menu-color',
        'data-sidenav-size',
    ];
    const protectedThemeAttributes = [
        'data-layout-mode',
        'data-bs-theme',
        'data-menu-color',
        'data-topbar-color',
        'data-sidenav-size',
        'data-layout-width',
    ];
    let resizeTimer = null;
    let mutationTimer = null;
    let currentSettings = window.currentThemeSettings || window.themeSettingsFromServer || persistedSettings.reduce((settings, name) => {
        const value = html.getAttribute(name);

        if (value) {
            settings[name] = value;
        }

        return settings;
    }, {});

    const syncConfig = (settings) => {
        if (!window.config) {
            return;
        }

        window.config.theme = settings['data-bs-theme'] ?? window.config.theme;
        window.config.layout = window.config.layout ?? {};
        window.config.layout.mode = settings['data-layout-mode'] ?? window.config.layout.mode;
        window.config.topbar = window.config.topbar ?? {};
        window.config.topbar.color = settings['data-topbar-color'] ?? window.config.topbar.color;
        window.config.menu = window.config.menu ?? {};
        window.config.menu.color = settings['data-menu-color'] ?? window.config.menu.color;
        window.config.sidenav = window.config.sidenav ?? {};
        window.config.sidenav.size = settings['data-sidenav-size'] ?? window.config.sidenav.size;
        sessionStorage.setItem('__HIGHDMIN_CONFIG__', JSON.stringify(window.config));
    };

    const syncClasses = (settings) => {
        const isDetached = settings['data-layout-mode'] === 'detached';

        body?.classList.toggle('layout-detached', isDetached);
        wrapper?.classList.toggle('layout-detached', isDetached);
    };

    function syncThemeControls(settings) {
        Object.entries(settings).forEach(([name, value]) => {
            document
                .querySelectorAll(`input[name="${name}"]`)
                .forEach((input) => {
                    const isSelected = input.value === String(value);

                    input.checked = isSelected;

                    const card = input.closest('.form-check, .card-radio, .theme-choice');

                    if (card) {
                        card.classList.toggle('active', isSelected);
                        card.classList.toggle('checked', isSelected);
                    }
                });
        });

        const selectedLayout = document.querySelector('input[name="data-layout-mode"]:checked');

        if (!selectedLayout && settings['data-layout-mode']) {
            const saved = document.querySelector(
                `input[name="data-layout-mode"][value="${settings['data-layout-mode']}"]`,
            );

            if (saved) {
                saved.checked = true;
            }
        }
    }

    const applyThemeSettings = (settings = currentSettings) => {
        currentSettings = {
            ...currentSettings,
            ...settings,
        };

        Object.entries(currentSettings).forEach(([name, value]) => {
            html.setAttribute(name, value);
        });

        syncConfig(currentSettings);
        syncClasses(currentSettings);
        syncThemeControls(currentSettings);
        window.currentThemeSettings = currentSettings;
    };
    const scheduleThemeReapply = (delay = 0) => {
        window.setTimeout(() => {
            window.requestAnimationFrame(() => applyThemeSettings(currentSettings));
        }, delay);
    };

    window.currentThemeSettings = currentSettings;
    window.themeSettingsFromServer = window.themeSettingsFromServer || currentSettings;
    window.protectedThemeAttributes = protectedThemeAttributes;
    window.applyThemeSettings = applyThemeSettings;
    window.syncThemeControls = syncThemeControls;
    applyThemeSettings();

    const observer = new MutationObserver(() => {
        window.clearTimeout(mutationTimer);

        mutationTimer = window.setTimeout(() => {
            const settings = window.currentThemeSettings || window.themeSettingsFromServer || currentSettings;

            applyThemeSettings(settings);
            syncThemeControls(settings);
        }, 50);
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });

    document.addEventListener('DOMContentLoaded', () => {
        applyThemeSettings();
        scheduleThemeReapply();
        scheduleThemeReapply(50);
    });

    window.addEventListener('load', () => {
        applyThemeSettings();
        scheduleThemeReapply();
        scheduleThemeReapply(100);
    });

    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        scheduleThemeReapply();
        scheduleThemeReapply(50);

        resizeTimer = window.setTimeout(() => {
            applyThemeSettings(window.currentThemeSettings || window.themeSettingsFromServer || currentSettings);
            syncThemeControls(window.currentThemeSettings || window.themeSettingsFromServer || currentSettings);
        }, 150);
    });

    document.getElementById('theme-settings-offcanvas')?.addEventListener('shown.bs.offcanvas', () => {
        applyThemeSettings(window.currentThemeSettings || window.themeSettingsFromServer || currentSettings);
        syncThemeControls(window.currentThemeSettings || window.themeSettingsFromServer || currentSettings);
    });

    if (!saveUrl || !csrfToken) {
        return;
    }

    const readSettings = () => persistedSettings.reduce((settings, name) => {
        const checkedInput = document.querySelector(`input[type="radio"][name="${name}"]:checked`);
        const value = checkedInput?.value ?? html.getAttribute(name);

        if (value) {
            settings[name] = value;
        }

        return settings;
    }, {});

    const saveSettings = (changedSettings = {}) => {
        currentSettings = {
            ...currentSettings,
            ...readSettings(),
            ...changedSettings,
        };

        applyThemeSettings(currentSettings);
        window.currentThemeSettings = currentSettings;

        fetch(saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(currentSettings),
        }).then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (payload?.theme_settings) {
                    applyThemeSettings(payload.theme_settings);
                    window.currentThemeSettings = currentSettings;
                    window.themeSettingsFromServer = currentSettings;
                }
            })
            .catch(() => {
                // A failed preference save should never block navigation or theme usage.
            });
    };

    persistedSettings.forEach((name) => {
        document.querySelectorAll(`input[type="radio"][name="${name}"]`).forEach((input) => {
            input.addEventListener('change', (event) => {
                if (!event.isTrusted) {
                    return;
                }

                window.setTimeout(() => saveSettings({ [name]: input.value }), 0);
            });
        });
    });

    document.getElementById('reset-layout')?.addEventListener('click', () => {
        window.setTimeout(saveSettings, 0);
    });
})();
