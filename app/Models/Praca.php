<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property int $id_unidade_negocio
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Praca extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pracas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'id_unidade_negocio',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_unidade_negocio' => 'integer',
        ];
    }

    protected function setNomeAttribute(mixed $value): void
    {
        $this->attributes['nome'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    public function unidadeNegocio(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio');
    }

    /**
     * @return HasMany<Cliente, $this>
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'id_praca');
    }

    /**
     * @return HasMany<PracaHistorico, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(PracaHistorico::class, 'praca_id');
    }
}
