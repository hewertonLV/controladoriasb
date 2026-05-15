@extends('layouts.app')

@section('title', 'Importação — hub corporativo')
@section('page-title', 'Importação e hub corporativo')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-0">Planilha exclusiva de “empresas” não é mais utilizada</h4>
                <p class="text-muted mb-0">
                    O cadastro permanece em <strong>Clientes</strong>, <strong>Fornecedores</strong> e <strong>Unidades de negócio</strong>.
                    Ao importar ou criar nesses módulos, o vínculo no hub corporativo é gerado automaticamente.
                </p>
            </div>
            <a href="{{ route('admin.empresas.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar ao hub
            </a>
        </div>
        <div class="card-body">
            <p class="mb-2">Use as importações já existentes:</p>
            <ul class="mb-0">
                @can('clientes.importar')
                    <li><a href="{{ route('admin.clientes.importar') }}">Importar clientes</a></li>
                @endcan
                @can('fornecedores.importar')
                    <li><a href="{{ route('admin.fornecedores.importar') }}">Importar fornecedores</a></li>
                @endcan
                @can('unidades-negocio.importar')
                    <li><a href="{{ route('admin.unidades-negocio.importar') }}">Importar unidades de negócio</a></li>
                @endcan
            </ul>
        </div>
    </div>
@endsection
