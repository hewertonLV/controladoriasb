<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int|null $user_id
 * @property string $origem
 * @property string $acao
 * @property array<string, mixed>|null $dados_antes
 * @property array<string, mixed>|null $dados_depois
 * @property list<array{campo:string, antes:mixed, depois:mixed}>|null $alteracoes
 * @property Carbon|null $created_at
 */
class EmpresaHistorico extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    public const ORIGEM_MANUAL = 'MANUAL';

    public const ORIGEM_IMPORTACAO_EXCEL = 'IMPORTACAO_EXCEL';

    public const ORIGEM_SISTEMA = 'SISTEMA';

    public const ACAO_CRIACAO = 'CRIACAO';

    public const ACAO_ATUALIZACAO = 'ATUALIZACAO';

    public const ACAO_INATIVACAO = 'INATIVACAO';

    public const ACAO_REATIVACAO = 'REATIVACAO';

    public const ACAO_IMPORTACAO_CRIACAO = 'IMPORTACAO_CRIACAO';

    public const ACAO_IMPORTACAO_ATUALIZACAO = 'IMPORTACAO_ATUALIZACAO';

    public const ACAO_EXCLUSAO_REGISTRO = 'EXCLUSAO_REGISTRO';

    protected $table = 'empresa_historicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'empresa_id',
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

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
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
            self::ACAO_INATIVACAO => 'Inativação',
            self::ACAO_REATIVACAO => 'Reativação',
            self::ACAO_IMPORTACAO_CRIACAO => 'Importação — Criação',
            self::ACAO_IMPORTACAO_ATUALIZACAO => 'Importação — Atualização',
            self::ACAO_EXCLUSAO_REGISTRO => 'Exclusão do vínculo',
            default => $this->acao,
        };
    }

    public function rotuloOrigem(): string
    {
        return match ($this->origem) {
            self::ORIGEM_MANUAL => 'Manual',
            self::ORIGEM_IMPORTACAO_EXCEL => 'Importação Excel',
            self::ORIGEM_SISTEMA => 'Sistema',
            default => $this->origem,
        };
    }
}
