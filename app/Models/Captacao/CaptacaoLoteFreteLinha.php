<?php

namespace App\Models\Captacao;

use App\Models\Fruta;
use App\Models\Frete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptacaoLoteFreteLinha extends Model
{
    protected $table = 'captacao_lote_frete_linhas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_captacao_lote',
        'id_fruta',
        'id_frete',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(CaptacaoLote::class, 'id_captacao_lote');
    }

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'id_fruta');
    }

    public function frete(): BelongsTo
    {
        return $this->belongsTo(Frete::class, 'id_frete');
    }
}
