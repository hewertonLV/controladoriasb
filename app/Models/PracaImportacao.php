<?php

namespace App\Models;

use App\Models\Concerns\HasImportacaoAssincrona;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracaImportacao extends Model
{
    use HasFactory;
    use HasImportacaoAssincrona;

    protected $table = 'praca_importacoes';

    protected $fillable = [
        'uuid',
        'user_id',
        'arquivo_original',
        'arquivo_path',
        'status',
        'total_linhas',
        'linhas_processadas',
        'percentual',
        'novas_count',
        'atualizacoes_count',
        'sem_alteracoes_count',
        'erros_count',
        'resultado',
        'erro_mensagem',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'total_linhas' => 'integer',
        'linhas_processadas' => 'integer',
        'percentual' => 'integer',
        'novas_count' => 'integer',
        'atualizacoes_count' => 'integer',
        'sem_alteracoes_count' => 'integer',
        'erros_count' => 'integer',
        'resultado' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
