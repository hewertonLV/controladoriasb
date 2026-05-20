@extends('layouts.app')

@section('title', 'Novo ICMS')
@section('page-title', 'Novo ICMS de Fruta')

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <a href="{{ route('admin.frutas.icms.index') }}" class="btn btn-light btn-sm">
                <i class="ri-arrow-left-line"></i>
            </a>
            <h4 class="header-title mb-0">Cadastrar ICMS</h4>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.frutas.icms.store') }}" novalidate>
                @csrf
                @include('admin.frutas.icms._form', [
                    'frutas' => $frutas,
                    'estados' => $estados,
                    'icmsLinha' => $icmsLinha,
                ])
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Salvar
                    </button>
                    <a href="{{ route('admin.frutas.icms.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
