(function () {
    'use strict';

    const submittingAttribute = 'data-submit-guard-submitting';
    const markerAttribute = 'data-submit-guard-managed';
    const disabledBeforeAttribute = 'data-submit-guard-was-disabled';
    const ignoredSelector = '[data-submit-guard="off"]';
    const fallbackMs = 30000;
    const fallbackTimers = new WeakMap();
    let pageIsUnloading = false;

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

            button.setAttribute(markerAttribute, 'true');
            button.setAttribute(disabledBeforeAttribute, button.disabled ? 'true' : 'false');
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            button.classList.add('disabled');
        });

        const timer = window.setTimeout(function () {
            if (!pageIsUnloading && form.getAttribute(submittingAttribute) === 'true') {
                resetSubmittingForm(form);
                window.dispatchEvent(new CustomEvent('submit-guard:timeout', { detail: { form } }));
            }
        }, fallbackMs);

        fallbackTimers.set(form, timer);
    }

    function resetSubmittingForm(form) {
        const timer = fallbackTimers.get(form);

        if (timer) {
            window.clearTimeout(timer);
            fallbackTimers.delete(form);
        }

        form.removeAttribute(submittingAttribute);
        form.removeAttribute('aria-busy');

        submitButtons(form).forEach((button) => {
            if (button.getAttribute(markerAttribute) !== 'true') {
                return;
            }

            if (button.getAttribute(disabledBeforeAttribute) !== 'true') {
                button.disabled = false;
                button.removeAttribute('aria-disabled');
                button.classList.remove('disabled');
            }

            button.removeAttribute(markerAttribute);
            button.removeAttribute(disabledBeforeAttribute);
        });
    }

    function resetAllSubmittingForms() {
        document
            .querySelectorAll(`form[${submittingAttribute}="true"]`)
            .forEach(resetSubmittingForm);
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

    window.addEventListener('beforeunload', function () {
        pageIsUnloading = true;
    });

    window.addEventListener('pageshow', function () {
        pageIsUnloading = false;
        resetAllSubmittingForms();
    });

    document.addEventListener('submit-guard:reset', resetAllSubmittingForms);

    window.submitGuardReset = resetAllSubmittingForms;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(registerGuard, 0);
        });
    } else {
        setTimeout(registerGuard, 0);
    }
})();
