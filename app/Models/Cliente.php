<?php

namespace App\Models;

use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\ClienteCaptacaoAgenda;
use App\Models\Captacao\ClienteFrutaVinculo;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $id_cigam
 * @property string $numero_divisao
 * @property string $razao_social
 * @property string|null $fantasia
 * @property string|null $contato_nome
 * @property string|null $contato_telefone
 * @property string|null $contato_email
 * @property string $cnpj_cpf
 * @property int $id_praca
 * @property int|null $grupo_id
 * @property int $id_unidade_negocio
 * @property string $desconto_nf
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
        'numero_divisao',
        'razao_social',
        'fantasia',
        'contato_nome',
        'contato_telefone',
        'contato_email',
        'cnpj_cpf',
        'id_praca',
        'grupo_id',
        'id_unidade_negocio',
        'id_captacao_carteira',
        'desconto_nf',
        'percentual_margem_alvo',
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
            'id_captacao_carteira' => 'integer',
            'fantasia' => 'string',
            'desconto_nf' => 'decimal:2',
            'percentual_margem_alvo' => 'decimal:2',
        ];
    }

    protected function setIdCigamAttribute(mixed $value): void
    {
        $this->attributes['id_cigam'] = TextoCadastro::normalizarIdCigamAteSeisDigitos(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setNumeroDivisaoAttribute(mixed $value): void
    {
        $digitos = TextoCadastro::somenteDigitos($value === null ? '' : (string) $value);

        $this->attributes['numero_divisao'] = str_pad(
            substr($digitos === '' ? '10' : $digitos, 0, 2),
            2,
            '0',
            STR_PAD_LEFT,
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

    protected function setContatoNomeAttribute(mixed $value): void
    {
        $texto = preg_replace('/\s+/u', ' ', (string) ($value ?? '')) ?? '';

        $this->attributes['contato_nome'] = TextoCadastro::normalizarMaiusculasOuNulo($texto);
    }

    protected function setContatoTelefoneAttribute(mixed $value): void
    {
        $digitos = TextoCadastro::somenteDigitos($value === null ? '' : (string) $value);

        $this->attributes['contato_telefone'] = $digitos === '' ? null : $digitos;
    }

    protected function setContatoEmailAttribute(mixed $value): void
    {
        $email = mb_strtolower(trim((string) ($value ?? '')));

        $this->attributes['contato_email'] = $email === '' ? null : $email;
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

    public function gruposContrato(): BelongsToMany
    {
        return $this->belongsToMany(GrupoContrato::class, 'grupo_contrato_clientes', 'cliente_id', 'grupo_contrato_id')
            ->withPivot(['competencia_inicio', 'competencia_fim'])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function unidadeNegocio(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio');
    }

    public function captacaoCarteira(): BelongsTo
    {
        return $this->belongsTo(CaptacaoCarteira::class, 'id_captacao_carteira');
    }

    /**
     * @return HasMany<ClienteCaptacaoAgenda, $this>
     */
    public function captacaoAgenda(): HasMany
    {
        return $this->hasMany(ClienteCaptacaoAgenda::class, 'id_cliente');
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

    /**
     * @return HasMany<ClienteFrutaVinculo, $this>
     */
    public function frutaVinculos(): HasMany
    {
        return $this->hasMany(ClienteFrutaVinculo::class, 'id_cliente');
    }
}
