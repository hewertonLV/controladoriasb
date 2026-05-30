<?php

namespace App\Models\Captacao;

use App\Models\Concerns\HasImportacaoAssincrona;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaptacaoCarteiraImportacao extends Model
{
    use HasImportacaoAssincrona;
    use SoftDeletes;

    protected $table = 'captacao_carteira_importacoes';

    protected $fillable = [
        'uuid',
        'user_id',
        'id_captacao_carteira',
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
        'id_captacao_carteira' => 'integer',
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

    public function carteira(): BelongsTo
    {
        return $this->belongsTo(CaptacaoCarteira::class, 'id_captacao_carteira');
    }
}
