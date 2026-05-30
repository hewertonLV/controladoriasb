@extends('layouts.app')

@section('title', 'Nova demanda de transferência')
@section('page-title', 'Movimentação — Nova demanda')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Demanda manual multi-fruta</h4>
            <a href="{{ route('admin.movimentacoes.transferencias.demandas.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.movimentacoes.transferencias.demandas.store') }}">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Origem</label>
                        <select name="id_unidade_negocio_origem" class="form-select" required>
                            <option value="">Selecione</option>
                            @foreach ($unidades as $unidade)
                                <option value="{{ $unidade->id }}" @selected(old('id_unidade_negocio_origem') == $unidade->id)>{{ $unidade->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Destino</label>
                        <select name="id_unidade_negocio_destino" class="form-select" required>
                            <option value="">Selecione</option>
                            @foreach ($unidades as $unidade)
                                <option value="{{ $unidade->id }}" @selected(old('id_unidade_negocio_destino') == $unidade->id)>{{ $unidade->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observação</label>
                        <input type="text" name="observacao" class="form-control" maxlength="500" value="{{ old('observacao') }}">
                    </div>
                </div>

                @include('admin.movimentacoes.transferencias.demandas._form-linhas', ['demanda' => null])

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Salvar demanda</button>
                </div>
            </form>
        </div>
    </div>
@endsection
