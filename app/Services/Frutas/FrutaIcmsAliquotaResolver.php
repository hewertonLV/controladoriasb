<?php

namespace App\Services\Frutas;

use App\Enums\FrutaIcmsEscopoVenda;
use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaProcedencia;
use App\Models\Fruta;
use App\Models\FrutaIcmsAliquota;
use App\Models\FrutaIcmsHistorico;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use DateTimeInterface;
use Illuminate\Support\Collection;

class FrutaIcmsAliquotaResolver
{
    public function __construct(
        private readonly FrutaIcmsHistoricoService $historicoService,
    ) {}

    public function buscarEntradaPorKg(
        Fruta $fruta,
        int $idEstado,
        FrutaProcedencia $procedencia,
        ?DateTimeInterface $dataReferencia = null,
    ): ?FrutaIcmsAliquota {
        return $this->buscar(
            $fruta->id,
            $idEstado,
            FrutaIcmsOperacao::ENTRADA,
            $procedencia,
            null,
            $dataReferencia,
        );
    }

    public function buscarSaidaPercentual(
        Fruta $fruta,
        int $idEstado,
        FrutaProcedencia $procedencia,
        FrutaIcmsEscopoVenda $escopo,
        ?DateTimeInterface $dataReferencia = null,
    ): ?FrutaIcmsAliquota {
        return $this->buscar(
            $fruta->id,
            $idEstado,
            FrutaIcmsOperacao::SAIDA,
            $procedencia,
            $escopo,
            $dataReferencia,
        );
    }

    /**
     * @return Collection<int, FrutaIcmsAliquota>
     */
    public function listarPorFrutaEstado(int $frutaId, int $idEstado): Collection
    {
        return FrutaIcmsAliquota::query()
            ->where('fruta_id', $frutaId)
            ->where('id_estado', $idEstado)
            ->orderBy('operacao')
            ->orderBy('procedencia')
            ->orderBy('escopo_venda')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function mapaParaFormulario(Fruta $fruta, int $idEstado): array
    {
        if ($fruta->id === null) {
            return FrutaIcmsLinhaFormulario::vazia();
        }

        $linha = FrutaIcmsLinhaFormulario::vazia();
        $aliquotas = $this->listarPorFrutaEstado($fruta->id, $idEstado);

        foreach (FrutaIcmsLinhaFormulario::definicoes() as $def) {
            $registro = $aliquotas->first(function (FrutaIcmsAliquota $a) use ($def): bool {
                $escopo = $def['escopo_venda'];

                return $a->operacao->value === $def['operacao']
                    && $a->procedencia->value === $def['procedencia']
                    && ($escopo === null
                        ? $a->escopo_venda === null
                        : $a->escopo_venda?->value === $escopo);
            });

            if ($registro !== null) {
                $linha[$def['chave']] = number_format((float) $registro->valor, 2, '.', '');
            }
        }

        return $linha;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function mapaCompletoFruta(Fruta $fruta): array
    {
        $mapa = [];

        foreach ($fruta->icmsAliquotas->pluck('id_estado')->unique() as $idEstado) {
            $mapa[(int) $idEstado] = $this->mapaParaFormulario($fruta, (int) $idEstado);
        }

        return $mapa;
    }

    private function buscar(
        int $frutaId,
        int $idEstado,
        FrutaIcmsOperacao $operacao,
        FrutaProcedencia $procedencia,
        ?FrutaIcmsEscopoVenda $escopo,
        ?DateTimeInterface $dataReferencia,
    ): ?FrutaIcmsAliquota {
        if ($dataReferencia !== null) {
            $historico = $this->historicoService->vigenteNaData($frutaId, $idEstado, $dataReferencia);

            if ($historico !== null) {
                return $this->aliquotaFromHistorico($historico, $operacao, $procedencia, $escopo);
            }
        }

        return FrutaIcmsAliquota::query()
            ->where('fruta_id', $frutaId)
            ->where('id_estado', $idEstado)
            ->where('operacao', $operacao)
            ->where('procedencia', $procedencia)
            ->when(
                $escopo === null,
                fn ($q) => $q->whereNull('escopo_venda'),
                fn ($q) => $q->where('escopo_venda', $escopo),
            )
            ->first();
    }

    private function aliquotaFromHistorico(
        FrutaIcmsHistorico $historico,
        FrutaIcmsOperacao $operacao,
        FrutaProcedencia $procedencia,
        ?FrutaIcmsEscopoVenda $escopo,
    ): ?FrutaIcmsAliquota {
        $linha = $historico->aliquotasArray();
        $def = collect(FrutaIcmsLinhaFormulario::definicoes())->first(
            fn (array $d): bool => $d['operacao'] === $operacao->value
                && $d['procedencia'] === $procedencia->value
                && ($escopo === null
                    ? $d['escopo_venda'] === null
                    : $d['escopo_venda'] === $escopo->value),
        );

        if ($def === null) {
            return null;
        }

        $valor = (float) ($linha[$def['chave']] ?? 0);

        return new FrutaIcmsAliquota([
            'fruta_id' => $historico->fruta_id,
            'id_estado' => $historico->id_estado,
            'operacao' => $operacao,
            'procedencia' => $procedencia,
            'escopo_venda' => $escopo,
            'tipo_valor' => $def['tipo_valor'],
            'valor' => $valor,
        ]);
    }
}
