@extends('layouts.app')

@section('title', 'Movimentar estoque')
@section('page-title', 'Movimentar estoque')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Entrada ou saída de estoque</h4>
            <a href="{{ route('admin.estoques.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            @error('movimentacao')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('admin.estoques.movimentar.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label for="id_unidade_negocio" class="form-label">Unidade de negócio <span class="text-danger">*</span></label>
                    <select name="id_unidade_negocio" id="id_unidade_negocio" class="form-select @error('id_unidade_negocio') is-invalid @enderror" required>
                        <option value="">Selecione…</option>
                        @foreach ($unidades as $u)
                            <option value="{{ $u->id }}" @selected((string) old('id_unidade_negocio', $idUnidadeSelecionada) === (string) $u->id)>
                                {{ $u->nome }} ({{ $u->id_cigam }})
                            </option>
                        @endforeach
                    </select>
                    @error('id_unidade_negocio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="id_fruta" class="form-label">Fruta <span class="text-danger">*</span></label>
                    <select name="id_fruta" id="id_fruta" class="form-select @error('id_fruta') is-invalid @enderror" required>
                        <option value="">Selecione…</option>
                        @foreach ($frutas as $f)
                            <option value="{{ $f->id }}" @selected((string) old('id_fruta', $idFrutaSelecionada) === (string) $f->id)>
                                {{ $f->nome }} ({{ $f->id_cigam }}) — {{ $f->unidade_medicao }}
                            </option>
                        @endforeach
                    </select>
                    @error('id_fruta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select name="tipo" id="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                        <option value="entrada" @selected(old('tipo', 'entrada') === 'entrada')>Entrada</option>
                        <option value="saida" @selected(old('tipo') === 'saida')>Saída</option>
                    </select>
                    @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="quantidade_kg" class="form-label">Quantidade (kg) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="quantidade_kg" id="quantidade_kg"
                           value="{{ old('quantidade_kg') }}"
                           class="form-control @error('quantidade_kg') is-invalid @enderror" required>
                    @error('quantidade_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="preco_medio_kg" class="form-label">Preço médio (kg) — entradas</label>
                    <input type="number" step="0.01" min="0" name="preco_medio_kg" id="preco_medio_kg"
                           value="{{ old('preco_medio_kg') }}"
                           class="form-control @error('preco_medio_kg') is-invalid @enderror">
                    @error('preco_medio_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Obrigatório para entrada; ignorado na saída.</small>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-check-line me-1"></i> Registrar movimentação
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
