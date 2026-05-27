<?php

namespace App\Models\Captacao;

use App\Enums\PedidoOrigem;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pedido extends Model
{
    use SoftDeletes;

    protected $table = 'pedidos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_captacao_lote',
        'id_cliente',
        'id_unidade_negocio_saida_venda',
        'id_captacao_rota',
        'numero_pedido',
        'ordem_carregamento',
        'data_entrega',
        'origem',
        'captacao_concluida',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_entrega' => 'date',
            'origem' => PedidoOrigem::class,
            'captacao_concluida' => 'boolean',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(CaptacaoLote::class, 'id_captacao_lote');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function unidadeSaidaVenda(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_saida_venda');
    }

    public function rota(): BelongsTo
    {
        return $this->belongsTo(CaptacaoRota::class, 'id_captacao_rota');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoItem::class, 'id_pedido');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
