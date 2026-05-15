@extends('layouts.app')

@section('title', 'Fornecedor — ' . $fornecedor->razao_social)
@section('page-title', 'Detalhes do Fornecedor')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-1">{{ $fornecedor->razao_social }}</h4>
                <div class="text-muted small">
                    <code>{{ $fornecedor->id_cigam }}</code>
                    · Estado (ICMS): <span class="fw-semibold">{{ $fornecedor->estado?->nome ?? '—' }}</span>
                    · {{ $fornecedor->cnpj_cpf_formatado }}
                    @if ($fornecedor->fantasia)
                        · {{ $fornecedor->fantasia }}
                    @endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.fornecedores.index') }}" class="btn btn-light">
                    <i class="ri-arrow-left-line me-1"></i> Voltar
                </a>
                @can('fornecedores.editar')
                    <a href="{{ route('admin.fornecedores.edit', $fornecedor) }}" class="btn btn-soft-primary">
                        <i class="ri-pencil-line me-1"></i> Editar
                    </a>
                @endcan
                @can('fornecedores.historico')
                    <a href="{{ route('admin.fornecedores.historico', $fornecedor) }}" class="btn btn-soft-info">
                        <i class="ri-history-line me-1"></i> Histórico
                    </a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <p class="text-muted text-uppercase fs-12 mb-1">ID CIGAM</p>
                    <p class="mb-0"><code>{{ $fornecedor->id_cigam }}</code></p>
                </div>
                <div class="col-md-4">
                    <p class="text-muted text-uppercase fs-12 mb-1">Estado (ICMS)</p>
                    <p class="mb-0 fw-semibold">{{ $fornecedor->estado?->nome ?? '—' }}</p>
                </div>
                <div class="col-md-4">
                    <p class="text-muted text-uppercase fs-12 mb-1">CPF/CNPJ</p>
                    <p class="mb-0"><code>{{ $fornecedor->cnpj_cpf_formatado }}</code></p>
                </div>
                <div class="col-md-12">
                    <p class="text-muted text-uppercase fs-12 mb-1">Razão social</p>
                    <p class="mb-0">{{ $fornecedor->razao_social }}</p>
                </div>
                <div class="col-md-12">
                    <p class="text-muted text-uppercase fs-12 mb-1">Fantasia</p>
                    <p class="mb-0">{{ $fornecedor->fantasia ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
