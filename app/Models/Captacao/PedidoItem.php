<?php

namespace App\Models\Captacao;

use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    protected $table = 'pedido_itens';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_pedido',
        'id_fruta',
        'quantidade',
        'preco_venda',
        'custo_referencia',
        'id_unidade_origem_fisica',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_venda' => 'decimal:4',
            'custo_referencia' => 'decimal:4',
            'version' => 'integer',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'id_fruta');
    }

    public function unidadeOrigemFisica(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_origem_fisica');
    }
}
