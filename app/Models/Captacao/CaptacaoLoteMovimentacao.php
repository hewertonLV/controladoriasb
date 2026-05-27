<?php

namespace App\Models\Captacao;

use App\Models\Fruta;
use App\Models\VendaNota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptacaoLoteMovimentacao extends Model
{
    public const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';

    public const TIPO_VENDA_NOTA = 'VENDA_NOTA';

    protected $table = 'captacao_lote_movimentacoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_captacao_lote',
        'tipo',
        'id_fruta',
        'transferencia_origem_id',
        'venda_nota_id',
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
}
