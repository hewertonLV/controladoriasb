(function () {
    'use strict';

    const submittingAttribute = 'data-submit-guard-submitting';
    const ignoredSelector = '[data-submit-guard="off"]';

    function submitButtons(form) {
        const buttons = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
        const formId = form.getAttribute('id');

        if (formId) {
            buttons.push(
                ...Array.from(document.querySelectorAll(`button[type="submit"][form="${CSS.escape(formId)}"], input[type="submit"][form="${CSS.escape(formId)}"]`)),
            );
        }

        return Array.from(new Set(buttons));
    }

    function markSubmitting(form) {
        form.setAttribute(submittingAttribute, 'true');
        form.setAttribute('aria-busy', 'true');

        submitButtons(form).forEach((button) => {
            if (button.matches(ignoredSelector)) {
                return;
            }

            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            button.classList.add('disabled');
        });
    }

    function registerGuard() {
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.matches(ignoredSelector)) {
                return;
            }

            if (form.getAttribute(submittingAttribute) === 'true') {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.matches(ignoredSelector) || event.defaultPrevented) {
                return;
            }

            markSubmitting(form);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(registerGuard, 0);
        });
    } else {
        setTimeout(registerGuard, 0);
    }
})();
