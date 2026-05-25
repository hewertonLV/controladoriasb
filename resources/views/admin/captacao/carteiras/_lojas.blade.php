@php
    $lojasVinculadas = $lojasVinculadas ?? collect();
    $lojasSemCarteira = $lojasSemCarteira ?? collect();
    $idsSelecionados = collect(old('id_clientes', $lojasVinculadas->pluck('id')->all()))->map(fn ($id) => (int) $id);
@endphp

<div class="card mt-4">
    <div class="card-header">
        <strong>Lojas da carteira</strong>
        <span class="text-muted small ms-2">
            Faturamento: {{ $carteira->unidadeFaturamento?->nome ?? '—' }}
        </span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Marque as lojas que pertencem a esta carteira. Só aparecem lojas <strong>sem vínculo</strong> com outra carteira
            e da mesma unidade de faturamento. Ao salvar, a unidade de negócio da loja é alinhada ao faturamento da carteira.
        </p>

        @error('id_clientes')
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror

        <div class="mb-3">
            <input type="search" id="filtro-lojas-carteira" class="form-control form-control-sm"
                   style="max-width: 320px;" placeholder="Buscar loja…">
        </div>

        @if ($lojasVinculadas->isEmpty() && $lojasSemCarteira->isEmpty())
            <p class="text-muted mb-0">
                Nenhuma loja vinculada e nenhuma loja disponível sem carteira neste faturamento.
                @can('clientes.criar')
                    <a href="{{ route('admin.clientes.create') }}">Cadastrar cliente</a>
                @endcan
            </p>
        @else
            <div class="row g-3" id="lista-lojas-carteira">
                @if ($lojasVinculadas->isNotEmpty())
                    <div class="col-12">
                        <h6 class="text-success mb-2">
                            <i class="ri-store-2-line me-1"></i>
                            Nesta carteira ({{ $lojasVinculadas->count() }})
                        </h6>
                        <div class="row g-2">
                            @foreach ($lojasVinculadas as $cliente)
                                <div class="col-md-6 col-lg-4 loja-item"
                                     data-nome="{{ mb_strtolower(($cliente->fantasia ?: $cliente->razao_social)) }}">
                                    <div class="form-check border rounded px-2 py-1">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="id_clientes[]"
                                               value="{{ $cliente->id }}"
                                               id="loja_carteira_{{ $cliente->id }}"
                                               @checked($idsSelecionados->contains($cliente->id))>
                                        <label class="form-check-label" for="loja_carteira_{{ $cliente->id }}">
                                            {{ $cliente->fantasia ?: $cliente->razao_social }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($lojasSemCarteira->isNotEmpty())
                    <div class="col-12">
                        <h6 class="text-primary mb-2">
                            <i class="ri-add-circle-line me-1"></i>
                            Sem carteira — disponíveis para vincular ({{ $lojasSemCarteira->count() }})
                        </h6>
                        <div class="row g-2">
                            @foreach ($lojasSemCarteira as $cliente)
                                <div class="col-md-6 col-lg-4 loja-item"
                                     data-nome="{{ mb_strtolower(($cliente->fantasia ?: $cliente->razao_social)) }}">
                                    <div class="form-check border rounded px-2 py-1 bg-light bg-opacity-50">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="id_clientes[]"
                                               value="{{ $cliente->id }}"
                                               id="loja_carteira_{{ $cliente->id }}"
                                               @checked($idsSelecionados->contains($cliente->id))>
                                        <label class="form-check-label" for="loja_carteira_{{ $cliente->id }}">
                                            {{ $cliente->fantasia ?: $cliente->razao_social }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-sm btn-light" id="btn-marcar-todas-lojas">Marcar todas visíveis</button>
                <button type="button" class="btn btn-sm btn-light" id="btn-desmarcar-todas-lojas">Desmarcar todas visíveis</button>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function () {
    const filtro = document.getElementById('filtro-lojas-carteira');
    const itens = document.querySelectorAll('.loja-item');
    if (!filtro || itens.length === 0) return;

    filtro.addEventListener('input', () => {
        const termo = filtro.value.trim().toLowerCase();
        itens.forEach((el) => {
            const nome = el.dataset.nome || '';
            el.classList.toggle('d-none', termo !== '' && !nome.includes(termo));
        });
    });

    function forEachVisivel(cb) {
        itens.forEach((el) => {
            if (!el.classList.contains('d-none')) {
                cb(el.querySelector('input[type=checkbox]'));
            }
        });
    }

    document.getElementById('btn-marcar-todas-lojas')?.addEventListener('click', () => {
        forEachVisivel((input) => { if (input) input.checked = true; });
    });
    document.getElementById('btn-desmarcar-todas-lojas')?.addEventListener('click', () => {
        forEachVisivel((input) => { if (input) input.checked = false; });
    });
})();
</script>
@endpush
