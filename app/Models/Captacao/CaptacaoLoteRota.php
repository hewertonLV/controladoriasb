<?php

namespace App\Models\Captacao;

use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptacaoLoteRota extends Model
{
    protected $table = 'captacao_lote_rotas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_captacao_lote',
        'id_captacao_rota',
        'nome_motorista',
        'id_veiculo',
        'concluida',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'concluida' => 'boolean',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(CaptacaoLote::class, 'id_captacao_lote');
    }

    public function rota(): BelongsTo
    {
        return $this->belongsTo(CaptacaoRota::class, 'id_captacao_rota');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'id_veiculo');
    }
}
