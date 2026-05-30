<?php

namespace App\Models\Movimentacoes;

use App\Models\Fruta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferenciaDemandaLinha extends Model
{
    protected $table = 'transferencia_demanda_linhas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_transferencia_demanda',
        'id_fruta',
        'qtd_um',
    ];

    public function demanda(): BelongsTo
    {
        return $this->belongsTo(TransferenciaDemanda::class, 'id_transferencia_demanda');
    }

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'id_fruta');
    }
}
