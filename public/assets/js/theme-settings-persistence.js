(function () {
    const html = document.documentElement;
    const saveUrl = html.dataset.themeSettingsUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const persistedSettings = [
        'data-bs-theme',
        'data-layout-mode',
        'data-topbar-color',
        'data-menu-color',
        'data-sidenav-size',
    ];

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

    const saveSettings = () => {
        fetch(saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(readSettings()),
        }).catch(() => {
            // A failed preference save should never block navigation or theme usage.
        });
    };

    persistedSettings.forEach((name) => {
        document.querySelectorAll(`input[type="radio"][name="${name}"]`).forEach((input) => {
            input.addEventListener('change', () => {
                window.setTimeout(saveSettings, 0);
            });
        });
    });

    document.getElementById('reset-layout')?.addEventListener('click', () => {
        window.setTimeout(saveSettings, 0);
    });
})();
