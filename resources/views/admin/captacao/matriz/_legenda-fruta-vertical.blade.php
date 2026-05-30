@php
    use App\Support\Captacao\CaptacaoMatrizLegendaFruta;

    /** @var string $nome */
    [$linha1, $linha2] = CaptacaoMatrizLegendaFruta::duasLinhas($nome);
@endphp
<span class="captacao-matriz-fruta-nome" title="{{ $nome }}">
    @if ($linha1 !== '')
        <span class="captacao-matriz-fruta-linha">{{ $linha1 }}</span>
    @endif
    @if ($linha2 !== '')
        <span class="captacao-matriz-fruta-linha">{{ $linha2 }}</span>
    @endif
</span>
