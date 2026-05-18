<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $id_cigam
 * @property string $nome
 * @property string $unidade_medicao
 * @property string $kg_por_unidade_medicao
 * @property string $icms_ex_compra
 * @property string $icms_na_compra
 * @property string $um_icms
 * @property string $icms_venda
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Fruta extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'frutas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_cigam',
        'nome',
        'unidade_medicao',
        'kg_por_unidade_medicao',
        'icms_ex_compra',
        'icms_na_compra',
        'um_icms',
        'icms_venda',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kg_por_unidade_medicao' => 'decimal:2',
            'icms_ex_compra' => 'decimal:2',
            'icms_na_compra' => 'decimal:2',
            'icms_venda' => 'decimal:2',
        ];
    }

    protected function setIdCigamAttribute(mixed $value): void
    {
        $this->attributes['id_cigam'] = TextoCadastro::normalizarIdCigamAteSeisDigitos(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setNomeAttribute(mixed $value): void
    {
        $this->attributes['nome'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setUnidadeMedicaoAttribute(mixed $value): void
    {
        $this->attributes['unidade_medicao'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setKgPorUnidadeMedicaoAttribute(mixed $value): void
    {
        $kg = max(0, round((float) $value, 2));
        $this->attributes['kg_por_unidade_medicao'] = number_format($kg, 2, '.', '');
    }

    protected function setIcmsExCompraAttribute(mixed $value): void
    {
        $this->attributes['icms_ex_compra'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setIcmsNaCompraAttribute(mixed $value): void
    {
        $this->attributes['icms_na_compra'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setUmIcmsAttribute(mixed $value): void
    {
        $this->attributes['um_icms'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setIcmsVendaAttribute(mixed $value): void
    {
        $this->attributes['icms_venda'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    /**
     * @return HasMany<FrutaHistorico, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(FrutaHistorico::class, 'fruta_id');
    }

    /**
     * @return HasMany<Estoque, $this>
     */
    public function estoques(): HasMany
    {
        return $this->hasMany(Estoque::class, 'id_fruta');
    }

    /**
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'id_fruta');
    }
}
