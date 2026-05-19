<?php

namespace App\Services\Frutas;

use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaUmIcms;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\FrutaIcms;
use App\Models\FrutaIcmsHistorico;
use App\Models\User;

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
        $this->upsertOperacao($fruta, $idEstado, FrutaIcmsOperacao::ENTRADA, [
            'icms_nacional' => $linha['entrada_nacional'] ?? $linha['compra_nacional'] ?? 0,
            'um_icms_nacional' => $linha['entrada_um_nacional'] ?? $linha['um_compra_nacional'] ?? FrutaUmIcms::KG->value,
            'icms_externo' => $linha['entrada_externo'] ?? $linha['compra_exterior'] ?? 0,
            'um_icms_externo' => $linha['entrada_um_externo'] ?? $linha['um_compra_exterior'] ?? FrutaUmIcms::KG->value,
            'icms_venda_importada' => 0,
            'um_icms_venda_importada' => FrutaUmIcms::KG->value,
            'icms_venda_nacional' => 0,
            'um_icms_venda_nacional' => FrutaUmIcms::KG->value,
        ]);

        $this->upsertOperacao($fruta, $idEstado, FrutaIcmsOperacao::SAIDA, [
            'icms_nacional' => 0,
            'um_icms_nacional' => FrutaUmIcms::KG->value,
            'icms_externo' => 0,
            'um_icms_externo' => FrutaUmIcms::KG->value,
            'icms_venda_importada' => $linha['saida_importada'] ?? $linha['venda_importada'] ?? 0,
            'um_icms_venda_importada' => $linha['saida_um_importada'] ?? $linha['um_venda_importada'] ?? FrutaUmIcms::KG->value,
            'icms_venda_nacional' => $linha['saida_nacional'] ?? $linha['venda_nacional'] ?? 0,
            'um_icms_venda_nacional' => $linha['saida_um_nacional'] ?? $linha['um_venda_nacional'] ?? FrutaUmIcms::KG->value,
        ]);

        $this->historicoService->registrarSeAlterou($fruta, $idEstado, $linha, $user, $origem);
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function upsertOperacao(
        Fruta $fruta,
        int $idEstado,
        FrutaIcmsOperacao $operacao,
        array $valores,
    ): void {
        FrutaIcms::query()->updateOrCreate(
            [
                'fruta_id' => $fruta->id,
                'id_estado' => $idEstado,
                'operacao' => $operacao,
            ],
            $valores,
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function mapaParaFormulario(Fruta $fruta): array
    {
        $fruta->loadMissing(['icms.estado']);

        $mapa = [];

        foreach (Estado::query()->orderBy('nome')->get() as $estado) {
            $entrada = $fruta->icms
                ->first(fn (FrutaIcms $i) => $i->id_estado === $estado->id && $i->operacao === FrutaIcmsOperacao::ENTRADA);
            $saida = $fruta->icms
                ->first(fn (FrutaIcms $i) => $i->id_estado === $estado->id && $i->operacao === FrutaIcmsOperacao::SAIDA);

            $mapa[$estado->id] = [
                'entrada_nacional' => $entrada ? number_format((float) $entrada->icms_nacional, 2, '.', '') : '0.00',
                'entrada_um_nacional' => $entrada?->um_icms_nacional ?? FrutaUmIcms::KG->value,
                'entrada_externo' => $entrada ? number_format((float) $entrada->icms_externo, 2, '.', '') : '0.00',
                'entrada_um_externo' => $entrada?->um_icms_externo ?? FrutaUmIcms::KG->value,
                'saida_importada' => $saida ? number_format((float) $saida->icms_venda_importada, 2, '.', '') : '0.00',
                'saida_um_importada' => $saida?->um_icms_venda_importada ?? FrutaUmIcms::KG->value,
                'saida_nacional' => $saida ? number_format((float) $saida->icms_venda_nacional, 2, '.', '') : '0.00',
                'saida_um_nacional' => $saida?->um_icms_venda_nacional ?? FrutaUmIcms::KG->value,
            ];
        }

        return $mapa;
    }

    /**
     * @return array<string, string>
     */
    public function snapshotImportacao(Fruta $fruta, int $idEstado): array
    {
        $linha = $this->mapaParaFormulario($fruta)[$idEstado] ?? [
            'entrada_nacional' => '0.00',
            'entrada_um_nacional' => FrutaUmIcms::KG->value,
            'entrada_externo' => '0.00',
            'entrada_um_externo' => FrutaUmIcms::KG->value,
            'saida_importada' => '0.00',
            'saida_um_importada' => FrutaUmIcms::KG->value,
            'saida_nacional' => '0.00',
            'saida_um_nacional' => FrutaUmIcms::KG->value,
        ];

        return [
            'compra_nacional' => $linha['entrada_nacional'],
            'um_compra_nacional' => $linha['entrada_um_nacional'],
            'compra_exterior' => $linha['entrada_externo'],
            'um_compra_exterior' => $linha['entrada_um_externo'],
            'venda_importada' => $linha['saida_importada'],
            'um_venda_importada' => $linha['saida_um_importada'],
            'venda_nacional' => $linha['saida_nacional'],
            'um_venda_nacional' => $linha['saida_um_nacional'],
        ];
    }

    public function possuiConfiguracao(Fruta $fruta, int $idEstado): bool
    {
        return FrutaIcms::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->exists();
    }
}
