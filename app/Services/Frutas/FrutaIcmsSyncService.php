<?php

namespace App\Services\Frutas;

use App\Enums\FrutaIcmsEscopoVenda;
use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaIcmsTipoValor;
use App\Enums\FrutaProcedencia;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\FrutaIcmsAliquota;
use App\Models\FrutaIcmsHistorico;
use App\Models\User;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use App\Support\TextoCadastro;

class FrutaIcmsSyncService
{
    public function __construct(
        private readonly FrutaIcmsHistoricoService $historicoService,
    ) {}

    /**
     * @param  array<int|string, array<string, mixed>>  $icmsPorEstado
     */
    public function sync(
        Fruta $fruta,
        array $icmsPorEstado,
        ?User $user = null,
        string $origem = FrutaIcmsHistorico::ORIGEM_FRUTA_FORM,
    ): void {
        foreach (Estado::query()->orderBy('nome')->pluck('id') as $idEstado) {
            $linha = $icmsPorEstado[$idEstado] ?? $icmsPorEstado[(string) $idEstado] ?? [];
            $this->syncEstado($fruta, (int) $idEstado, $linha, $user, $origem);
        }
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    public function syncEstado(
        Fruta $fruta,
        int $idEstado,
        array $linha,
        ?User $user = null,
        string $origem = FrutaIcmsHistorico::ORIGEM_MANUAL,
    ): void {
        $linha = $this->normalizarLinha($linha);

        foreach (FrutaIcmsLinhaFormulario::definicoes() as $def) {
            $valor = (float) ($linha[$def['chave']] ?? 0);

            FrutaIcmsAliquota::query()->updateOrCreate(
                [
                    'fruta_id' => $fruta->id,
                    'id_estado' => $idEstado,
                    'operacao' => $def['operacao'],
                    'procedencia' => $def['procedencia'],
                    'escopo_venda' => $def['escopo_venda'],
                ],
                [
                    'tipo_valor' => $def['tipo_valor'],
                    'valor' => number_format($valor, 4, '.', ''),
                ],
            );
        }

        $this->historicoService->registrarSeAlterou($fruta, $idEstado, $linha, $user, $origem);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function mapaParaFormulario(Fruta $fruta): array
    {
        if ($fruta->id !== null) {
            $fruta->loadMissing('icmsAliquotas');
        }

        $mapa = [];
        $resolver = app(FrutaIcmsAliquotaResolver::class);

        foreach (Estado::query()->orderBy('nome')->get() as $estado) {
            $mapa[$estado->id] = $resolver->mapaParaFormulario($fruta, $estado->id);
        }

        return $mapa;
    }

    /**
     * @return array<string, string>
     */
    public function snapshotImportacao(Fruta $fruta, int $idEstado): array
    {
        return app(FrutaIcmsAliquotaResolver::class)->mapaParaFormulario($fruta, $idEstado);
    }

    public function possuiConfiguracao(Fruta $fruta, int $idEstado): bool
    {
        return FrutaIcmsAliquota::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $linha
     * @return array<string, string>
     */
    private function normalizarLinha(array $linha): array
    {
        $linha = FrutaIcmsLinhaFormulario::normalizarChavesLegadas($linha);
        $normalizada = FrutaIcmsLinhaFormulario::vazia();

        foreach (FrutaIcmsLinhaFormulario::chaves() as $chave) {
            $normalizada[$chave] = TextoCadastro::normalizarValorMonetarioBrasileiro($linha[$chave] ?? 0);
        }

        return $normalizada;
    }
}
