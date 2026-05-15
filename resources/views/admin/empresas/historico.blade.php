@extends('layouts.app')

@section('title', 'Histórico — registro corporativo')
@section('page-title', 'Histórico do registro corporativo')

@php
    $dados = $empresa->dadosConsolidadosParaAuditoria();
    $rotuloCampo = [
        'tipo_registro' => 'Tipo',
        'id_cigam' => 'ID CIGAM',
        'status' => 'Status',
        'nome_exibicao' => 'Nome / razão social',
        'fantasia' => 'Fantasia',
        'documento' => 'CPF/CNPJ',
        'unidade_referencia' => 'Referência UN',
        'tipo_pessoa' => 'Tipo documento',
    ];

    $formatValor = function (string $campo, mixed $valor) {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if ($campo === 'status') {
            return $valor ? 'Ativa' : 'Inativa';
        }
        if ($campo === 'documento') {
            $d = preg_replace('/\D/', '', (string) $valor) ?? '';
            if (strlen($d) === 11) {
                return substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2);
            }
            if (strlen($d) === 14) {
                return substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12, 2);
            }
            return $d;
        }
        return (string) $valor;
    };

    $badgeAcao = [
        'CRIACAO' => 'bg-success-subtle text-success',
        'ATUALIZACAO' => 'bg-warning-subtle text-warning',
        'INATIVACAO' => 'bg-secondary-subtle text-secondary',
        'REATIVACAO' => 'bg-success-subtle text-success',
        'IMPORTACAO_CRIACAO' => 'bg-info-subtle text-info',
        'IMPORTACAO_ATUALIZACAO' => 'bg-info-subtle text-info',
        'EXCLUSAO_REGISTRO' => 'bg-danger-subtle text-danger',
    ];
@endphp

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-1">{{ $dados['nome_exibicao'] ?? '—' }}</h4>
                <div class="text-muted small">
                    <span class="badge bg-secondary-subtle text-secondary">{{ $empresa->rotuloTipoRegistro() }}</span>
                    · <code>{{ $dados['id_cigam'] ?? '—' }}</code>
                    · {{ $empresa->documentoFormatado() }}
                    · {{ $dados['tipo_pessoa'] === 'FISICA' ? 'Pessoa física' : ($dados['tipo_pessoa'] === 'JURIDICA' ? 'Pessoa jurídica' : '—') }}
                    ·
                    @if ($dados['status'] ?? false)
                        <span class="badge bg-success-subtle text-success">Ativa (no cadastro)</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary">Inativa / N/A</span>
                    @endif
                </div>
                <div class="text-muted small mt-1">
                    Registro corporativo #{{ $empresa->id }}
                    · Criado em {{ optional($empresa->created_at)->format('d/m/Y H:i') ?? '—' }}
                    · Atualizado em {{ optional($empresa->updated_at)->format('d/m/Y H:i') ?? '—' }}
                </div>
            </div>
            <a href="{{ route('admin.empresas.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="header-title mb-0">Eventos</h5>
            <p class="text-muted mb-0">Ordem cronológica decrescente (somente eventos do hub).</p>
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
                                    @if ($h->origem === \App\Models\EmpresaHistorico::ORIGEM_IMPORTACAO_EXCEL)
                                        <i class="ri-file-excel-2-line text-success me-1"></i>
                                    @elseif ($h->origem === \App\Models\EmpresaHistorico::ORIGEM_SISTEMA)
                                        <i class="ri-robot-line text-muted me-1"></i>
                                    @else
                                        <i class="ri-user-line text-muted me-1"></i>
                                    @endif
                                    {{ $h->rotuloOrigem() }}
                                </td>
                                <td>{{ $h->user?->name ?? '—' }}</td>
                                <td>
                                    @if ($h->acao === \App\Models\EmpresaHistorico::ACAO_CRIACAO || $h->acao === \App\Models\EmpresaHistorico::ACAO_IMPORTACAO_CRIACAO)
                                        <span class="text-muted small">Criação do vínculo — snapshot dos dados consolidados.</span>
                                    @elseif (! empty($h->alteracoes))
                                        <ul class="mb-0 ps-3 small">
                                            @foreach ($h->alteracoes as $a)
                                                <li>
                                                    <span class="text-muted">{{ $rotuloCampo[$a['campo']] ?? $a['campo'] }}:</span>
                                                    <span class="text-decoration-line-through text-danger">{{ $formatValor($a['campo'], $a['antes'] ?? null) }}</span>
                                                    <i class="ri-arrow-right-line text-muted mx-1"></i>
                                                    <span class="text-success fw-semibold">{{ $formatValor($a['campo'], $a['depois'] ?? null) }}</span>
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
