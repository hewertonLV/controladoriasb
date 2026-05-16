<?php

namespace Database\Factories;

use App\Enums\MovimentacaoStatusRegistro;
use App\Models\CategoriaMovimentacao;
use App\Models\Fruta;
use App\Models\Movimentacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movimentacao>
 */
class MovimentacaoFactory extends Factory
{
    protected $model = Movimentacao::class;

    public function definition(): array
    {
        return [
            'id_fruta' => Fruta::factory(),
            'categoria_movimentacao_id' => CategoriaMovimentacao::ID_COMPRA,
            'valor_nf_total' => '0.00',
            'valor_nf_um' => '0.00',
            'valor_nf_kg' => '0.00',
            'valor_total_movimentacao' => '0.00',
            'valor_custo_saida' => '0.00',
            'resultado_movimentacao' => '0.00',
            'valor_icms_total' => '0.00',
            'valor_icms_kg' => '0.00',
            'valor_icms_um' => '0.00',
            'qtd_fruta_kg' => '10.00',
            'qtd_fruta_um' => '0.00',
            'valor_frete_rateio' => '0.00',
            'valor_frete_um' => '0.00',
            'valor_frete_kg' => '0.00',
            'valor_custo_operacional' => '0.00',
            'saldo_estoque_fruta_kg' => '0.00',
            'saldo_estoque_fruta_um' => '0.00',
            'preco_medio_fruta_kg' => '0.00',
            'preco_medio_fruta_um' => '0.00',
            'icms_convertido_kg' => '0.00',
            'categoria_descarte_id' => null,
            'venda_nota_id' => null,
            'id_unidade_negocio_faturamento' => null,
            'motivo_descarte' => null,
            'movimentacao_origem_id' => null,
            'substituida_por_id' => null,
            'versao' => 1,
            'versao_replay' => 1,
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
            'data_movimentacao' => now(),
        ];
    }
}
