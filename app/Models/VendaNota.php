<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Cabeçalho fiscal/comercial que agrupa os itens de venda.
 *
 * @property int $id
 * @property string $numero_nf
 * @property int $id_empresa_origem
 * @property int $id_empresa_destino
 * @property int $id_unidade_negocio_faturamento
 * @property Carbon $data_emissao
 * @property string $valor_total_nf
 * @property string $status_registro
 * @property string|null $observacao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VendaNota extends Model
{
    use SoftDeletes;

    protected $table = 'vendas_notas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'numero_nf',
        'id_empresa_origem',
        'id_empresa_destino',
        'id_unidade_negocio_faturamento',
        'data_emissao',
        'valor_total_nf',
        'status_registro',
        'observacao',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_empresa_origem' => 'integer',
            'id_empresa_destino' => 'integer',
            'id_unidade_negocio_faturamento' => 'integer',
            'data_emissao' => 'datetime',
            'valor_total_nf' => 'decimal:2',
        ];
    }

    protected function setNumeroNfAttribute(mixed $value): void
    {
        $this->attributes['numero_nf'] = trim((string) ($value ?? ''));
    }

    protected function setValorTotalNfAttribute(mixed $value): void
    {
        $this->attributes['valor_total_nf'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
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
     * @return BelongsTo<UnidadeNegocio, $this>
     */
    public function unidadeFaturamento(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_faturamento');
    }

    /**
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'venda_nota_id');
    }
}
