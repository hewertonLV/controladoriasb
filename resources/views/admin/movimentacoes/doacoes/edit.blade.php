@extends('layouts.app')

@section('title', 'Editar doação #' . $movimentacao->id)
@section('page-title', 'Movimentação — Editar doação')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Editar doação #{{ $movimentacao->id }}</h4>
            <a href="{{ route('admin.movimentacoes.doacoes.show', $movimentacao) }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.movimentacoes.doacoes.update', $movimentacao) }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-4">
                    <label for="qtd_fruta_um" class="form-label">Quantidade (UM) <span class="text-danger">*</span></label>
                    <input type="text" name="qtd_fruta_um" id="qtd_fruta_um" value="{{ old('qtd_fruta_um', $movimentacao->qtd_fruta_um) }}"
                           class="form-control @error('qtd_fruta_um') is-invalid @enderror js-decimal-br" required>
                    @error('qtd_fruta_um')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-8">
                    <label for="id_empresa_destino" class="form-label">Cliente destino (opcional)</label>
                    <select name="id_empresa_destino" id="id_empresa_destino" class="form-select @error('id_empresa_destino') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach ($opcoes['empresas_destino_cliente'] as $e)
                            <option value="{{ $e->id }}" @selected(old('id_empresa_destino', $movimentacao->id_empresa_destino) == $e->id)>{{ $e->nomeExibicao() }}</option>
                        @endforeach
                    </select>
                    @error('id_empresa_destino')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="motivo_doacao" class="form-label">Motivo da doação <span class="text-danger">*</span></label>
                    <input type="text" name="motivo_doacao" id="motivo_doacao" value="{{ old('motivo_doacao', $movimentacao->motivo_doacao) }}" maxlength="255"
                           class="form-control @error('motivo_doacao') is-invalid @enderror" required>
                    @error('motivo_doacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="numero_nf_origem" class="form-label">Número NF origem</label>
                    <input type="text" name="numero_nf_origem" id="numero_nf_origem" value="{{ old('numero_nf_origem', $movimentacao->numero_nf_origem) }}" maxlength="120"
                           class="form-control @error('numero_nf_origem') is-invalid @enderror">
                    @error('numero_nf_origem')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="observacao" class="form-label">Observação</label>
                    <textarea name="observacao" id="observacao" rows="3" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao', $movimentacao->observacao) }}</textarea>
                    @error('observacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="motivo_substituicao" class="form-label">Motivo da substituição (versão)</label>
                    <textarea name="motivo_substituicao" id="motivo_substituicao" rows="2" class="form-control @error('motivo_substituicao') is-invalid @enderror">{{ old('motivo_substituicao') }}</textarea>
                    @error('motivo_substituicao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Salvar nova versão</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.movimentacoes.compras._masks')
@endpush
