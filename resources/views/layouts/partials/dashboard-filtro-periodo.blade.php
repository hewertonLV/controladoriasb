@php
    $mesAtual = $mesAtual ?? now()->format('Y-m');
    $diaAtual = $diaAtual ?? now()->format('Y-m-d');
    $periodoTipoInicial = $periodoTipoInicial ?? 'mes';
    $inputMesId = $inputMesId ?? 'dashboard-mes';
    $inputDiaId = $inputDiaId ?? 'dashboard-dia';
    $inputTipoId = $inputTipoId ?? 'dashboard-periodo-tipo';
    $wrapMesId = $wrapMesId ?? 'dashboard-periodo-mes-wrap';
    $wrapDiaId = $wrapDiaId ?? 'dashboard-periodo-dia-wrap';
    $botaoId = $botaoId ?? 'dashboard-buscar-mes';
@endphp

<div class="col-md-3 col-lg-2">
    <label for="{{ $inputTipoId }}" class="form-label">Período</label>
    <select class="form-select" id="{{ $inputTipoId }}" name="periodo_tipo" aria-label="Tipo de período">
        <option value="mes" @selected($periodoTipoInicial === 'mes')>Mês</option>
        <option value="dia" @selected($periodoTipoInicial === 'dia')>Dia</option>
    </select>
</div>
<div class="col-md-4 col-lg-3 {{ $periodoTipoInicial === 'dia' ? 'd-none' : '' }}" id="{{ $wrapMesId }}">
    <label for="{{ $inputMesId }}" class="form-label">Mês</label>
    <input class="form-control"
           id="{{ $inputMesId }}"
           type="month"
           name="mes"
           value="{{ $mesAtual }}">
</div>
<div class="col-md-4 col-lg-3 {{ $periodoTipoInicial === 'mes' ? 'd-none' : '' }}" id="{{ $wrapDiaId }}">
    <label for="{{ $inputDiaId }}" class="form-label">Dia</label>
    <input class="form-control"
           id="{{ $inputDiaId }}"
           type="date"
           name="dia"
           value="{{ $diaAtual }}">
</div>
<div class="col-md-3 col-lg-2 d-flex align-items-end">
    <button type="button" class="btn btn-primary w-100" id="{{ $botaoId }}">
        <i class="ri-search-line me-1"></i> Buscar
    </button>
</div>
