<div class="modal fade"
     id="avatar-crop-modal"
     tabindex="-1"
     aria-labelledby="avatar-crop-modal-label"
     aria-hidden="true"
     data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="avatar-crop-modal-label">Recortar foto de perfil</h5>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Fechar"
                        id="avatar-crop-close"></button>
            </div>
            <div class="modal-body p-2">
                <p class="text-muted small px-2 mb-2">
                    Arraste e ajuste o enquadramento. A imagem será recortada em quadrado e comprimida automaticamente antes do envio.
                </p>
                <div class="bg-light rounded overflow-hidden" style="max-height: min(70vh, 480px);">
                    <img id="avatar-crop-image"
                         src=""
                         alt="Pré-visualização para recorte"
                         class="d-block w-100"
                         style="max-height: min(70vh, 480px);">
                </div>
            </div>
            <div class="modal-footer flex-wrap">
                <small id="avatar-crop-status" class="text-muted me-auto w-100 w-md-auto mb-2 mb-md-0"></small>
                <button type="button" class="btn btn-light" id="avatar-crop-cancel" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="avatar-crop-apply">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="avatar-crop-spinner" role="status" aria-hidden="true"></span>
                    Aplicar corte
                </button>
            </div>
        </div>
    </div>
</div>
