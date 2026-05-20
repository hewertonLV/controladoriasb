<?php

namespace App\Models;

use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fruta_id
 * @property int $id_estado
 * @property int|null $user_id
 * @property string $origem
 * @property array<string, string>|null $aliquotas
 * @property bool $status_position
 * @property Carbon $created_at
 */
class FrutaIcmsHistorico extends Model
{
    public const ORIGEM_MANUAL = 'MANUAL';

    public const ORIGEM_IMPORTACAO = 'IMPORTACAO';

    public const ORIGEM_FRUTA_FORM = 'FRUTA_FORM';

    public const ORIGEM_BACKFILL = 'BACKFILL';

    public const UPDATED_AT = null;

    protected $table = 'fruta_icms_historicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fruta_id',
        'id_estado',
        'user_id',
        'origem',
        'aliquotas',
        'status_position',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliquotas' => 'array',
            'status_position' => 'boolean',
            'created_at' => 'datetime',
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

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'fruta_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    public function aliquotasArray(): array
    {
        return array_merge(
            FrutaIcmsLinhaFormulario::vazia(),
            is_array($this->aliquotas) ? $this->aliquotas : [],
        );
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    public static function fromLinhaIcms(int $frutaId, int $idEstado, array $linha): self
    {
        $linha = FrutaIcmsLinhaFormulario::normalizarChavesLegadas($linha);
        $snapshot = FrutaIcmsLinhaFormulario::vazia();

        foreach (FrutaIcmsLinhaFormulario::chaves() as $chave) {
            $snapshot[$chave] = number_format((float) ($linha[$chave] ?? 0), 2, '.', '');
        }

        return new self([
            'fruta_id' => $frutaId,
            'id_estado' => $idEstado,
            'aliquotas' => $snapshot,
        ]);
    }
}
