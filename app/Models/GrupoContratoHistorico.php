<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrupoContratoHistorico extends Model
{
    public const UPDATED_AT = null;

    public const ORIGEM_MANUAL = 'MANUAL';

    public const ACAO_CRIACAO = 'CRIACAO';

    public const ACAO_ATUALIZACAO = 'ATUALIZACAO';

    protected $table = 'grupo_contrato_historicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_contrato_id',
        'user_id',
        'origem',
        'acao',
        'dados_antes',
        'dados_depois',
        'alteracoes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dados_antes' => 'array',
            'dados_depois' => 'array',
            'alteracoes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function grupoContrato(): BelongsTo
    {
        return $this->belongsTo(GrupoContrato::class, 'grupo_contrato_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
