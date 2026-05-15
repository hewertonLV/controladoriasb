<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
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
        'descricao',
    ];

    protected function setNomeAttribute(mixed $value): void
    {
        $this->attributes['nome'] = TextoCadastro::normalizarMaiusculas(
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
