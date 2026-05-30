<?php

namespace App\Models\Captacao;

use App\Models\Fruta;
use App\Models\VendaNota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaptacaoLoteMovimentacao extends Model
{
    use SoftDeletes;

    public const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';

    public const TIPO_VENDA_NOTA = 'VENDA_NOTA';

    protected $table = 'captacao_lote_movimentacoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_captacao_lote',
        'id_captacao_rota',
        'id_pedido',
        'tipo',
        'id_fruta',
        'id_unidade_negocio_origem',
        'transferencia_origem_id',
        'id_transferencia_origem_dependencia',
        'venda_nota_id',
        'status_demanda',
        'qtd_um',
        'nf_transferencia_path',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(CaptacaoLote::class, 'id_captacao_lote');
    }

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'id_fruta');
    }

    public function vendaNota(): BelongsTo
    {
        return $this->belongsTo(VendaNota::class, 'venda_nota_id');
    }

    public function captacaoRota(): BelongsTo
    {
        return $this->belongsTo(CaptacaoRota::class, 'id_captacao_rota');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }

    /**
     * @return HasMany<CaptacaoLoteMovimentacaoLinha, $this>
     */
    public function linhas(): HasMany
    {
        return $this->hasMany(CaptacaoLoteMovimentacaoLinha::class, 'id_captacao_lote_movimentacao');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $demanda): void {
            if ($demanda->isForceDeleting()) {
                $demanda->linhas()->withTrashed()->forceDelete();

                return;
            }

            $demanda->linhas()->get()->each->delete();
        });
    }
}
