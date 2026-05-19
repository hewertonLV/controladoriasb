(function () {
    'use strict';

    function toggleButtonState(button, visible) {
        const showIcon = button.querySelector('[data-icon-show]');
        const hideIcon = button.querySelector('[data-icon-hide]');

        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
        button.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');

        if (showIcon) {
            showIcon.classList.toggle('d-none', visible);
        }
        if (hideIcon) {
            hideIcon.classList.toggle('d-none', !visible);
        }
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-password-toggle]');
        if (!button) {
            return;
        }

        const selector = button.getAttribute('data-target');
        if (!selector) {
            return;
        }

        const input = document.querySelector(selector);
        if (!input) {
            return;
        }

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggleButtonState(button, show);
    });
})();
