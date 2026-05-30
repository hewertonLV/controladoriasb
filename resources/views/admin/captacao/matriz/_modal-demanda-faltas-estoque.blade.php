<div class="modal fade"
     id="modal-demanda-faltas-estoque"
     tabindex="-1"
     aria-labelledby="modal-demanda-faltas-estoque-titulo"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2 px-3 border-0 pb-0">
                <div class="pe-2">
                    <h5 class="modal-title text-danger mb-0 fs-6" id="modal-demanda-faltas-estoque-titulo">
                        <i class="ri-error-warning-line me-1"></i> Estoque insuficiente
                    </h5>
                    <p class="text-muted mb-0 mt-1" style="font-size: 0.8rem;" id="modal-demanda-faltas-subtitulo">
                        Saldo insuficiente para concluir a demanda.
                    </p>
                </div>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body py-2 px-3">
                <div class="table-responsive" style="max-height: 12rem;">
                    <table class="table table-sm table-bordered align-middle mb-0 small">
                        <thead class="table-light">
                        <tr class="text-nowrap">
                            <th class="py-1 px-2">Fruta</th>
                            <th class="text-end py-1 px-2">Disponível</th>
                            <th class="text-end py-1 px-2">Falta</th>
                        </tr>
                        </thead>
                        <tbody id="modal-demanda-faltas-corpo"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 border-0 pt-0">
                <button type="button" class="btn btn-primary btn-sm w-100" data-bs-dismiss="modal">Entendi</button>
            </div>
        </div>
    </div>
</div>
