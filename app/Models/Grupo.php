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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupos';

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
     * @return HasMany<Cliente, $this>
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'grupo_id');
    }

    /**
     * @return HasMany<GrupoHistorico, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(GrupoHistorico::class, 'grupo_id');
    }
}
