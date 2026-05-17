<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $id_cigam
 * @property int $id_estado
 * @property string $razao_social
 * @property string $nome
 * @property string|null $cpf_cnpj
 * @property string $custo_operacional
 * @property bool $status
 * @property bool $possui_estoque
 * @property bool $is_hub
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $cpf_cnpj_formatado
 * @property-read HistoricoCOUnNg|null $historicoCustoOperacionalAtual
 * @property-read Estado|null $estado
 */
class UnidadeNegocio extends Model
{
    use HasFactory;

    /**
     * Plural irregular em português.
     */
    protected $table = 'unidades_negocio';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_cigam',
        'id_estado',
        'razao_social',
        'nome',
        'cpf_cnpj',
        'custo_operacional',
        'status',
        'possui_estoque',
        'is_hub',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_estado' => 'integer',
            'status' => 'boolean',
            'possui_estoque' => 'boolean',
            'is_hub' => 'boolean',
            'custo_operacional' => 'decimal:2',
        ];
    }

    protected function cpfCnpjFormatado(): Attribute
    {
        return Attribute::get(function (): string {
            $value = TextoCadastro::somenteDigitos((string) $this->cpf_cnpj);

            if (strlen($value) === 11) {
                return substr($value, 0, 3).'.'.substr($value, 3, 3).'.'.substr($value, 6, 3).'-'.substr($value, 9, 2);
            }

            if (strlen($value) === 14) {
                return substr($value, 0, 2).'.'.substr($value, 2, 3).'.'.substr($value, 5, 3).'/'.substr($value, 8, 4).'-'.substr($value, 12, 2);
            }

            return $value;
        });
    }

    protected function setIdCigamAttribute(mixed $value): void
    {
        $this->attributes['id_cigam'] = TextoCadastro::normalizarIdCigamAteSeisDigitos(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setIdEstadoAttribute(mixed $value): void
    {
        $this->attributes['id_estado'] = $value === null || $value === '' ? '0' : (string) (int) $value;
    }

    protected function setRazaoSocialAttribute(mixed $value): void
    {
        $this->attributes['razao_social'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setNomeAttribute(mixed $value): void
    {
        $this->attributes['nome'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setCpfCnpjAttribute(mixed $value): void
    {
        $documento = TextoCadastro::somenteDigitos(
            $value === null ? '' : (string) $value,
        );

        $this->attributes['cpf_cnpj'] = $documento === '' ? null : $documento;
    }

    protected function setCustoOperacionalAttribute(mixed $value): void
    {
        $normalizado = number_format(max(0, (float) $value), 2, '.', '');
        $this->attributes['custo_operacional'] = $normalizado;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInativas(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    /**
     * @return BelongsTo<Estado, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    public function historicosCustoOperacional(): HasMany
    {
        return $this->hasMany(HistoricoCOUnNg::class, 'id_unidade_negocio')->latest('created_at');
    }

    public function historicoCustoOperacionalAtual(): HasOne
    {
        return $this->hasOne(HistoricoCOUnNg::class, 'id_unidade_negocio')->where('status_position', true);
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(UnidadeNegocioHistorico::class, 'unidade_negocio_id')->latest('created_at');
    }

    public function veiculos(): HasMany
    {
        return $this->hasMany(Veiculo::class, 'id_unidade_negocio')->latest('created_at');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'id_unidade_negocio')->latest('created_at');
    }

    public function pracas(): HasMany
    {
        return $this->hasMany(Praca::class, 'id_unidade_negocio')->latest('created_at');
    }

    /**
     * @return HasMany<Estoque, $this>
     */
    public function estoques(): HasMany
    {
        return $this->hasMany(Estoque::class, 'id_unidade_negocio');
    }

    /**
     * @return MorphOne<Empresa, $this>
     */
    public function registroCorporativo(): MorphOne
    {
        return $this->morphOne(Empresa::class, 'entidade');
    }
}
