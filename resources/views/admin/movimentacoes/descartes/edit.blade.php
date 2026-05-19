@extends('layouts.app')

@section('title', 'Editar descarte #' . $movimentacao->id)
@section('page-title', 'Movimentação — Editar descarte')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Editar descarte #{{ $movimentacao->id }}</h4>
            <a href="{{ route('admin.movimentacoes.descartes.show', $movimentacao) }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.movimentacoes.descartes.update', $movimentacao) }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-4">
                    <label for="qtd_fruta_um" class="form-label">Quantidade (UM) <span class="text-danger">*</span></label>
                    <input type="text" name="qtd_fruta_um" id="qtd_fruta_um" value="{{ old('qtd_fruta_um', $movimentacao->qtd_fruta_um) }}"
                           class="form-control @error('qtd_fruta_um') is-invalid @enderror js-decimal-br" required>
                    @error('qtd_fruta_um')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-8">
                    <label for="categoria_descarte_id" class="form-label">Categoria de descarte <span class="text-danger">*</span></label>
                    <select name="categoria_descarte_id" id="categoria_descarte_id" class="form-select @error('categoria_descarte_id') is-invalid @enderror" data-search-select data-placeholder="Selecione ou pesquise a categoria" required>
                        <option value="">Selecione…</option>
                        @foreach ($opcoes['categorias_descarte'] as $categoria)
                            <option value="{{ $categoria->id }}" @selected(old('categoria_descarte_id', $movimentacao->categoria_descarte_id) == $categoria->id)>{{ $categoria->nome }}</option>
                        @endforeach
                    </select>
                    @error('categoria_descarte_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="motivo_descarte" class="form-label">Motivo do descarte</label>
                    <textarea name="motivo_descarte" id="motivo_descarte" rows="3" class="form-control @error('motivo_descarte') is-invalid @enderror">{{ old('motivo_descarte', $movimentacao->motivo_descarte) }}</textarea>
                    @error('motivo_descarte')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
    @include('partials.admin.movimentacoes-form-scripts')
@endpush
