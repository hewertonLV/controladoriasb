<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Histórico de custo operacional por unidade de negócio.
 *
 * @property int $id
 * @property int $id_unidade_negocio
 * @property string $custo_operacional
 * @property bool $status_position
 * @property Carbon|null $created_at
 * @property-read UnidadeNegocio $unidadeNegocio
 */
class HistoricoCOUnNg extends Model
{
    use HasFactory;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $table = 'historico_c_o_un_ng';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_unidade_negocio',
        'custo_operacional',
        'status_position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_unidade_negocio' => 'integer',
            'custo_operacional' => 'decimal:2',
            'status_position' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('status_position', true);
    }

    public function unidadeNegocio(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio');
    }

    /**
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'id_custo_operacional');
    }
}
