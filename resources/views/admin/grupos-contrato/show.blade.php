@extends('layouts.app')

@section('title', $grupoContrato->nome)
@section('page-title', 'Grupo de Contrato')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-1">{{ $grupoContrato->nome }}</h4>
                <p class="text-muted mb-0">{{ $grupoContrato->descricao ?: 'Sem descrição.' }}</p>
            </div>
            <a href="{{ route('admin.grupos-contrato.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="header-title mb-0">Membros por competência</h5>
                </div>
                @can('grupos-contrato.membros')
                    <div class="card-body border-bottom">
                        <form method="POST" action="{{ route('admin.grupos-contrato.membros.store', $grupoContrato) }}" class="row g-2">
                            @csrf
                            <div class="col-md-12">
                                <label for="cliente_id" class="form-label">Cliente</label>
                                <select id="cliente_id" name="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" required>
                                    <option value="">Selecione...</option>
                                    @foreach ($clientes as $cliente)
                                        <option value="{{ $cliente->id }}" @selected((string) old('cliente_id') === (string) $cliente->id)>
                                            {{ $cliente->razao_social }} @if($cliente->fantasia) ({{ $cliente->fantasia }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('cliente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="competencia_inicio" class="form-label">Competência inicial</label>
                                <input type="month" id="competencia_inicio" name="competencia_inicio" value="{{ old('competencia_inicio') }}" class="form-control @error('competencia_inicio') is-invalid @enderror" required>
                                @error('competencia_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="competencia_fim" class="form-label">Competência final</label>
                                <input type="month" id="competencia_fim" name="competencia_fim" value="{{ old('competencia_fim') }}" class="form-control @error('competencia_fim') is-invalid @enderror">
                                @error('competencia_fim')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">Vincular cliente</button>
                            </div>
                        </form>
                    </div>
                @endcan
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-centered mb-0">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($grupoContrato->membros as $membro)
                                    <tr>
                                        <td>{{ $membro->cliente?->razao_social ?? '—' }}</td>
                                        <td>{{ $membro->competencia_inicio }}</td>
                                        <td>{{ $membro->competencia_fim ?? 'Em aberto' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">Nenhum cliente vinculado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="header-title mb-0">Descontos mensais</h5>
                </div>
                @can('grupos-contrato.descontos')
                    <div class="card-body border-bottom">
                        <form method="POST" action="{{ route('admin.grupos-contrato.descontos.store', $grupoContrato) }}" class="row g-2">
                            @csrf
                            <div class="col-md-4">
                                <label for="competencia" class="form-label">Competência</label>
                                <input type="month" id="competencia" name="competencia" value="{{ old('competencia') }}" class="form-control @error('competencia') is-invalid @enderror" required>
                                @error('competencia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="valor" class="form-label">Valor R$</label>
                                <input type="number" step="0.01" min="0" id="valor" name="valor" value="{{ old('valor') }}" class="form-control @error('valor') is-invalid @enderror" required>
                                @error('valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="valor_teto" class="form-label">Teto R$</label>
                                <input type="number" step="0.01" min="0" id="valor_teto" name="valor_teto" value="{{ old('valor_teto') }}" class="form-control @error('valor_teto') is-invalid @enderror">
                                @error('valor_teto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="observacao" class="form-label">Observação</label>
                                <textarea id="observacao" name="observacao" rows="2" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao') }}</textarea>
                                @error('observacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">Lançar desconto</button>
                            </div>
                        </form>
                    </div>
                @endcan
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-centered mb-0">
                            <thead>
                                <tr>
                                    <th>Competência</th>
                                    <th>Valor</th>
                                    <th>Teto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($grupoContrato->descontos as $desconto)
                                    <tr>
                                        <td>{{ $desconto->competencia }}</td>
                                        <td>R$ {{ number_format((float) $desconto->valor, 2, ',', '.') }}</td>
                                        <td>{{ $desconto->valor_teto === null ? '—' : 'R$ '.number_format((float) $desconto->valor_teto, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">Nenhum desconto lançado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
