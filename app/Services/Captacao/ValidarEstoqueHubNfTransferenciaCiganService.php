<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ValidarEstoqueHubNfTransferenciaCiganService
{
    public function __construct(
        private readonly RomaneioAbastecimentoService $romaneioAbastecimento,
    ) {}

    public function executar(CaptacaoLote $lote): void
    {
        $resultado = $this->analisar($lote);

        if ($resultado === null) {
            return;
        }

        throw new NfTransferenciaEstoqueHubInsuficienteException(
            $resultado['hub_nome'],
            $resultado['frutas'],
        );
    }

    /**
     * @return array{hub_nome: string, frutas: list<array{
     *     fruta_nome: string,
     *     unidade_medicao: string,
     *     estoque_um: string,
     *     estoque_kg: string,
     *     falta_um: string,
     *     falta_kg: string,
     * }>}|null
     */
    public function analisar(CaptacaoLote $lote): ?array
    {
        if ($lote->id_unidade_negocio_hub_origem === null) {
            throw ValidationException::withMessages([
                'id_unidade_negocio_hub_origem' => 'Informe e salve o HUB de origem antes de enviar a NF.',
            ]);
        }

        $hub = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_hub_origem);
        $hubNome = trim((string) ($hub->nome ?: $hub->razao_social ?: 'HUB'));

        $linhas = $this->romaneioAbastecimento->necessidadeEstoqueHub($lote);
        $faltas = $this->frutasComEstoqueInsuficienteNoHub($linhas, (int) $hub->id);

        if ($faltas === []) {
            return null;
        }

        return [
            'hub_nome' => $hubNome,
            'frutas' => $faltas,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $linhas
     * @return list<array{
     *     fruta_nome: string,
     *     unidade_medicao: string,
     *     estoque_um: string,
     *     estoque_kg: string,
     *     falta_um: string,
     *     falta_kg: string,
     * }>
     */
    private function frutasComEstoqueInsuficienteNoHub(Collection $linhas, int $idUnidadeHub): array
    {
        $faltas = [];

        foreach ($linhas as $linha) {
            $necessarioKg = (float) $linha['necessidade_kg'];
            $necessarioUm = (float) $linha['necessidade_um'];
            if ($necessarioKg <= 0 && $necessarioUm <= 0) {
                continue;
            }

            $idFruta = (int) $linha['id_fruta'];
            $fruta = Fruta::query()->find($idFruta);
            $casasKg = $fruta?->casasDecimaisKgPorUnidadeMedicao() ?? 3;

            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $idUnidadeHub)
                ->where('id_fruta', $idFruta)
                ->where('ativo_unico', 1)
                ->first();

            $disponivelKg = $estoque !== null ? round((float) $estoque->qtd_fruta_kg, $casasKg) : 0.0;
            $disponivelUm = $estoque !== null ? round((float) $estoque->qtd_fruta_um, 2) : 0.0;
            $necessarioKg = round($necessarioKg, $casasKg);
            $necessarioUm = round($necessarioUm, 2);

            $faltaKg = max(0.0, round($necessarioKg - $disponivelKg, $casasKg));
            $faltaUm = max(0.0, round($necessarioUm - $disponivelUm, 2));

            if ($faltaKg <= 0 && $faltaUm <= 0) {
                continue;
            }

            $faltas[] = [
                'fruta_nome' => (string) $linha['fruta_nome'],
                'unidade_medicao' => (string) $linha['unidade_medicao'],
                'estoque_um' => $this->formatarBr($disponivelUm, 2),
                'estoque_kg' => $this->formatarBr($disponivelKg, $casasKg),
                'falta_um' => $this->formatarBr($faltaUm, 2),
                'falta_kg' => $this->formatarBr($faltaKg, $casasKg),
            ];
        }

        return $faltas;
    }

    private function formatarBr(float $valor, int $casas): string
    {
        return number_format($valor, $casas, ',', '.');
    }
}
