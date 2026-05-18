<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $id_sbs
 * @property string $nome
 * @property string $tipo
 * @property int $id_unidade_negocio
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Veiculo extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'veiculos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_sbs',
        'nome',
        'tipo',
        'id_unidade_negocio',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_sbs' => 'integer',
            'id_unidade_negocio' => 'integer',
        ];
    }

    protected function setIdSbsAttribute(mixed $value): void
    {
        $this->attributes['id_sbs'] = (int) TextoCadastro::somenteDigitos((string) $value);
    }

    protected function setNomeAttribute(mixed $value): void
    {
        $this->attributes['nome'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setTipoAttribute(mixed $value): void
    {
        $this->attributes['tipo'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setStatusAttribute(mixed $value): void
    {
        $this->attributes['status'] = TextoCadastro::normalizarStatusAtivoInativo(
            $value === null ? 'ATIVO' : (string) $value,
        );
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', 'ATIVO');
    }

    public function scopeInativos(Builder $query): Builder
    {
        return $query->where('status', 'INATIVO');
    }

    public function unidadeNegocio(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio');
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(VeiculoHistorico::class, 'veiculo_id')->latest('created_at');
    }

    /**
     * @return HasMany<Frete, $this>
     */
    public function fretes(): HasMany
    {
        return $this->hasMany(Frete::class, 'id_veiculo');
    }
}
