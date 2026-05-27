<?php

namespace App\Models\Captacao;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptacaoLoteCiganExport extends Model
{
    protected $table = 'captacao_lote_cigan_exports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_captacao_lote',
        'tipo',
        'versao_layout',
        'caminho_arquivo',
        'user_id',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(CaptacaoLote::class, 'id_captacao_lote');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
