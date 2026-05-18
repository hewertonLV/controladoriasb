<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FrutaHistorico extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    public const ORIGEM_MANUAL = 'MANUAL';

    public const ORIGEM_IMPORTACAO_EXCEL = 'IMPORTACAO_EXCEL';

    public const ACAO_CRIACAO = 'CRIACAO';

    public const ACAO_ATUALIZACAO = 'ATUALIZACAO';

    public const ACAO_IMPORTACAO_CRIACAO = 'IMPORTACAO_CRIACAO';

    public const ACAO_IMPORTACAO_ATUALIZACAO = 'IMPORTACAO_ATUALIZACAO';

    protected $table = 'fruta_historicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fruta_id',
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

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'fruta_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rotuloAcao(): string
    {
        return match ($this->acao) {
            self::ACAO_CRIACAO => 'Criação',
            self::ACAO_ATUALIZACAO => 'Atualização',
            self::ACAO_IMPORTACAO_CRIACAO => 'Importação — Criação',
            self::ACAO_IMPORTACAO_ATUALIZACAO => 'Importação — Atualização',
            default => $this->acao,
        };
    }

    public function rotuloOrigem(): string
    {
        return match ($this->origem) {
            self::ORIGEM_MANUAL => 'Manual',
            self::ORIGEM_IMPORTACAO_EXCEL => 'Importação Excel',
            default => $this->origem,
        };
    }
}
