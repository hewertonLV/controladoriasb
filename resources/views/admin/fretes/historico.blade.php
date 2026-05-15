@extends('layouts.app')

@section('title', 'Histórico — ' . $frete->nome)
@section('page-title', 'Histórico do Frete')

@php
    $rotuloCampo = [
        'nome' => 'Nome',
        'valor' => 'Valor',
        'id_veiculo' => 'Veículo',
        'descricao' => 'Descrição',
        'status_situacao' => 'Situação',
        'valor_fruta_kg' => 'Valor fruta/kg',
    ];

    $fmtMoeda = fn ($v) => $v === null || $v === '' ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');

    $formatValor = function (string $campo, mixed $valor) use ($fmtMoeda) {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if (in_array($campo, ['valor', 'valor_fruta_kg'], true)) {
            return $fmtMoeda($valor);
        }
        if ($campo === 'status_situacao') {
            return $valor === 'ABERTA' ? 'Aberta' : 'Encerrada';
        }

        return (string) $valor;
    };

    $badgeAcao = [
        'CRIACAO' => 'bg-success-subtle text-success',
        'ATUALIZACAO' => 'bg-warning-subtle text-warning',
        'IMPORTACAO_CRIACAO' => 'bg-info-subtle text-info',
        'IMPORTACAO_ATUALIZACAO' => 'bg-info-subtle text-info',
    ];
@endphp

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-1">{{ $frete->nome }}</h4>
                <div class="text-muted small">
                    Veículo:
                    @if ($frete->veiculo)
                        <code>{{ $frete->veiculo->id_sbs }}</code> — {{ $frete->veiculo->nome }}
                    @else
                        —
                    @endif
                    · {{ $fmtMoeda($frete->valor) }}
                    · Situação: {{ $frete->status_situacao === 'ABERTA' ? 'Aberta' : 'Encerrada' }}
                </div>
            </div>
            <a href="{{ route('admin.fretes.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="header-title mb-0">Eventos</h5>
            <p class="text-muted mb-0">Ordem cronológica decrescente.</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-centered mb-0">
                    <thead class="bg-light bg-opacity-50">
                        <tr>
                            <th style="width: 170px;">Quando</th>
                            <th>Ação</th>
                            <th>Origem</th>
                            <th>Usuário</th>
                            <th>Alterações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($historicos as $h)
                            <tr>
                                <td class="text-nowrap">
                                    {{ optional($h->created_at)->format('d/m/Y H:i:s') ?? '—' }}
                                </td>
                                <td>
                                    <span class="badge {{ $badgeAcao[$h->acao] ?? 'bg-light text-dark' }}">
                                        {{ $h->rotuloAcao() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($h->origem === \App\Models\FreteHistorico::ORIGEM_IMPORTACAO_EXCEL)
                                        <i class="ri-file-excel-2-line text-success me-1"></i>
                                    @else
                                        <i class="ri-user-line text-muted me-1"></i>
                                    @endif
                                    {{ $h->rotuloOrigem() }}
                                </td>
                                <td>{{ $h->user?->name ?? '—' }}</td>
                                <td>
                                    @if ($h->acao === \App\Models\FreteHistorico::ACAO_CRIACAO || $h->acao === \App\Models\FreteHistorico::ACAO_IMPORTACAO_CRIACAO)
                                        <span class="text-muted small">Criação inicial — todos os campos foram definidos.</span>
                                    @elseif (! empty($h->alteracoes))
                                        <ul class="mb-0 ps-3 small">
                                            @foreach ($h->alteracoes as $a)
                                                <li>
                                                    <span class="text-muted">{{ $rotuloCampo[$a['campo']] ?? $a['campo'] }}:</span>
                                                    <span class="text-decoration-line-through text-danger">{{ $formatValor($a['campo'], $a['antes']) }}</span>
                                                    <i class="ri-arrow-right-line text-muted mx-1"></i>
                                                    <span class="text-success fw-semibold">{{ $formatValor($a['campo'], $a['depois']) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Nenhum evento registrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($historicos->hasPages())
            <div class="card-footer">
                {{ $historicos->links() }}
            </div>
        @endif
    </div>
@endsection
