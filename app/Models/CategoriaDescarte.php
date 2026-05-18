<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property string|null $descricao
 * @property bool $impacta_kpi_perda
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CategoriaDescarte extends Model
{
    public const ID_AVARIA = 1;

    public const ID_VENCIMENTO = 2;

    public const ID_FUNGOS = 3;

    public const ID_QUALIDADE = 4;

    public const ID_TRANSPORTE = 5;

    public const ID_QUEBRA = 6;

    public const ID_CONTAMINACAO = 7;

    public const ID_MADURACAO_EXCESSIVA = 8;

    public const ID_PERDA_OPERACIONAL = 9;

    public const ID_OUTROS = 10;

    protected $table = 'categorias_descarte';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'descricao',
        'impacta_kpi_perda',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'impacta_kpi_perda' => 'boolean',
        ];
    }

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
        return $this->hasMany(Movimentacao::class, 'categoria_descarte_id');
    }
}
