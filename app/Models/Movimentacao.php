<?php

namespace App\Models;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Concerns\NormalizaAtributosMonetariosMovimentacao;
use App\Support\Movimentacoes\DoacaoValorEconomico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Trilha financeira, operacional e de estoque das movimentações de fruta.
 *
 * @property int $id
 * @property int|null $id_movimentacao_estoque_old
 * @property int|null $id_movimentacao_estoque_new
 * @property int|null $id_empresa_origem
 * @property int|null $id_empresa_destino
 * @property int $id_fruta
 * @property string $valor_nf_total
 * @property string $valor_nf_um
 * @property string $valor_nf_kg
 * @property string $valor_total_movimentacao
 * @property string $qtd_fruta_um
 * @property string $qtd_fruta_kg
 * @property int|null $id_frete
 * @property string $valor_frete_rateio
 * @property string $valor_frete_um
 * @property string $valor_frete_kg
 * @property int|null $id_custo_operacional
 * @property string $valor_custo_operacional
 * @property string $saldo_estoque_fruta_kg
 * @property string $saldo_estoque_fruta_um
 * @property string $preco_medio_fruta_kg
 * @property string $preco_medio_fruta_um
 * @property string $icms_convertido_kg
 * @property int $categoria_movimentacao_id
 * @property int|null $status_movimentacao_id
 * @property string|null $status_transferencia
 * @property int|null $transferencia_origem_id
 * @property int|null $pareada_movimentacao_id
 * @property string|null $numero_nf_origem
 * @property string|null $numero_nf_destino
 * @property string|null $qtd_recebida_um
 * @property string|null $qtd_recebida_kg
 * @property string|null $status_recebimento
 * @property string|null $observacao
 * @property string|null $motivo_doacao
 * @property string|null $observacao_recebimento
 * @property int|null $movimentacao_origem_id
 * @property int|null $substituida_por_id
 * @property int $versao
 * @property string $status_registro
 * @property string|null $motivo_substituicao
 * @property Carbon|null $substituida_em
 * @property Carbon $data_movimentacao
 * @property int|null $cancelada_por
 * @property Carbon|null $cancelada_em
 * @property string|null $motivo_cancelamento
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $canceladaPor
 * @property-read MovimentacaoEstoque|null $movimentacaoEstoqueOld
 * @property-read MovimentacaoEstoque|null $movimentacaoEstoqueNew
 * @property-read Empresa|null $empresaOrigem
 * @property-read Empresa|null $empresaDestino
 * @property-read Fruta $fruta
 * @property-read Frete|null $frete
 * @property-read HistoricoCOUnNg|null $custoOperacionalHistorico
 * @property-read MovimentacaoEstoque|null $movimentacaoEstoqueVinculada
 * @property-read CategoriaMovimentacao $categoriaMovimentacao
 * @property-read Movimentacao|null $origem
 * @property-read Movimentacao|null $substituidaPor
 * @property-read Movimentacao|null $versaoAnterior
 * @property-read Movimentacao|null $movimentacaoPareada
 */
class Movimentacao extends Model
{
    use HasFactory;
    use NormalizaAtributosMonetariosMovimentacao;

