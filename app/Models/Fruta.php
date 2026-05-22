<?php

namespace App\Models;

use App\Enums\FrutaProcedencia;
use App\Enums\FrutaUnidadeMedicao;
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
 * @property string $procedencia
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
        'procedencia',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kg_por_unidade_medicao' => 'decimal:2',
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
        $casas = $this->casasDecimaisKgPorUnidadeMedicao();
        $kg = max(0, round((float) $value, $casas));
        $this->attributes['kg_por_unidade_medicao'] = number_format($kg, $casas, '.', '');
    }

    public function casasDecimaisKgPorUnidadeMedicao(): int
    {
        $um = mb_strtoupper(trim((string) ($this->attributes['unidade_medicao'] ?? $this->unidade_medicao ?? '')), 'UTF-8');

        return FrutaUnidadeMedicao::tryFrom($um)?->casasDecimaisKg() ?? 2;
    }

    /**
     * @return HasMany<FrutaIcmsAliquota, $this>
     */
    public function icmsAliquotas(): HasMany
    {
        return $this->hasMany(FrutaIcmsAliquota::class, 'fruta_id');
    }

    public function procedenciaEnum(): FrutaProcedencia
    {
        $valor = mb_strtoupper(trim((string) ($this->procedencia ?? '')), 'UTF-8');

        return FrutaProcedencia::tryFrom($valor) ?? FrutaProcedencia::NACIONAL;
    }

    protected function setProcedenciaAttribute(mixed $value): void
    {
        $normalizado = mb_strtoupper(trim((string) ($value === null ? FrutaProcedencia::NACIONAL->value : $value)), 'UTF-8');
        $this->attributes['procedencia'] = in_array($normalizado, FrutaProcedencia::values(), true)
            ? $normalizado
            : FrutaProcedencia::NACIONAL->value;
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
