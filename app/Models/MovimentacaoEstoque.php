<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $id_estoque
 * @property int $id_unidade_negocio
 * @property int $id_fruta
 * @property int|null $movimentacao_id
 * @property string $qtd_fruta_kg
 * @property string $qtd_fruta_um
 * @property string $preco_medio_kg
 * @property string $preco_medio_um
 * @property string $valor_total_fruta
 * @property bool $status_ultima_posicao
 * @property Carbon|null $created_at
 */
class MovimentacaoEstoque extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected $table = 'movimentacoes_estoque';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_estoque',
        'id_unidade_negocio',
        'id_fruta',
        'movimentacao_id',
        'qtd_fruta_kg',
        'qtd_fruta_um',
        'preco_medio_kg',
        'preco_medio_um',
        'valor_total_fruta',
        'status_ultima_posicao',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movimentacao_id' => 'integer',
            'qtd_fruta_kg' => 'decimal:2',
            'qtd_fruta_um' => 'decimal:2',
            'preco_medio_kg' => 'decimal:2',
            'preco_medio_um' => 'decimal:2',
            'valor_total_fruta' => 'decimal:2',
            'status_ultima_posicao' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Estoque, $this>
     */
    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class, 'id_estoque');
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
     * @return BelongsTo<Movimentacao, $this>
     */
    public function movimentacao(): BelongsTo
    {
        return $this->belongsTo(Movimentacao::class, 'movimentacao_id');
    }

    /**
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoesFinanceirasComoPosicaoAnterior(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'id_movimentacao_estoque_old');
    }

    /**
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoesFinanceirasComoPosicaoNova(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'id_movimentacao_estoque_new');
    }
}
