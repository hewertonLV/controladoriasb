<?php

namespace App\Models\Captacao;

use App\Enums\CaptacaoFaturamentoDiaStatus;
use App\Models\UnidadeNegocio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptacaoFaturamentoDia extends Model
{
    protected $table = 'captacao_faturamento_dias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'data_referencia',
        'id_unidade_negocio_faturamento',
        'status',
        'finalizado_em',
        'finalizado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_referencia' => 'date',
            'status' => CaptacaoFaturamentoDiaStatus::class,
            'finalizado_em' => 'datetime',
        ];
    }

    public function unidadeFaturamento(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_faturamento');
    }

    public function finalizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalizado_por_user_id');
    }
}
