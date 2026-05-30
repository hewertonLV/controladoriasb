<?php

namespace App\Models\Captacao;

use App\Models\Fruta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaptacaoLoteMovimentacaoLinha extends Model
{
    use SoftDeletes;
    protected $table = 'captacao_lote_movimentacao_linhas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_captacao_lote_movimentacao',
        'id_pedido',
        'id_pedido_key',
        'id_fruta',
        'qtd_um',
        'preco_venda',
    ];

    public function movimentacao(): BelongsTo
    {
        return $this->belongsTo(CaptacaoLoteMovimentacao::class, 'id_captacao_lote_movimentacao');
    }

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'id_fruta');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }

    protected static function booted(): void
    {
        static::saving(function (self $linha): void {
            $linha->id_pedido_key = (int) ($linha->id_pedido ?? 0);
        });
    }
}
