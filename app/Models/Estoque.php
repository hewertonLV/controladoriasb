<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $id_unidade_negocio
 * @property int $id_fruta
 * @property string $qtd_fruta_kg
 * @property string $qtd_fruta_um
 * @property string $preco_medio_kg
 * @property string $preco_medio_um
 * @property string $valor_total_acumulado
 * @property int|null $ativo_unico
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Estoque extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'estoques';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_unidade_negocio',
        'id_fruta',
        'qtd_fruta_kg',
        'qtd_fruta_um',
        'preco_medio_kg',
        'preco_medio_um',
        'valor_total_acumulado',
        'ativo_unico',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Estoque $estoque): void {
            if ($estoque->isForceDeleting()) {
                return;
            }

            $estoque->forceFill(['ativo_unico' => null])->saveQuietly();
        });

        static::restoring(function (Estoque $estoque): void {
            $existeAtivo = self::query()
                ->where('id_unidade_negocio', $estoque->id_unidade_negocio)
                ->where('id_fruta', $estoque->id_fruta)
                ->whereKeyNot($estoque->id)
                ->exists();

            if ($existeAtivo) {
                throw new \RuntimeException('Nao e possivel restaurar estoque removido quando ja existe estoque ativo para a mesma unidade e fruta.');
            }

            $estoque->ativo_unico = 1;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qtd_fruta_kg' => 'decimal:2',
            'qtd_fruta_um' => 'decimal:2',
            'preco_medio_kg' => 'decimal:2',
            'preco_medio_um' => 'decimal:2',
            'valor_total_acumulado' => 'decimal:2',
            'ativo_unico' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<UnidadeNegocio, $this>
     */
    public function unidadeNegocio(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio');
    }

    /**
     * @return BelongsTo<Fruta, $this>
     */
    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'id_fruta');
    }

    /**
     * @return HasMany<MovimentacaoEstoque, $this>
     */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class, 'id_estoque');
    }
}
