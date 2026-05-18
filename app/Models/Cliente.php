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
 * @property string $razao_social
 * @property string|null $fantasia
 * @property string $cnpj_cpf
 * @property int $id_praca
 * @property int|null $grupo_id
 * @property int $id_unidade_negocio
 * @property string $desconto_nf
 * @property string $desconto_contrato
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $cnpj_cpf_formatado
 */
class Cliente extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'clientes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_cigam',
        'razao_social',
        'fantasia',
        'cnpj_cpf',
        'id_praca',
        'grupo_id',
        'id_unidade_negocio',
        'desconto_nf',
        'desconto_contrato',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_praca' => 'integer',
            'grupo_id' => 'integer',
            'id_unidade_negocio' => 'integer',
            'fantasia' => 'string',
            'desconto_nf' => 'decimal:2',
            'desconto_contrato' => 'decimal:2',
        ];
    }

    protected function setIdCigamAttribute(mixed $value): void
    {
        $this->attributes['id_cigam'] = TextoCadastro::normalizarIdCigamAteSeisDigitos(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setRazaoSocialAttribute(mixed $value): void
    {
        $this->attributes['razao_social'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setFantasiaAttribute(mixed $value): void
    {
        $texto = preg_replace('/\s+/u', ' ', (string) ($value ?? '')) ?? '';

        $this->attributes['fantasia'] = TextoCadastro::normalizarMaiusculasOuNulo(
            $texto,
        );
    }

    protected function setCnpjCpfAttribute(mixed $value): void
    {
        $this->attributes['cnpj_cpf'] = TextoCadastro::somenteDigitos(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setDescontoNfAttribute(mixed $value): void
    {
        $this->attributes['desconto_nf'] = $this->normalizarDesconto($value);
    }

    protected function setDescontoContratoAttribute(mixed $value): void
    {
        $this->attributes['desconto_contrato'] = $this->normalizarDesconto($value);
    }

    private function normalizarDesconto(mixed $value): string
    {
        $num = (float) $value;
        $num = max(0, $num);

        return number_format($num, 2, '.', '');
    }

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

    public function praca(): BelongsTo
    {
        return $this->belongsTo(Praca::class, 'id_praca');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function unidadeNegocio(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio');
    }

    /**
     * Hub corporativo (registro central em `empresas`).
     *
     * @return MorphOne<Empresa, $this>
     */
    public function registroCorporativo(): MorphOne
    {
        return $this->morphOne(Empresa::class, 'entidade');
    }

    /**
     * @return HasMany<ClienteHistorico, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(ClienteHistorico::class, 'cliente_id');
    }
}
