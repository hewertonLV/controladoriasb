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
 * @property string $nome
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CategoriaMovimentacao extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const ID_COMPRA = 1;

    public const ID_TRANSFERENCIA = 2;

    public const ID_VENDA = 3;

    public const ID_DOACAO = 4;

    public const ID_DESCARTE = 5;

    public const ID_DEVOLUCAO = 6;

    public const ID_CONVERSAO_EMBALAGEM = 7;

    public const ID_ENTRADA_ESTOQUE = 8;

    protected $table = 'categorias_movimentacao';

    public static function idPorNome(string $nome): int
    {
        $id = self::query()->where('nome', TextoCadastro::normalizarMaiusculas(trim($nome)))->value('id');

        if ($id === null) {
            throw new \InvalidArgumentException("Categoria de movimentação «{$nome}» não encontrada.");
        }

        return (int) $id;
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
    ];

    protected function setNomeAttribute(mixed $value): void
    {
        $this->attributes['nome'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    /**
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'categoria_movimentacao_id');
    }
}
