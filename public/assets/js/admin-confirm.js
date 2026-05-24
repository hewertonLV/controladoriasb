/**
 * Confirmação temática (Bootstrap modal — colored header) para formulários e botões.
 *
 * Atributos:
 * - data-confirm (obrigatório): mensagem do corpo
 * - data-confirm-title: título do modal (padrão "Confirmar ação")
 * - data-confirm-variant: danger | success | warning | primary (padrão danger)
 * - data-confirm-btn: rótulo do botão confirmar (padrão "Confirmar")
 * - data-confirm-cancel: rótulo do botão cancelar (padrão "Cancelar")
 * - data-confirm-prompt: exibe campo de texto no modal (ex.: motivo)
 * - data-confirm-prompt-label: rótulo do campo (padrão "Motivo")
 * - data-confirm-prompt-field: name do input hidden no formulário que recebe o valor
 */
(function (window) {
    'use strict';

    const MODAL_ID = 'adminConfirmModal';
    const APPROVED_FLAG = 'data-confirm-approved';

    const VARIANTS = {
        danger: {
            headerClass: 'bg-danger text-white',
            closeClass: 'btn-close-white',
            btnClass: 'btn-danger',
            icon: 'ri-error-warning-line',
        },
        success: {
            headerClass: 'bg-success text-white',
            closeClass: 'btn-close-white',
            btnClass: 'btn-success',
            icon: 'ri-checkbox-circle-line',
        },
        warning: {
            headerClass: 'bg-warning text-dark',
            closeClass: '',
            btnClass: 'btn-warning',
            icon: 'ri-alert-line',
        },
        primary: {
            headerClass: 'bg-primary text-white',
            closeClass: 'btn-close-white',
            btnClass: 'btn-primary',
            icon: 'ri-question-line',
        },
    };

    let pendingForm = null;
    let pendingTrigger = null;
    let modalInstance = null;
    let modalMode = 'confirm';
    let actionsBound = false;

    function resolveBootstrapModal() {
        const bs = globalThis.bootstrap ?? window.bootstrap;

        return bs?.Modal ?? null;
    }

    function getModalElements() {
        const modalEl = document.getElementById(MODAL_ID);
        if (!modalEl) {
            return null;
        }

        return {
            modalEl,
            header: document.getElementById('adminConfirmModalHeader'),
            titleText: document.getElementById('adminConfirmModalTitleText'),
            icon: document.getElementById('adminConfirmModalIcon'),
            body: document.getElementById('adminConfirmModalBody'),
            closeBtn: document.getElementById('adminConfirmModalCloseBtn'),
            cancelBtn: document.getElementById('adminConfirmModalCancelBtn'),
            confirmBtn: document.getElementById('adminConfirmModalConfirmBtn'),
            promptWrap: document.getElementById('adminConfirmModalPromptWrap'),
            promptLabel: document.getElementById('adminConfirmModalPromptLabel'),
            promptInput: document.getElementById('adminConfirmModalPromptInput'),
            promptError: document.getElementById('adminConfirmModalPromptError'),
        };
    }

    function resolveTrigger(element) {
        if (!element) {
            return null;
        }

        if (element.matches('form[data-confirm]')) {
            return element;
        }

        if (element.matches('[data-confirm]')) {
            return element;
        }

        const form = element.closest('form[data-confirm]');
        if (form) {
            return form;
        }

        return element.closest('[data-confirm]');
    }

    function readOptions(trigger) {
        const variantKey = (trigger.dataset.confirmVariant || 'danger').toLowerCase();
        const variant = VARIANTS[variantKey] || VARIANTS.danger;

        return {
            message: trigger.dataset.confirm || 'Deseja continuar?',
            title: trigger.dataset.confirmTitle || 'Confirmar ação',
            confirmLabel: trigger.dataset.confirmBtn || 'Confirmar',
            cancelLabel: trigger.dataset.confirmCancel || 'Cancelar',
            prompt: trigger.dataset.confirmPrompt || '',
            promptLabel: trigger.dataset.confirmPromptLabel || 'Motivo',
            promptField: trigger.dataset.confirmPromptField || '',
            variant,
        };
    }

    function applyVariant(elements, variant) {
        const headerBase = 'modal-header';
        elements.header.className = `${headerBase} ${variant.headerClass}`;

        elements.closeBtn.className = 'btn-close';
        if (variant.closeClass) {
            elements.closeBtn.classList.add(variant.closeClass);
        }

        elements.icon.className = variant.icon;
        elements.confirmBtn.className = `btn ${variant.btnClass}`;
    }

    function restoreModalFooter(elements) {
        elements.cancelBtn.classList.remove('d-none');
        elements.confirmBtn.textContent = 'Confirmar';
        elements.cancelBtn.textContent = 'Cancelar';
        modalMode = 'confirm';
    }

    function ensureActionsBound() {
        if (actionsBound) {
            return;
        }

        bindModalActions();
        actionsBound = true;
    }

    function getOrCreateModalInstance(modalEl) {
        const Modal = resolveBootstrapModal();
        if (!Modal) {
            return null;
        }

        return Modal.getOrCreateInstance(modalEl);
    }

    function openConfirm(trigger) {
        ensureActionsBound();

        const elements = getModalElements();
        const Modal = resolveBootstrapModal();
        if (!elements || !Modal) {
            return false;
        }

        modalMode = 'confirm';
        const options = readOptions(trigger);
        applyVariant(elements, options.variant);

        elements.titleText.textContent = options.title;
        elements.body.textContent = options.message;
        elements.confirmBtn.textContent = options.confirmLabel;
        elements.cancelBtn.textContent = options.cancelLabel;
        elements.cancelBtn.classList.remove('d-none');

        pendingTrigger = trigger;
        pendingForm = trigger.tagName === 'FORM' ? trigger : trigger.closest('form');

        if (options.prompt) {
            elements.promptWrap.classList.remove('d-none');
            elements.promptLabel.textContent = options.promptLabel;
            elements.promptInput.value = '';
            elements.promptInput.classList.remove('is-invalid');
            elements.promptInput.placeholder = options.prompt;
        } else {
            elements.promptWrap.classList.add('d-none');
            elements.promptInput.value = '';
            elements.promptInput.classList.remove('is-invalid');
        }

        modalInstance = getOrCreateModalInstance(elements.modalEl);
        modalInstance?.show();

        return true;
    }

    /**
     * Modal informativo (somente OK) — mesmo template visual do confirm.
     *
     * @param {{ title?: string, message: string, variant?: string, confirmLabel?: string }} options
     */
    function openAlert(options) {
        ensureActionsBound();

        const elements = getModalElements();
        const Modal = resolveBootstrapModal();
        if (!elements || !Modal) {
            console.error('[AdminConfirm] Modal ou Bootstrap indisponível.', options);

            return false;
        }

        modalMode = 'alert';
        pendingForm = null;
        pendingTrigger = null;

        const variantKey = (options.variant || 'warning').toLowerCase();
        const variant = VARIANTS[variantKey] || VARIANTS.warning;
        applyVariant(elements, variant);

        elements.titleText.textContent = options.title || 'Atenção';
        elements.body.textContent = options.message || '';
        elements.confirmBtn.textContent = options.confirmLabel || 'Entendi';
        elements.cancelBtn.classList.add('d-none');
        elements.promptWrap.classList.add('d-none');
        elements.promptInput.classList.remove('is-invalid');

        modalInstance = getOrCreateModalInstance(elements.modalEl);
        modalInstance?.show();

        return true;
    }

    function validatePrompt(elements) {
        const trigger = pendingTrigger;
        if (!trigger?.dataset.confirmPrompt) {
            return true;
        }

        const value = (elements.promptInput.value || '').trim();
        if (value === '') {
            elements.promptInput.classList.add('is-invalid');
            elements.promptInput.focus();
            return false;
        }

        elements.promptInput.classList.remove('is-invalid');

        if (pendingForm && trigger.dataset.confirmPromptField) {
            const target = pendingForm.querySelector(`[name="${trigger.dataset.confirmPromptField}"]`);
            if (target) {
                target.value = value;
            }
        }

        return true;
    }

    function submitPendingForm() {
        if (!pendingForm) {
            return;
        }

        const elements = getModalElements();
        if (elements && !validatePrompt(elements)) {
            return;
        }

        const form = pendingForm;
        pendingForm = null;
        pendingTrigger = null;

        form.setAttribute(APPROVED_FLAG, '1');

        if (typeof form.requestSubmit === 'function') {
            const submitter = form.querySelector('[type="submit"]');
            form.requestSubmit(submitter || undefined);
        } else {
            form.submit();
        }

        window.setTimeout(() => form.removeAttribute(APPROVED_FLAG), 0);
    }

    function bindModalActions() {
        const elements = getModalElements();
        if (!elements) {
            return;
        }

        elements.confirmBtn.addEventListener('click', () => {
            if (modalMode === 'alert') {
                modalInstance?.hide();

                return;
            }

            modalInstance?.hide();
            submitPendingForm();
        });

        elements.modalEl.addEventListener('hidden.bs.modal', () => {
            pendingForm = null;
            pendingTrigger = null;
            elements.promptWrap.classList.add('d-none');
            elements.promptInput.classList.remove('is-invalid');
            restoreModalFooter(elements);
        });
    }

    function shouldInterceptSubmit(form) {
        return form?.hasAttribute('data-confirm') && form.getAttribute(APPROVED_FLAG) !== '1';
    }

    function bindDocumentHandlers() {
        document.addEventListener(
            'submit',
            (event) => {
                const form = event.target instanceof HTMLFormElement ? event.target : null;
                if (!shouldInterceptSubmit(form)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                openConfirm(form);
            },
            true,
        );

        document.addEventListener('click', (event) => {
            const button = event.target.closest('button[type="submit"][data-confirm], input[type="submit"][data-confirm]');
            if (!button) {
                return;
            }

            const form = button.closest('form');
            if (!form || form.hasAttribute('data-confirm')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            pendingForm = form;
            openConfirm(button);
        });
    }

    function showSessionErrorModal() {
        const el = document.getElementById('admin-flash-modal-error');
        const mensagem = (el?.textContent || '').trim();
        if (mensagem === '') {
            return;
        }

        openAlert({
            title: 'Atenção',
            message: mensagem,
            variant: 'warning',
            confirmLabel: 'Entendi',
        });
    }

    function bootstrapConfirm() {
        ensureActionsBound();
        bindDocumentHandlers();
        showSessionErrorModal();
    }

    window.AdminConfirm = {
        open: openConfirm,
        alert: openAlert,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapConfirm);
    } else {
        bootstrapConfirm();
    }
})(window);
