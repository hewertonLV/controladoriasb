<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $id_cigam
 * @property int $id_estado
 * @property string $razao_social
 * @property string|null $fantasia
 * @property string $cnpj_cpf
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $cnpj_cpf_formatado
 * @property-read Estado|null $estado
 */
class Fornecedor extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fornecedores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_cigam',
        'id_estado',
        'razao_social',
        'fantasia',
        'cnpj_cpf',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_estado' => 'integer',
        ];
    }

    /**
     * Exibe CPF/CNPJ formatado (armazenamento permanece só com dígitos).
     */
    protected function cnpjCpfFormatado(): Attribute
    {
        return Attribute::get(function (): string {
            $value = TextoCadastro::somenteDigitos((string) $this->cnpj_cpf);

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

    protected function setFantasiaAttribute(mixed $value): void
    {
        $this->attributes['fantasia'] = TextoCadastro::normalizarMaiusculasOuNulo(
            $value === null ? null : (string) $value,
        );
    }

    protected function setCnpjCpfAttribute(mixed $value): void
    {
        $this->attributes['cnpj_cpf'] = TextoCadastro::somenteDigitos(
            $value === null ? '' : (string) $value,
        );
    }

    /**
     * @return BelongsTo<Estado, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    /**
     * @return HasMany<FornecedorHistorico, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(FornecedorHistorico::class, 'fornecedor_id');
    }

    /**
     * @return MorphOne<Empresa, $this>
     */
    public function registroCorporativo(): MorphOne
    {
        return $this->morphOne(Empresa::class, 'entidade');
    }
}
