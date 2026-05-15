@extends('layouts.app')

@section('title', 'Editar Veículo')
@section('page-title', 'Editar Veículo')

@section('content')
    <form method="POST" action="{{ route('admin.veiculos.update', $veiculo) }}">
        @csrf
        @method('PUT')
        @include('admin.veiculos._form', [
            'submitLabel' => 'Atualizar',
            'cardTitle' => 'Editar veículo',
        ])
    </form>
@endsection

