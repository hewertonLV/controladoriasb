@props([
    'carteiras',
    'dataReferencia' => null,
    'semCard' => false,
])

@if (! $semCard)
<div class="card mb-3">
    <div class="card-header pb-0"><strong>Abrir captação do dia</strong></div>
    <div class="card-body pt-2">
@else
<div class="mb-0">
@endif
        <p class="text-muted small mb-2">
            Só não é possível abrir outra captação se já existir uma <strong>em andamento</strong> na mesma carteira e data.
            Em qualquer outro status do lote anterior, você pode criar uma nova (complementar).
        </p>
        <form method="post" action="{{ route('admin.captacao.lotes.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2">
                <label class="form-label">Data</label>
                <input type="date"
                       name="data_referencia"
                       class="form-control"
                       value="{{ old('data_referencia', $dataReferencia ?? now()->toDateString()) }}"
                       required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Carteira</label>
                <select name="id_captacao_carteira"
                        class="form-select"
                        data-search-select
                        data-placeholder="Selecione ou pesquise a carteira"
                        required>
                    <option value="">Selecione a carteira…</option>
                    @foreach ($carteiras as $carteira)
                        <option value="{{ $carteira->id }}" @selected((int) old('id_captacao_carteira') === $carteira->id)>
                            {{ $carteira->nome }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">Criar captação</button>
            </div>
        </form>
@if (! $semCard)
    </div>
</div>
@else
</div>
@endif
