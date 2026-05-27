<?php

namespace App\Support\Frutas;

use App\Enums\FrutaIcmsEscopoVenda;
use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaIcmsTipoValor;
use App\Enums\FrutaProcedencia;

final class FrutaIcmsLinhaFormulario
{
    public const ENTRADA_NACIONAL_KG = 'entrada_nacional_kg';

    public const ENTRADA_INTERNACIONAL_KG = 'entrada_internacional_kg';

    public const SAIDA_NACIONAL_DENTRO_PCT = 'saida_nacional_dentro_pct';

    public const SAIDA_NACIONAL_FORA_PCT = 'saida_nacional_fora_pct';

    public const SAIDA_INTERNACIONAL_DENTRO_PCT = 'saida_internacional_dentro_pct';

    public const SAIDA_INTERNACIONAL_FORA_PCT = 'saida_internacional_fora_pct';

    /**
     * @return list<string>
     */
    public static function chaves(): array
    {
        return [
            self::ENTRADA_NACIONAL_KG,
            self::ENTRADA_INTERNACIONAL_KG,
            self::SAIDA_NACIONAL_DENTRO_PCT,
            self::SAIDA_NACIONAL_FORA_PCT,
            self::SAIDA_INTERNACIONAL_DENTRO_PCT,
            self::SAIDA_INTERNACIONAL_FORA_PCT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function vazia(): array
    {
        return array_fill_keys(self::chaves(), '0.00');
    }

    /**
     * @return list<array{
     *     operacao: string,
     *     procedencia: string,
     *     escopo_venda: string|null,
     *     tipo_valor: string,
     *     chave: string,
     * }>
     */
    public static function definicoes(): array
    {
        return [
            [
                'operacao' => FrutaIcmsOperacao::ENTRADA->value,
                'procedencia' => FrutaProcedencia::NACIONAL->value,
                'escopo_venda' => null,
                'tipo_valor' => FrutaIcmsTipoValor::VALOR_POR_KG->value,
                'chave' => self::ENTRADA_NACIONAL_KG,
            ],
            [
                'operacao' => FrutaIcmsOperacao::ENTRADA->value,
                'procedencia' => FrutaProcedencia::INTERNACIONAL->value,
                'escopo_venda' => null,
                'tipo_valor' => FrutaIcmsTipoValor::VALOR_POR_KG->value,
                'chave' => self::ENTRADA_INTERNACIONAL_KG,
            ],
            [
                'operacao' => FrutaIcmsOperacao::SAIDA->value,
                'procedencia' => FrutaProcedencia::NACIONAL->value,
                'escopo_venda' => FrutaIcmsEscopoVenda::DENTRO_ESTADO->value,
                'tipo_valor' => FrutaIcmsTipoValor::PERCENTUAL->value,
                'chave' => self::SAIDA_NACIONAL_DENTRO_PCT,
            ],
            [
                'operacao' => FrutaIcmsOperacao::SAIDA->value,
                'procedencia' => FrutaProcedencia::NACIONAL->value,
                'escopo_venda' => FrutaIcmsEscopoVenda::FORA_ESTADO->value,
                'tipo_valor' => FrutaIcmsTipoValor::PERCENTUAL->value,
                'chave' => self::SAIDA_NACIONAL_FORA_PCT,
            ],
            [
                'operacao' => FrutaIcmsOperacao::SAIDA->value,
                'procedencia' => FrutaProcedencia::INTERNACIONAL->value,
                'escopo_venda' => FrutaIcmsEscopoVenda::DENTRO_ESTADO->value,
                'tipo_valor' => FrutaIcmsTipoValor::PERCENTUAL->value,
                'chave' => self::SAIDA_INTERNACIONAL_DENTRO_PCT,
            ],
            [
                'operacao' => FrutaIcmsOperacao::SAIDA->value,
                'procedencia' => FrutaProcedencia::INTERNACIONAL->value,
                'escopo_venda' => FrutaIcmsEscopoVenda::FORA_ESTADO->value,
                'tipo_valor' => FrutaIcmsTipoValor::PERCENTUAL->value,
                'chave' => self::SAIDA_INTERNACIONAL_FORA_PCT,
            ],
        ];
    }

    /**
     * Mapeia chaves legadas do formulário/importação antiga.
     *
     * @param  array<string, mixed>  $linha
     * @return array<string, mixed>
     */
    public static function normalizarChavesLegadas(array $linha): array
    {
        $mapa = [
            'entrada_nacional' => self::ENTRADA_NACIONAL_KG,
            'compra_nacional' => self::ENTRADA_NACIONAL_KG,
            'entrada_externo' => self::ENTRADA_INTERNACIONAL_KG,
            'compra_exterior' => self::ENTRADA_INTERNACIONAL_KG,
            'saida_nacional' => self::SAIDA_NACIONAL_DENTRO_PCT,
            'venda_nacional' => self::SAIDA_NACIONAL_DENTRO_PCT,
            'saida_importada' => self::SAIDA_NACIONAL_FORA_PCT,
            'venda_importada' => self::SAIDA_NACIONAL_FORA_PCT,
        ];

        foreach ($mapa as $legado => $nova) {
            if (array_key_exists($legado, $linha) && ! array_key_exists($nova, $linha)) {
                $linha[$nova] = $linha[$legado];
            }
        }

        return $linha;
    }
}
