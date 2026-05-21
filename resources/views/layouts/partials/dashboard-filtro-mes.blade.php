@php
    $mesAtual = $mesAtual ?? now()->format('Y-m');
    $inputId = $inputId ?? 'dashboard-mes';
    $botaoId = $botaoId ?? 'dashboard-buscar-mes';
@endphp

<div class="col-md-4 col-lg-3">
    <label for="{{ $inputId }}" class="form-label">Mês</label>
    <input class="form-control"
           id="{{ $inputId }}"
           type="month"
           name="mes"
           value="{{ $mesAtual }}">
</div>
<div class="col-md-3 col-lg-2 d-flex align-items-end">
    <button type="button" class="btn btn-primary w-100" id="{{ $botaoId }}">
        <i class="ri-search-line me-1"></i> Buscar
    </button>
</div>
