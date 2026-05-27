@extends('layouts.app')

@section('title', 'Lote de captação')
@section('page-title', 'Lote de captação #'.$lote->id)

@section('content')
    <x-admin.flash-messages />

    @include('admin.captacao._lote-timeline-status', ['lote' => $lote])

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#romaneio1" type="button">Romaneio 1 — Carregamento</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#romaneio2" type="button">Romaneio 2 — Abastecimento</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="romaneio1">
            <div class="card"><div class="card-body table-responsive">
                @include('admin.captacao._romaneio-carregamento-tabela', [
                    'romaneioCarregamento' => $romaneioCarregamento,
                    'romaneioCarregamentoTotaisGerais' => $romaneioCarregamentoTotaisGerais,
                ])
            </div></div>
        </div>
        <div class="tab-pane fade" id="romaneio2">
            <div class="card"><div class="card-body table-responsive">
                @include('admin.captacao._romaneio-abastecimento-tabela', ['romaneioAbastecimento' => $romaneioAbastecimento])
            </div></div>
        </div>
    </div>
@endsection
