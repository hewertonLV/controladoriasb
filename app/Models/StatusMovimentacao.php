<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StatusMovimentacao extends Model
{
    use HasFactory;

    public const ID_ENTRADA = 1;

    public const ID_SAIDA = 2;

    protected $table = 'status_movimentacoes';

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
}
