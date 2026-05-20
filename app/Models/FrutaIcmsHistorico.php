<?php

namespace App\Models;

use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaUmIcms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot operacional de ICMS por fruta/estado (vigência temporal).
 *
 * @property int $id
 * @property int $fruta_id
 * @property int $id_estado
 * @property int|null $user_id
 * @property string $origem
 * @property string $entrada_nacional
 * @property string $um_icms_nacional
 * @property string $entrada_externo
 * @property string $um_icms_externo
 * @property string $saida_importada
 * @property string $um_icms_venda_importada
 * @property string $saida_nacional
 * @property string $um_icms_venda_nacional
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
        'entrada_nacional',
        'um_icms_nacional',
        'entrada_externo',
        'um_icms_externo',
        'saida_importada',
        'um_icms_venda_importada',
        'saida_nacional',
        'um_icms_venda_nacional',
        'status_position',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entrada_nacional' => 'decimal:2',
            'entrada_externo' => 'decimal:2',
            'saida_importada' => 'decimal:2',
            'saida_nacional' => 'decimal:2',
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
     * Configuração ENTRADA compatível com FrutaIcmsCalculoService.
     */
    public function comoConfigEntrada(): FrutaIcms
    {
        $config = new FrutaIcms([
            'fruta_id' => $this->fruta_id,
            'id_estado' => $this->id_estado,
            'operacao' => FrutaIcmsOperacao::ENTRADA,
            'icms_nacional' => $this->entrada_nacional,
            'um_icms_nacional' => $this->um_icms_nacional ?: FrutaUmIcms::KG->value,
            'icms_externo' => $this->entrada_externo,
            'um_icms_externo' => $this->um_icms_externo ?: FrutaUmIcms::KG->value,
        ]);
        $config->exists = true;

        return $config;
    }

    public function comoConfigSaida(): FrutaIcms
    {
        $config = new FrutaIcms([
            'fruta_id' => $this->fruta_id,
            'id_estado' => $this->id_estado,
            'operacao' => FrutaIcmsOperacao::SAIDA,
            'icms_venda_importada' => $this->saida_importada,
            'um_icms_venda_importada' => $this->um_icms_venda_importada ?: FrutaUmIcms::PCT->value,
            'icms_venda_nacional' => $this->saida_nacional,
            'um_icms_venda_nacional' => $this->um_icms_venda_nacional ?: FrutaUmIcms::PCT->value,
        ]);
        $config->exists = true;

        return $config;
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    public static function fromLinhaIcms(int $frutaId, int $idEstado, array $linha): self
    {
        return new self([
            'fruta_id' => $frutaId,
            'id_estado' => $idEstado,
            'entrada_nacional' => $linha['entrada_nacional'] ?? $linha['compra_nacional'] ?? 0,
            'um_icms_nacional' => $linha['entrada_um_nacional'] ?? $linha['um_compra_nacional'] ?? FrutaUmIcms::KG->value,
            'entrada_externo' => $linha['entrada_externo'] ?? $linha['compra_exterior'] ?? 0,
            'um_icms_externo' => $linha['entrada_um_externo'] ?? $linha['um_compra_exterior'] ?? FrutaUmIcms::KG->value,
            'saida_importada' => $linha['saida_importada'] ?? $linha['venda_importada'] ?? 0,
            'um_icms_venda_importada' => $linha['saida_um_importada'] ?? $linha['um_venda_importada'] ?? FrutaUmIcms::PCT->value,
            'saida_nacional' => $linha['saida_nacional'] ?? $linha['venda_nacional'] ?? 0,
            'um_icms_venda_nacional' => $linha['saida_um_nacional'] ?? $linha['um_venda_nacional'] ?? FrutaUmIcms::PCT->value,
        ]);
    }
}
