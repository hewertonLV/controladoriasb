<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $nome
 * @property string $abreviacao
 * @property string|null $descricao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Estado extends Model
{
    use HasFactory;

    public const ID_CEARA = 1;

    public const ID_PERNAMBUCO = 2;

    public const ID_ALAGOAS = 3;

    protected $table = 'estados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'abreviacao',
        'descricao',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'nome' => 'string',
        'abreviacao' => 'string',
        'descricao' => 'string',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $estado): void {
            $estado->validarCadastro();
        });
    }

    protected function setNomeAttribute(mixed $value): void
    {
        $this->attributes['nome'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setAbreviacaoAttribute(mixed $value): void
    {
        $this->attributes['abreviacao'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setDescricaoAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['descricao'] = null;

            return;
        }

        $t = trim((string) $value);
        $this->attributes['descricao'] = $t === '' ? null : $t;
    }

    public static function normalizarChaveBusca(mixed $value): string
    {
        return TextoCadastro::normalizarBuscaEstado($value === null ? '' : (string) $value);
    }

    public static function buscarPorAbreviacaoOuNome(mixed $value): ?self
    {
        $chave = self::normalizarChaveBusca($value);

        if ($chave === '') {
            return null;
        }

        $estado = self::query()
            ->where('abreviacao', $chave)
            ->first();

        if ($estado !== null) {
            return $estado;
        }

        return self::query()
            ->get(['id', 'nome', 'abreviacao', 'descricao'])
            ->first(fn (self $estado): bool => self::normalizarChaveBusca($estado->nome) === $chave);
    }

    private function validarCadastro(): void
    {
        $erros = [];
        $nome = (string) ($this->attributes['nome'] ?? '');
        $abreviacao = (string) ($this->attributes['abreviacao'] ?? '');

        if ($nome === '') {
            $erros['nome'][] = 'O nome é obrigatório.';
        }

        if ($abreviacao === '') {
            $erros['abreviacao'][] = 'A abreviação é obrigatória.';
        } elseif (mb_strlen($abreviacao, 'UTF-8') !== 2) {
            $erros['abreviacao'][] = 'A abreviação deve ter exatamente 2 caracteres.';
        }

        if ($nome !== '' && $this->existeOutroRegistroCom('nome', $nome)) {
            $erros['nome'][] = 'O nome já está em uso.';
        }

        if ($abreviacao !== '' && $this->existeOutroRegistroCom('abreviacao', $abreviacao)) {
            $erros['abreviacao'][] = 'A abreviação já está em uso.';
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }
    }

    private function existeOutroRegistroCom(string $campo, string $valor): bool
    {
        return self::query()
            ->where($campo, $valor)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

    /**
     * @return HasMany<UnidadeNegocio, $this>
     */
    public function unidadesNegocio(): HasMany
    {
        return $this->hasMany(UnidadeNegocio::class, 'id_estado');
    }

    /**
     * @return HasMany<Fornecedor, $this>
     */
    public function fornecedores(): HasMany
    {
        return $this->hasMany(Fornecedor::class, 'id_estado');
    }
}
