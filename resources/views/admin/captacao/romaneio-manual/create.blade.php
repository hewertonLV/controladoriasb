@extends('layouts.app')

@section('title', 'Romaneio manual')
@section('page-title', 'Romaneio manual de abastecimento')

@section('content')
    <x-admin.flash-messages />

    <div class="alert alert-info">
        Após abrir o romaneio, adicione <strong>uma fruta por vez</strong> e informe as quantidades em <strong>caixas</strong>.
        O salvamento é automático, como na matriz de captação.
    </div>

    <form method="post" action="{{ route('admin.captacao.romaneio-manual.store') }}">
        @csrf
        <div class="card mb-3">
            <div class="card-header"><strong>Dados do lote</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-2">
                    <label class="form-label" for="data_referencia">Data</label>
                    <input type="date"
                           id="data_referencia"
                           name="data_referencia"
                           class="form-control @error('data_referencia') is-invalid @enderror"
                           value="{{ old('data_referencia', now()->toDateString()) }}"
                           required>
                    @error('data_referencia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="id_unidade_negocio_faturamento">Unidade de faturamento (Cigan)</label>
                    <select id="id_unidade_negocio_faturamento"
                            name="id_unidade_negocio_faturamento"
                            class="form-select @error('id_unidade_negocio_faturamento') is-invalid @enderror"
                            required>
                        <option value="">Selecione…</option>
                        @foreach ($faturamentos as $un)
                            <option value="{{ $un->id }}" @selected((int) old('id_unidade_negocio_faturamento') === $un->id)>
                                {{ $un->nome }}
                            </option>
                        @endforeach
                    </select>
                    @error('id_unidade_negocio_faturamento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="id_unidade_negocio_galpao">Galpão de destino</label>
                    <select id="id_unidade_negocio_galpao"
                            name="id_unidade_negocio_galpao"
                            class="form-select @error('id_unidade_negocio_galpao') is-invalid @enderror"
                            required>
                        <option value="">Selecione…</option>
                        @foreach ($galpoes as $un)
                            <option value="{{ $un->id }}" @selected((int) old('id_unidade_negocio_galpao') === $un->id)>
                                {{ $un->nome }}
                            </option>
                        @endforeach
                    </select>
                    @error('id_unidade_negocio_galpao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-play-line me-1"></i> Abrir romaneio e montar frutas
                </button>
            </div>
        </div>
    </form>
@endsection
