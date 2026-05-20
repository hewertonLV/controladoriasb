{{-- Modal única de confirmação (Danger / Success / Warning / Primary header). Controlada por admin-confirm.js --}}
<div class="modal fade"
     id="adminConfirmModal"
     tabindex="-1"
     aria-labelledby="adminConfirmModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="adminConfirmModalHeader">
                <h5 class="modal-title d-flex align-items-center gap-2" id="adminConfirmModalLabel">
                    <i class="ri-error-warning-line" id="adminConfirmModalIcon" aria-hidden="true"></i>
                    <span id="adminConfirmModalTitleText">Confirmar ação</span>
                </h5>
                <button type="button"
                        class="btn-close"
                        id="adminConfirmModalCloseBtn"
                        data-bs-dismiss="modal"
                        aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="adminConfirmModalBody"></p>
                <div id="adminConfirmModalPromptWrap" class="mt-3 d-none">
                    <label for="adminConfirmModalPromptInput" class="form-label" id="adminConfirmModalPromptLabel">Motivo</label>
                    <textarea id="adminConfirmModalPromptInput" class="form-control" rows="2"></textarea>
                    <div class="invalid-feedback d-block" id="adminConfirmModalPromptError">Informe o motivo para continuar.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                        id="adminConfirmModalCancelBtn">
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger" id="adminConfirmModalConfirmBtn">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
