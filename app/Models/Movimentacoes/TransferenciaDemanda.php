<?php

namespace App\Models\Movimentacoes;

use App\Models\Fruta;
use App\Models\Frete;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferenciaDemanda extends Model
{
    public const ORIGEM_MANUAL = 'MANUAL';

    protected $table = 'transferencia_demandas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'origem',
        'status',
        'id_unidade_negocio_origem',
        'id_unidade_negocio_destino',
        'observacao',
        'id_frete',
        'nf_transferencia_path',
        'transferencia_origem_id',
    ];

    public function unidadeOrigem(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_origem');
    }

    public function unidadeDestino(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_destino');
    }

    public function frete(): BelongsTo
    {
        return $this->belongsTo(Frete::class, 'id_frete');
    }

    /**
     * @return HasMany<TransferenciaDemandaLinha, $this>
     */
    public function linhas(): HasMany
    {
        return $this->hasMany(TransferenciaDemandaLinha::class, 'id_transferencia_demanda');
    }
}
