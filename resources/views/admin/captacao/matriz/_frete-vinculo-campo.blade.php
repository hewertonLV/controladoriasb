@php
    /** @var string $url */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Frete> $fretesAbertos */
    /** @var int|null $idFreteAtual */
    $idFreteAtual = $idFreteAtual ?? null;
    $selectClass = $selectClass ?? 'form-select form-select-sm captacao-frete-select';
    $selectStyle = $selectStyle ?? '';
    $placeholder = $placeholder ?? 'Selecione ou pesquise o frete';
    $dataAttrs = $dataAttrs ?? [];
@endphp

<div class="captacao-frete-vinculo d-flex flex-wrap gap-2 align-items-center"
     data-url="{{ $url }}"
     data-frete-valor-salvo="{{ $idFreteAtual ?? '' }}"
     @foreach ($dataAttrs as $attr => $value)
         data-{{ $attr }}="{{ $value }}"
     @endforeach>
    <select name="id_frete"
            class="{{ $selectClass }}"
            @if ($selectStyle !== '') style="{{ $selectStyle }}" @endif
            data-search-select
            data-placeholder="{{ $placeholder }}">
        <option value=""></option>
        @foreach ($fretesAbertos as $frete)
            <option value="{{ $frete->id }}" @selected((int) $idFreteAtual === $frete->id)>
                {{ $frete->nome }} (R$ {{ $frete->valor }})
            </option>
        @endforeach
    </select>
    <button type="button"
            class="btn btn-link btn-sm text-danger p-0 text-nowrap captacao-frete-remover {{ $idFreteAtual ? '' : 'd-none' }}"
            title="Remover frete desta linha">
        Remover Frete
    </button>
</div>
