<?php

namespace App\Models;

use App\Models\Concerns\HasImportacaoAssincrona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Controle de uma execução de importação de Unidades de Negócio via Excel.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string|null $arquivo_original
 * @property string $arquivo_path
 * @property string $status
 * @property int $total_linhas
 * @property int $linhas_processadas
 * @property int $percentual
 * @property int $novas_count
 * @property int $atualizacoes_count
 * @property int $sem_alteracoes_count
 * @property int $erros_count
 * @property array<string,mixed>|null $resultado
 * @property string|null $erro_mensagem
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class UnidadeNegocioImportacao extends Model
{
    use HasImportacaoAssincrona;

    protected $table = 'unidade_negocio_importacoes';

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
