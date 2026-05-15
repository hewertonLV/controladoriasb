<?php

namespace App\Models;

use App\Models\Concerns\HasExportacaoAssincrona;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstoqueExportacao extends Model
{
    use HasExportacaoAssincrona;
    use HasFactory;

    protected $table = 'estoque_exportacoes';

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
