@extends('layouts.app')

@section('title', 'Vincular frete')
@section('page-title', 'Frete do lote #'.$lote->id)

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-0">Transferências geradas na validação. Frete é <strong>opcional</strong> por linha.</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Transferências do lote</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>Fruta</th>
                    <th>Qtd UM</th>
                    <th>Frete ABERTO</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($transferencias as $row)
                    <tr>
                        <td>{{ $row['vinculo']->fruta?->nome }}</td>
                        <td>{{ $row['saida']?->qtd_fruta_um ?? '—' }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.captacao.lotes.fretes.transferencia', $lote) }}" class="d-flex gap-2">
                                @csrf
                                <input type="hidden" name="transferencia_origem_id" value="{{ $row['vinculo']->transferencia_origem_id }}">
                                <select name="id_frete"
                                        class="form-select form-select-sm"
                                        data-search-select
                                        data-placeholder="Selecione ou pesquise o frete">
                                    <option value="">Sem frete</option>
                                    @foreach ($fretesAbertos as $frete)
                                        <option value="{{ $frete->id }}" @selected((int) $row['id_frete_atual'] === $frete->id)>
                                            {{ $frete->nome }} (R$ {{ $frete->valor }})
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                            </form>
                        </td>
                        <td></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Nenhuma transferência vinculada a este lote.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Frete por fruta (vendas — Jefferson)</strong></div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.captacao.lotes.fretes.fruta-venda', $lote) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Fruta</label>
                    <select name="id_fruta"
                            class="form-select"
                            data-search-select
                            data-placeholder="Selecione ou pesquise a fruta"
                            required>
                        @foreach ($transferencias->pluck('vinculo.fruta')->filter()->unique('id') as $fruta)
                            <option value="{{ $fruta->id }}">{{ $fruta->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Frete</label>
                    <select name="id_frete"
                            class="form-select"
                            data-search-select
                            data-placeholder="Selecione ou pesquise o frete">
                        <option value="">Sem frete</option>
                        @foreach ($fretesAbertos as $frete)
                            <option value="{{ $frete->id }}">{{ $frete->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary w-100">Vincular à venda</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@include('admin.captacao._search-select-scripts')