    protected $table = 'movimentacoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_movimentacao_estoque_old',
        'id_movimentacao_estoque_new',
        'id_empresa_origem',
        'id_empresa_destino',
        'id_fruta',
        'valor_nf_total',
        'valor_nf_um',
        'valor_nf_kg',
        'valor_total_movimentacao',
        'qtd_fruta_um',
        'qtd_fruta_kg',
        'id_frete',
        'valor_frete_rateio',
        'valor_frete_um',
        'valor_frete_kg',
        'id_custo_operacional',
        'valor_custo_operacional',
        'saldo_estoque_fruta_kg',
        'saldo_estoque_fruta_um',
        'preco_medio_fruta_kg',
        'preco_medio_fruta_um',
        'icms_convertido_kg',
        'categoria_movimentacao_id',
        'status_movimentacao_id',
        'status_transferencia',
        'transferencia_origem_id',
        'pareada_movimentacao_id',
        'numero_nf_origem',
        'numero_nf_destino',
        'qtd_recebida_um',
        'qtd_recebida_kg',
        'status_recebimento',
        'observacao',
        'motivo_doacao',
        'observacao_recebimento',
        'movimentacao_origem_id',
        'substituida_por_id',
        'versao',
        'status_registro',
        'motivo_substituicao',
        'substituida_em',
        'data_movimentacao',
        'cancelada_por',
        'cancelada_em',
        'motivo_cancelamento',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_movimentacao_estoque_old' => 'integer',
            'id_movimentacao_estoque_new' => 'integer',
            'id_empresa_origem' => 'integer',
            'id_empresa_destino' => 'integer',
            'id_fruta' => 'integer',
            'id_frete' => 'integer',
            'id_custo_operacional' => 'integer',
            'categoria_movimentacao_id' => 'integer',
            'status_movimentacao_id' => 'integer',
            'transferencia_origem_id' => 'integer',
            'pareada_movimentacao_id' => 'integer',
            'movimentacao_origem_id' => 'integer',
            'substituida_por_id' => 'integer',
            'versao' => 'integer',
            'valor_nf_total' => 'decimal:2',
            'valor_nf_um' => 'decimal:2',
            'valor_nf_kg' => 'decimal:2',
            'valor_total_movimentacao' => 'decimal:2',
            'qtd_fruta_um' => 'decimal:2',
            'qtd_fruta_kg' => 'decimal:2',
            'qtd_recebida_um' => 'decimal:2',
            'qtd_recebida_kg' => 'decimal:2',
            'valor_frete_rateio' => 'decimal:2',
            'valor_frete_um' => 'decimal:2',
            'valor_frete_kg' => 'decimal:2',
            'valor_custo_operacional' => 'decimal:2',
            'saldo_estoque_fruta_kg' => 'decimal:2',
            'saldo_estoque_fruta_um' => 'decimal:2',
            'preco_medio_fruta_kg' => 'decimal:2',
            'preco_medio_fruta_um' => 'decimal:2',
            'icms_convertido_kg' => 'decimal:2',
            'data_movimentacao' => 'datetime',
            'substituida_em' => 'datetime',
            'cancelada_por' => 'integer',
            'cancelada_em' => 'datetime',
        ];
    }

    /**
     * Posição de estoque criada/vinculada a esta movimentação (rastreio direto).
     *
     * @return HasOne<MovimentacaoEstoque, $this>
     */
    public function movimentacaoEstoqueVinculada(): HasOne
    {
        return $this->hasOne(MovimentacaoEstoque::class, 'movimentacao_id');
    }

    /**
     * @return BelongsTo<MovimentacaoEstoque, $this>
     */
    public function movimentacaoEstoqueOld(): BelongsTo
    {
        return $this->belongsTo(MovimentacaoEstoque::class, 'id_movimentacao_estoque_old');
    }

    /**
     * @return BelongsTo<MovimentacaoEstoque, $this>
     */
    public function movimentacaoEstoqueNew(): BelongsTo
    {
        return $this->belongsTo(MovimentacaoEstoque::class, 'id_movimentacao_estoque_new');
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresaOrigem(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa_origem');
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresaDestino(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa_destino');
    }

    /**
     * @return BelongsTo<Fruta, $this>
     */
    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'id_fruta');
    }

    /**
     * @return BelongsTo<Frete, $this>
     */
    public function frete(): BelongsTo
    {
        return $this->belongsTo(Frete::class, 'id_frete');
    }

    /**
     * @return BelongsTo<HistoricoCOUnNg, $this>
     */
    public function custoOperacionalHistorico(): BelongsTo
    {
        return $this->belongsTo(HistoricoCOUnNg::class, 'id_custo_operacional');
    }

    /**
     * @return BelongsTo<CategoriaMovimentacao, $this>
     */
    public function categoriaMovimentacao(): BelongsTo
    {
        return $this->belongsTo(CategoriaMovimentacao::class, 'categoria_movimentacao_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por');
    }

    /**
     * Outra perna da mesma transferência (saída ↔ entrada pendente).
     *
     * @return BelongsTo<Movimentacao, $this>
     */
    public function movimentacaoPareada(): BelongsTo
    {
        return $this->belongsTo(Movimentacao::class, 'pareada_movimentacao_id');
    }

    /**
     * Primeira versão da cadeia (quando {@see $movimentacao_origem_id} é null, o próprio registro é a raiz).
     *
     * @return BelongsTo<Movimentacao, $this>
     */
    public function origem(): BelongsTo
    {
        return $this->belongsTo(Movimentacao::class, 'movimentacao_origem_id');
    }

    /**
     * @return BelongsTo<Movimentacao, $this>
     */
    public function substituidaPor(): BelongsTo
    {
        return $this->belongsTo(Movimentacao::class, 'substituida_por_id');
    }

    /**
     * Demais versões cuja raiz é o id deste registro (útil quando este registro é a raiz: {@see $movimentacao_origem_id} nulo).
     *
     * @return HasMany<Movimentacao, $this>
     */
    public function substituicoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'movimentacao_origem_id', 'id')
            ->orderBy('versao')
            ->orderBy('id');
    }

    /**
     * Versão imediatamente anterior que foi substituída por esta.
     *
     * @return HasOne<Movimentacao, $this>
     */
    public function versaoAnterior(): HasOne
    {
        return $this->hasOne(self::class, 'substituida_por_id', 'id');
    }

    public function categoriaTipo(): ?CategoriaMovimentacaoTipo
    {
        return CategoriaMovimentacaoTipo::tryFrom((int) $this->categoria_movimentacao_id);
    }

    public function idCadeiaRaiz(): int
    {
        return (int) ($this->movimentacao_origem_id ?? $this->id);
    }

    /**
     * Valor exibido em relatórios: em doação, custo econômico da baixa; nas demais categorias, NF total.
     */
    public function valorEconomicoParaRelatorio(): float
    {
        if ($this->categoriaTipo() === CategoriaMovimentacaoTipo::Doacao) {
            return DoacaoValorEconomico::valorTotalMovimentacao($this);
        }

        return (float) $this->valor_nf_total;
    }

    public function versaoAtual(): ?Movimentacao
    {
        $raizId = $this->idCadeiaRaiz();

        return static::query()
            ->vigentesParaCalculo()
            ->where(function ($q) use ($raizId): void {
                $q->whereKey($raizId)->orWhere('movimentacao_origem_id', $raizId);
            })
            ->orderByDesc('versao')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSubstituidas(Builder $query): Builder
    {
        return $query->where('status_registro', MovimentacaoStatusRegistro::SUBSTITUIDO->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCanceladas(Builder $query): Builder
    {
        return $query->where('status_registro', MovimentacaoStatusRegistro::CANCELADO->value);
    }

    /**
     * Registros válidos para cálculo de estoque, frete e relatórios operacionais.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVigentesParaCalculo(Builder $query): Builder
    {
        return $query->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value);
    }

    /**
     * Ordenação da linha do tempo operacional (data original da cadeia, não o created_at da revisão).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdenarLinhaDoTempo(Builder $query): Builder
    {
        return $query
            ->orderBy('data_movimentacao')
            ->orderByRaw('COALESCE(movimentacao_origem_id, id)')
            ->orderBy('versao')
            ->orderBy('id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereCategoria(Builder $query, CategoriaMovimentacaoTipo $categoria): Builder
    {
        return $query->where('categoria_movimentacao_id', $categoria->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereFruta(Builder $query, int $idFruta): Builder
    {
        return $query->where('id_fruta', $idFruta);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEntre(Builder $query, Carbon $inicio, Carbon $fim): Builder
    {
        return $query->whereBetween('created_at', [$inicio, $fim]);
    }
}
