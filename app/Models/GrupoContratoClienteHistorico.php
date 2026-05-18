<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupoContratoClienteHistorico extends Model
{
    public const UPDATED_AT = null;

    public const ORIGEM_MANUAL = 'MANUAL';

    public const ACAO_CRIACAO = 'CRIACAO';

    public const ACAO_ATUALIZACAO = 'ATUALIZACAO';

    protected $table = 'grupo_contrato_cliente_historicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_contrato_cliente_id',
        'grupo_contrato_id',
        'cliente_id',
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
}
