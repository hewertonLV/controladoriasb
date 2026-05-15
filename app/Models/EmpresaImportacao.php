<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Controle de uma execução de importação de Empresas via Excel.
 *
 * O arquivo é salvo em disco, este registro guarda o progresso e o
 * resultado bruto (novas/atualizações/sem_alteracoes/erros) para que
 * a tela possa fazer polling até a conclusão e, em seguida, permitir
 * a confirmação seletiva das alterações.
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmpresaImportacao extends Model
{
    use HasFactory;

    public const STATUS_AGUARDANDO = 'AGUARDANDO';

    public const STATUS_PROCESSANDO = 'PROCESSANDO';

    public const STATUS_CONCLUIDO = 'CONCLUIDO';

    public const STATUS_FALHOU = 'FALHOU';

    protected $table = 'empresa_importacoes';

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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAguardando(): bool
    {
        return $this->status === self::STATUS_AGUARDANDO;
    }

    public function isProcessando(): bool
    {
        return $this->status === self::STATUS_PROCESSANDO;
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
