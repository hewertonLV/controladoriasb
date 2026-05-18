<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmpresaExportacao extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TIPO_PDF = 'PDF';

    public const STATUS_AGUARDANDO = 'AGUARDANDO';

    public const STATUS_PROCESSANDO = 'PROCESSANDO';

    public const STATUS_CONCLUIDO = 'CONCLUIDO';

    public const STATUS_FALHOU = 'FALHOU';

    protected $table = 'empresa_exportacoes';

    protected $fillable = [
        'uuid',
        'user_id',
        'tipo',
        'status',
        'filtros',
        'arquivo_path',
        'arquivo_nome',
        'total_registros',
        'erro_mensagem',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filtros' => 'array',
        'total_registros' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConcluido(): bool
    {
        return $this->status === self::STATUS_CONCLUIDO;
    }

    public function isFalhou(): bool
    {
        return $this->status === self::STATUS_FALHOU;
    }

    public function isFinalizado(): bool
    {
        return $this->isConcluido() || $this->isFalhou();
    }
}
